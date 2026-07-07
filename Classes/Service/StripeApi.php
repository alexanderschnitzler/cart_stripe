<?php

declare(strict_types=1);

namespace Schnitzler\CartStripe\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Schnitzler\CartStripe\Configuration;
use Stripe\Checkout\Session;
use Stripe\Stripe;

/**
 * Bootstraps the Stripe SDK and provides the read access needed to verify a payment
 * server-side. Shared by the redirect listener and the return actions so both talk
 * to Stripe through the same key and the same non-composer autoload handling.
 */
class StripeApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Query parameter carrying Stripe's {CHECKOUT_SESSION_ID} back to the return
     * actions. Deliberately not named `session_id`: the name has to be added to the
     * cHash exclude list in ext_localconf.php, and excluding something that generic
     * from cache hash calculation site-wide would affect other extensions too.
     */
    public const SESSION_ID_PARAMETER = 'cartStripeSessionId';

    public function __construct(
        protected Configuration $configuration
    ) {
    }

    public function initialize(): void
    {
        $this->loadLibrary();
        Stripe::setApiKey($this->configuration->getStripeApiKey());
    }

    /**
     * Returns null for anything that is not a session we can read -- an empty or
     * forged id, a revoked key, Stripe being unreachable, a misconfigured non-composer
     * autoload path. Callers must treat null as "not verified" and never fall back to
     * trusting the request.
     *
     * Every failure is caught, not only ApiErrorException: this runs after the buyer
     * has paid, where an uncaught error would mean a 500 instead of a page telling
     * them to get in touch.
     */
    public function retrieveSession(string $sessionId): Session|null
    {
        if ($sessionId === '') {
            return null;
        }

        try {
            $this->initialize();

            return Session::retrieve($sessionId);
        } catch (\Throwable $exception) {
            $this->logger?->error('Could not retrieve Stripe Checkout Session', [
                'sessionId' => $sessionId,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    private function loadLibrary(): void
    {
        if (class_exists(Session::class)) {
            return;
        }
        $path = $this->configuration->getNonComposerAutoloadPath();
        if ($path === '' || $path === '0') {
            throw new \UnexpectedValueException('No path to non composer autoload found', 1627993943);
        }
        if (!is_file($path)) {
            throw new \UnexpectedValueException('No file found at path ' . $path, 1627993944);
        }
        require_once $path;
    }
}
