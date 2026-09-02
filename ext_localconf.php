<?php

declare(strict_types=1);

defined('TYPO3') || exit('Access to file "' . basename(__FILE__) . '" denied.');

use Schnitzler\CartStripe\Controller\Order\PaymentController;
use Schnitzler\CartStripe\Service\StripeApi;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or exit;

ExtensionUtility::configurePlugin(
    'CartStripe',
    'Cart',
    [
        PaymentController::class => 'success, cancel, notify',
    ],
    [
        PaymentController::class => 'success, cancel, notify',
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = StripeApi::SESSION_ID_PARAMETER;
