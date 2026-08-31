<?php

declare(strict_types=1);

use MauticPlugin\MauticMailketingBundle\EventSubscriber\CallbackSubscriber;
use MauticPlugin\MauticMailketingBundle\Mailer\Transport\MailketingApiTransportFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(MailketingApiTransportFactory::class)
        ->parent('mailer.transport_factory.abstract')
        ->tag('mailer.transport_factory');

    $services->set(CallbackSubscriber::class)
        ->autowire()
        ->autoconfigure();
};
