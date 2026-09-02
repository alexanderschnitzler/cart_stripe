<?php

declare(strict_types=1);

defined('TYPO3') || exit('Access to file "' . basename(__FILE__) . '" denied.');

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->private()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('Schnitzler\\CartStripe\\', __DIR__ . '/../Classes/');
};
