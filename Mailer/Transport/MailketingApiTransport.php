<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailketingBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailketingApiTransport extends AbstractApiTransport implements TokenTransportInterface
{
    use TokenTransportTrait;

    public const SCHEME = 'mailketing+api';
    public const API_URL = 'https://api.mailketing.co.id/api/v2/send';

    public function __construct(
        private readonly string $apiToken,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return self::SCHEME.'://default';
    }

    /**
     * Mautic campaigns keep recipient metadata on MauticMessage. The regular
     * Symfony Email recipients are still used for test and direct messages.
     *
     * @return list<Address>
     */
    private function getRecipients(SentMessage $sentMessage, Email $email): array
    {
        $recipients = array_merge($email->getTo(), $email->getCc(), $email->getBcc());
        $original = $sentMessage->getOriginalMessage();
        if ($original instanceof MauticMessage) {
            foreach ($original->getMetadata() as $address => $metadata) {
                if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $name = is_array($metadata) && is_string($metadata['name'] ?? null)
                    ? $metadata['name']
                    : '';
                $recipients[] = new Address($address, $name);
            }
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof Address) {
                continue;
            }
            $unique[strtolower($recipient->getAddress())] = $recipient;
        }

        return array_values($unique);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $recipients = $this->getRecipients($sentMessage, $email);
        if ([] === $recipients) {
            throw new TransportException('Mailketing requires at least one recipient.');
        }

        $from = $email->getFrom()[0] ?? null;
        if (!$from instanceof Address) {
            throw new TransportException('Mailketing requires a sender address.');
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $content = $html ?: $text;
        if (null === $content || '' === trim($content)) {
            throw new TransportException('Mailketing requires email content.');
        }

        $lastResponse = null;
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof Address) {
                continue;
            }

            $payload = [
                'from_name'  => $from->getName() ?: $from->getAddress(),
                'from_email' => $from->getAddress(),
                'recipient'  => $recipient->getAddress(),
                'subject'    => $email->getSubject(),
                'content'    => $content,
            ];

            $messageId = trim($sentMessage->getMessageId(), '<>');
            if ('' !== $messageId) {
                $payload['message_id'] = $messageId;
            }

            $lastResponse = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Api-Token'  => $this->apiToken,
                ],
                'json' => $payload,
            ]);

            $statusCode = $lastResponse->getStatusCode();
            $response = $lastResponse->toArray(false);
            if ($statusCode < 200 || $statusCode >= 300 || true !== ($response['success'] ?? false)) {
                $message = (string) ($response['message'] ?? 'Unknown Mailketing API error.');
                throw new TransportException(sprintf('Mailketing API rejected the message (%d): %s', $statusCode, $message));
            }
        }

        return $lastResponse;
    }
}
