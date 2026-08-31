# Mautic Mailketing API plugin

Mailketing API v2 transport for **Mautic 5.2.11**. This package uses Symfony
Mailer 5.4 and replaces the SwiftMailer integration used by older Mautic
releases.

## Requirements

- Mautic 5.2.11
- PHP 8.1 or newer
- A Mailketing API v2 token and an approved sender address

## Install

Remove or rename the previous `plugins/MauticMailketingBundle` directory, copy
the new `MauticMailketingBundle` directory into `plugins/`, then run these
commands from the Mautic installation root:

```bash
php bin/console cache:clear --env=prod
php bin/console mautic:plugins:reload --env=prod
php bin/console cache:clear --env=prod
```

If the plugin is installed with Composer, install its dependencies and run the
same cache and plugin reload commands.

## Configure email sending

In Mautic, open **Settings → Configuration → Email Settings**. Enter these DSN
fields:

| Field | Value |
| --- | --- |
| Scheme | `mailketing+api` |
| Host | `default` |
| Port | leave blank |
| Path | leave blank |
| User | Mailketing API token |
| Password | leave blank |

The equivalent DSN is:

```text
mailketing+api://YOUR_API_TOKEN@default
```

Use the configuration form when possible because Mautic URL-encodes special
characters in the token. For backwards compatibility, the plugin also accepts
the token in the Password field.

Set **Name to send mail as** and **E-mail address to send mail from** to an
approved Mailketing sender, save the settings, and click **Send test email**.

The token is an API token from **Mailketing → API Integration**, not an SMTP
password. The transport sends one API v2 request per recipient and uses the
HTML body when available (otherwise the text body). Sending each recipient
separately is required so Mautic contact tokens remain personalized.

Mailketing API v2 accepts attachments only by public URL. Because ordinary
Mautic attachments are local files, this plugin rejects messages with
attachments instead of silently dropping them.

## Bounce and unsubscribe callbacks

In Mailketing, configure the webhook URL as:

```text
https://YOUR-MAUTIC-DOMAIN/mailer/callback
```

Use this URL for Mailketing's **Bounce** and **Unsubscribe** webhook fields. The
plugin records both event types in Mautic's Do Not Contact list. The old route
`/mailer/mailketing_api/callback` belongs to pre-Mautic-5 integrations and must
not be used here.

## Verify installation

Confirm that the factory is registered:

```bash
php bin/console debug:container --tag=mailer.transport_factory | grep -i mailketing
```

Then save the Email Settings and click **Send test email**. If it fails, inspect
the current production log under `var/logs/`; API errors now include the HTTP
status and Mailketing response message.

## Upgrade notes

This version is built specifically for Mautic 5.2.11 and Symfony 5.4. It does
not load the old `Swiftmailer/` classes. Do not copy the Mautic 2/3/4 transport
configuration into Mautic 5; use the `mailketing+api` DSN above.

Licensed under GNU General Public License v3.0.
