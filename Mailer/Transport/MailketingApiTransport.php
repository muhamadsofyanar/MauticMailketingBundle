<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMailketingBundle\Mailer\Transport;

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

/**
 * Mailketing API v2 transport for Mautic 5 / Symfony Mailer.
 *
 * This transport intentionally does not implement Mautic's
 * TokenTransportInterface. That interface is reserved for providers that can
 * perform per-recipient token replacement inside a batch request. Mailketing's
 * /send endpoint accepts one recipient per request, so Mautic must render and
 * send each contact's message separately to preserve personalization.
 */
final class MailketingApiTransport extends AbstractApiTransport
{
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

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $recipients = $this->getUniqueRecipients($envelope);
        if ([] === $recipients) {
            throw new TransportException('Mailketing requires at least one recipient.');
        }

        $from = $email->getFrom()[0] ?? null;
        if (!$from instanceof Address) {
            throw new TransportException('Mailketing requires a sender address.');
        }

        $subject = trim((string) $email->getSubject());
        if ('' === $subject) {
            throw new TransportException('Mailketing requires an email subject.');
        }

        $html    = $email->getHtmlBody();
        $text    = $email->getTextBody();
        $content = null !== $html && '' !== trim($html) ? $html : $text;
        if (null === $content || '' === trim($content)) {
            throw new TransportException('Mailketing requires email content.');
        }

        if ([] !== $email->getAttachments()) {
            throw new TransportException(
                'Mailketing API v2 accepts attachments only as public URLs; local Mautic attachments cannot be sent safely.'
            );
        }

        $lastResponse = null;
        foreach ($recipients as $recipient) {
            $payload = [
                'from_name'  => $from->getName() ?: $from->getAddress(),
                'from_email' => $from->getAddress(),
                'recipient'  => $recipient->getAddress(),
                'subject'    => $subject,
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
            $rawBody    = $lastResponse->getContent(false);
            $response   = json_decode($rawBody, true);
            $response   = is_array($response) ? $response : [];

            if ($statusCode < 200 || $statusCode >= 300 || true !== ($response['success'] ?? false)) {
                $message = $this->getApiErrorMessage($response, $rawBody);
                throw new TransportException(sprintf('Mailketing API rejected the message (%d): %s', $statusCode, $message));
            }
        }

        if (!$lastResponse instanceof ResponseInterface) {
            throw new TransportException('Mailketing did not return a response.');
        }

        return $lastResponse;
    }

    /**
     * @return list<Address>
     */
    private function getUniqueRecipients(Envelope $envelope): array
    {
        $unique = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $unique[strtolower($recipient->getAddress())] = $recipient;
        }

        return array_values($unique);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function getApiErrorMessage(array $response, string $rawBody): string
    {
        $message = $response['message'] ?? null;
        if (is_string($message) && '' !== trim($message)) {
            return trim($message);
        }

        $errors = $response['errors'] ?? null;
        if (is_array($errors) && [] !== $errors) {
            $encoded = json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($encoded)) {
                return $encoded;
            }
        }

        $plainBody = trim(strip_tags($rawBody));
        if ('' !== $plainBody) {
            return substr($plainBody, 0, 500);
        }

        return 'Unknown Mailketing API error.';
    }
}
