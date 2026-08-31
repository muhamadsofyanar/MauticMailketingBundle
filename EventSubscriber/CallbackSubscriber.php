<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailketingBundle\EventSubscriber;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MauticMailketingBundle\Mailer\Transport\MailketingApiTransport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Dsn;

final class CallbackSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TransportCallback $transportCallback,
        private readonly CoreParametersHelper $coreParametersHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => 'processCallbackRequest',
        ];
    }

    public function processCallbackRequest(TransportWebhookEvent $event): void
    {
        try {
            $dsn = Dsn::fromString((string) $this->coreParametersHelper->get('mailer_dsn'));
        } catch (\Throwable) {
            return;
        }

        if (MailketingApiTransport::SCHEME !== $dsn->getScheme()) {
            return;
        }

        $payload = json_decode($event->getRequest()->getContent(), true);
        if (!is_array($payload)) {
            $payload = $event->getRequest()->request->all();
        }

        $email = $payload['email'] ?? null;
        $type = $payload['type'] ?? null;
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if ('bounce' === $type) {
            $this->transportCallback->addFailureByAddress(
                $email,
                (string) ($payload['reason'] ?? 'bounce'),
                DoNotContact::BOUNCED
            );
        } elseif ('unsubscribe' === $type) {
            $this->transportCallback->addFailureByAddress(
                $email,
                'unsubscribe',
                DoNotContact::UNSUBSCRIBED
            );
        } else {
            return;
        }

        $event->setResponse(new Response('Callback processed'));
    }
}
