# Mautic Mailketing API plugin

This plugin adds Mailketing API v2 as a Symfony Mailer transport for Mautic 5.
It replaces the SwiftMailer integration used by older Mautic releases.

## Requirements

- Mautic 5.0 or newer
- PHP 8.1 or newer
- A Mailketing API v2 token and an approved sender address

## Install

Copy the `MauticMailketingBundle` directory into the Mautic `plugins/` directory,
then run these commands from the Mautic installation root:

```bash
php bin/console cache:clear --env=prod
php bin/console mautic:plugins:reload --env=prod
```

If the plugin is installed with Composer, install its dependencies and run the
same cache and plugin reload commands.

## Configure email sending

In Mautic, open **Settings → Configuration → Email Settings** and select the
Mailketing API transport. Use the following values:

| Field | Value |
| --- | --- |
| Scheme | `mailketing+api` |
| Host | `default` |
| User | leave blank (the token may also be placed here) |
| Password | Mailketing API token |

The equivalent DSN is:

```text
mailketing+api://:YOUR_API_TOKEN@default
```

Set **Name to send mail as** and **E-mail address to send mail from** to an
approved Mailketing sender, save the settings, and click **Send test email**.

The token is an API token, not the SMTP password shown in Mailketing's SMTP
account settings. The transport sends one API request per recipient and uses
the HTML body when available (otherwise the text body).

## Bounce and unsubscribe callbacks

In Mailketing, configure the webhook URL as:

```text
https://YOUR-MAUTIC-DOMAIN/mailer/callback
```

The plugin listens for Mailketing's JSON `bounce` and `unsubscribe` events and
records them in Mautic's Do Not Contact list.

## Upgrade notes

This version is for Mautic 5 and does not load the old `Swiftmailer/` classes.
Do not copy the old Mautic 2/3 transport configuration into Mautic 5; use the
`mailketing+api` DSN above instead.

Licensed under GNU General Public License v3.0.
