<?php

declare(strict_types=1);
namespace GeorgRinger\CartStripe\Controller\Order;

use Extcode\Cart\Controller\Cart\ActionController;
use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Event\Order\FinishEvent;
use Extcode\Cart\Service\SessionHandler;
use Extcode\Cart\Utility\CartUtility;
use GeorgRinger\CartStripe\Service\StripeApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Checkout\Session;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PaymentController extends ActionController
{
    use LoggerAwareTrait;

    protected ?Cart $cartObject = null;

    /**
     * @var array<mixed>
     */
    protected array $cartConf = [];

    /**
     * @var array<mixed>
     */
    protected $cartStripeConf = [];

    public function __construct(
        protected PersistenceManager $persistenceManager,
        protected SessionHandler $sessionHandler,
        protected CartRepository $cartRepository,
        protected PaymentRepository $paymentRepository,
        protected StripeApi $stripeApi,
        CartUtility $cartUtility,
        private readonly ConnectionPool $connectionPool
    ) {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $this->cartUtility = $cartUtility;
    }

    public function initializeAction(): void
    {
        $this->cartConf =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartStripeConf =
            $this->configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartStripe'
            );
        parent::initializeAction();
    }

    public function successAction(): ResponseInterface
    {
        $hash = $this->getHashArgument();
        if ($hash === '') {
            return $this->errorResponse('success.access_denied');
        }

        $this->loadCartByHash($hash);
        if (!$this->cartObject instanceof Cart) {
            return $this->errorResponse('success.error_occurred');
        }

        $orderItem = $this->cartObject->getOrderItem();
        $payment = $orderItem?->getPayment();
        if (!$orderItem instanceof Item || !$payment instanceof Payment) {
            return $this->errorResponse('success.error_occurred');
        }

        if ($payment->getStatus() !== 'paid') {
            // Arriving at this URL proves nothing. The buyer knows the hash -- it is
            // part of the cancel_url they are sent to when they abort -- and they
            // choose when to call this action. Only Stripe's own answer for exactly
            // this cart may settle the payment.
            $session = $this->stripeApi->retrieveSession(
                (string)($this->request->getQueryParams()['session_id'] ?? '')
            );

            if (!$this->sessionSettlesCart($session, $hash)) {
                $this->logger->warning('Rejected unverified Stripe payment return', [
                    'orderItem' => $orderItem->getUid(),
                    'sHash' => $hash,
                ]);

                return $this->errorResponse('success.not_verified');
            }

            $payment->setStatus('paid');
            $this->paymentRepository->update($payment);
            $this->persistenceManager->persistAll();

            $finishEvent = new FinishEvent($this->cart, $orderItem, $this->cartConf);
            $this->eventDispatcher->dispatch($finishEvent);
            $this->sessionHandler->writeCart(
                $this->cartConf['settings']['cart']['pid'],
                $this->cartUtility->getNewCart($this->cartConf)
            );
        }

        return $this->redirect('show', 'Cart\Order', 'Cart', ['orderItem' => $orderItem]);
    }

    public function cancelAction(): ResponseInterface
    {
        $hash = $this->getHashArgument();
        if ($hash === '') {
            return $this->errorResponse('cancel.access_denied');
        }

        $this->loadCartByHash($hash);
        if (!$this->cartObject instanceof Cart) {
            return $this->errorResponse('cancel.error_occurred');
        }

        $orderItem = $this->cartObject->getOrderItem();
        $payment = $orderItem?->getPayment();
        if (!$orderItem instanceof Item || !$payment instanceof Payment) {
            return $this->errorResponse('cancel.error_occurred');
        }

        // This action is reachable by anyone who knows the hash, so a settled payment
        // must not be reopened here.
        if ($payment->getStatus() === 'paid') {
            return $this->errorResponse('cancel.already_paid');
        }

        $payment->setStatus('canceled');

        $this->paymentRepository->update($payment);
        $this->persistenceManager->persistAll();

        $this->addFlashMessage(
            LocalizationUtility::translate(
                'tx_cartstripe.controller.order.payment.action.cancel.successfully_canceled',
                'CartStripe'
            ) ?? '',
        );

        return $this->redirect('show', 'Cart\Cart', 'Cart');
    }

    /**
     * A Checkout Session may settle a cart only if Stripe reports it as paid *and* it
     * was created for this very cart. Without the second check a single paid session
     * -- the attacker's own one-cent order, say -- could be replayed against every
     * other cart whose hash is known.
     */
    private function sessionSettlesCart(?Session $session, string $hash): bool
    {
        if (!$session instanceof Session) {
            return false;
        }

        return $session->payment_status === 'paid'
            && (string)($session->metadata['cartSHash'] ?? '') === $hash;
    }

    private function getHashArgument(): string
    {
        if (!$this->request->hasArgument('hash')) {
            return '';
        }

        $hash = $this->request->getArgument('hash');

        return is_string($hash) ? $hash : '';
    }

    private function errorResponse(string $labelKey): ResponseInterface
    {
        $this->addFlashMessage(
            LocalizationUtility::translate(
                'tx_cartstripe.controller.order.payment.action.' . $labelKey,
                'CartStripe'
            ) ?? '',
            '',
            ContextualFeedbackSeverity::ERROR
        );

        return $this->htmlResponse();
    }

    protected function loadCartByHash(string $hash, string $type = 'SHash'): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_cart_domain_model_cart');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_cart_domain_model_cart')
            ->where(
                $queryBuilder->expr()->eq('s_hash', $queryBuilder->createNamedParameter($hash))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            // todo exception
            return;
        }

        // The second argument of unserialize() is an options array and only knows the
        // key 'allowed_classes'. This used to be passed as a bare list, so the key was
        // never found and PHP silently allowed *every* class. Spelled out here -- see
        // the note in the Readme about narrowing this to an explicit allow list.
        $unserializedCart = unserialize($row['serialized_cart'], ['allowed_classes' => true]);
        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        $items = $dataMapper->map(Cart::class, [$row]);
        /** @var Cart $cartObject */
        $cartObject = $items[0];
        $cartObject->setCart($unserializedCart);
        $this->cart = $unserializedCart;
        $this->cartObject = $cartObject;
    }
}
