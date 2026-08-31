<?php

declare(strict_types=1);

return [
    'name'        => 'Mailketing integration',
    'description' => 'Mailketing API transport for Mautic 5',
    'version'     => '5.0.0',
    'author'      => 'Fadli Dzil Ikram',
    'services'    => [
        'events' => [
            'mautic.mailketing.callback_subscriber' => [
                'class'     => \MauticPlugin\MauticMailketingBundle\EventSubscriber\CallbackSubscriber::class,
                'arguments' => [
                    'mautic.email.model.transport_callback',
                    'mautic.helper.core_parameters',
                ],
            ],
        ],
        'other' => [
            'mautic.mailketing.transport_factory' => [
                'class'     => \MauticPlugin\MauticMailketingBundle\Mailer\Transport\MailketingApiTransportFactory::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.mailketing.http_client',
                    'monolog.logger.mautic',
                ],
                'tag' => 'mailer.transport_factory',
            ],
            'mautic.mailketing.http_client' => [
                'class' => \Symfony\Component\HttpClient\NativeHttpClient::class,
            ],
        ],
    ],
];
