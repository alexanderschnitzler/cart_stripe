<?php

declare(strict_types=1);

defined('TYPO3') || exit('Access to file "' . basename(__FILE__) . '" denied.');

/** @var array $EM_CONF */
/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Cart - Stripe',
    'description' => 'Shopping Cart(s) for TYPO3 - Stripe Payment Provider',
    'category' => 'services',
    'author' => 'Alexander Schnitzler',
    'author_email' => 'git@alexanderschnitzler.de',
    'state' => 'stable',
    'version' => '14.3.0',
    'constraints' => [
        'depends' => [],
        'conflicts' => [],
        'suggests' => [],
    ],
];
