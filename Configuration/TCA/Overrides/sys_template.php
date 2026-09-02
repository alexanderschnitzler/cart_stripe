<?php

declare(strict_types=1);

defined('TYPO3') || exit('Access to file "' . basename(__FILE__) . '" denied.');

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(static function () {
    ExtensionManagementUtility::addStaticFile(
        'cart_stripe',
        'Configuration/TypoScript',
        'Shopping Cart - Stripe'
    );
});
