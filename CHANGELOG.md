# Changelog

## 5.2.11.1

- Targets Mautic 5.2.11 and its Symfony Mailer/HttpClient 5.4 stack.
- Removes the incomplete `TokenTransportInterface` implementation that caused
  the transport class to fail loading because `getMaxBatchLimit()` was absent.
- Uses one rendered Mautic message per recipient so contact personalization is
  preserved when calling Mailketing's single-recipient `/api/v2/send` endpoint.
- Registers the Symfony Mailer transport factory and webhook subscriber without
  duplicate subscriber tags.
- Accepts the API token in the standard DSN User field and keeps Password-field
  compatibility for upgrades.
- Validates sender, recipient, subject, body, attachments, HTTP status, and the
  Mailketing API v2 `success` response.
- Reports Mailketing validation and API errors more clearly.
- Documents the Mautic 5 callback route `/mailer/callback`.
