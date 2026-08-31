<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailketingBundle\Mailer\Transport;

use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class MailketingApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (MailketingApiTransport::SCHEME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'mailketing', $this->getSupportedSchemes());
        }

        $apiToken = $dsn->getPassword() ?: $dsn->getUser();
        if (null === $apiToken || '' === $apiToken) {
            throw new InvalidArgumentException('Mailketing API token is missing from the DSN password field.');
        }

        return new MailketingApiTransport(
            $apiToken,
            $this->client,
            $this->dispatcher,
            $this->logger
        );
    }

    protected function getSupportedSchemes(): array
    {
        return [MailketingApiTransport::SCHEME];
    }
}
