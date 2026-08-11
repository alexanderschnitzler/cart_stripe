<?php

declare(strict_types=1);
namespace GeorgRinger\CartStripe\Service;

use GeorgRinger\CartStripe\Configuration;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

/**
 * Bootstraps the Stripe SDK and provides the read access needed to verify a payment
 * server-side. Shared by the redirect listener and the return actions so both talk
 * to Stripe through the same key and the same non-composer autoload handling.
 */
class StripeApi
{
    public function __construct(
        protected Configuration $configuration
    ) {}

    public function initialize(): void
    {
        $this->loadLibrary();
        Stripe::setApiKey($this->configuration->getStripeApiKey());
    }

    /**
     * Returns null for anything that is not a session we can read -- an empty or
     * forged id, a revoked key, Stripe being unreachable. Callers must treat null
     * as "not verified" and never fall back to trusting the request.
     */
    public function retrieveSession(string $sessionId): ?Session
    {
        if ($sessionId === '') {
            return null;
        }

        $this->initialize();

        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException) {
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
