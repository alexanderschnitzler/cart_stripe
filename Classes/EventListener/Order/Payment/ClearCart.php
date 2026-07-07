<?php

declare(strict_types=1);

namespace Schnitzler\CartStripe\EventListener\Order\Payment;

use Extcode\Cart\Event\Order\EventInterface;
use Extcode\Cart\EventListener\Order\Finish\ClearCart as FinishClearCart;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'cart-stripe--order--payment--clear-cart',
    before: 'cart-stripe--order--payment--provider-redirect',
)]
class ClearCart extends FinishClearCart
{
    public function __invoke(EventInterface $event): void
    {
        $orderItem = $event->getOrderItem();

        $provider = $orderItem->getPayment()?->getProvider();

        if (str_starts_with((string)$provider, 'STRIPE')) {
            parent::__invoke($event);
        }
    }
}
