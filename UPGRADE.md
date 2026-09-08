# Upgrade from 1.x to 2.0

v2 is a protocol change, not a bump of the old EWS client.

| 1.x | 2.0 |
| --- | --- |
| Exchange Web Services SOAP | Microsoft Graph `POST /users/{mailbox}/sendMail` |
| Mailbox username + password | Entra ID app: tenant, client ID, client secret |
| `php-ews/php-ews` | Laravel HTTP client |
| Config published or nothing works | Config is merged automatically |
| Failures logged with `info()` | Failures throw `Raju\\EWSMail\\Exceptions\\ExchangeMailException` |
| PHP / Laravel unconstrained | PHP 8.2+, Laravel 11–13 |

Exchange Online basic auth for EWS was turned off in 2022. EWS itself is phased out from **1 October 2026** and retired on **1 April 2027**. v2 is the supported path for Microsoft 365.

On-premises Exchange Server is not a Graph target. If you only have on-prem EWS, stay on 1.x or run a local SMTP relay.

## 1. Install 2.0

```bash
composer require rajurayhan/laravel-ews-mail-server:^2.0
```

If Packagist still points at 1.x, require the GitHub repo:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/rajurayhan/laravel-ews-mail-server.git"
        }
    ]
}
```

## 2. Register an Entra app

1. Azure Portal → Microsoft Entra ID → App registrations → New registration.
2. Certificates & secrets → New client secret.
3. API permissions → Add **Microsoft Graph** application permission `Mail.Send` → Grant admin consent.
4. Restrict senders with an Exchange [application access policy](https://learn.microsoft.com/en-us/graph/auth-limit-mailbox-access) so the app cannot mail-as every mailbox.

## 3. Replace config

Remove `config/ews-mail-server.php` if you published it.

```bash
php artisan vendor:publish --tag=exchange-mail
```

```env
MAIL_MAILER=microsoft-graph
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your App"

EXCHANGE_TENANT_ID=
EXCHANGE_CLIENT_ID=
EXCHANGE_CLIENT_SECRET=
EXCHANGE_FROM_ADDRESS=noreply@yourdomain.com
EXCHANGE_SAVE_TO_SENT=true
```

`EXCHANGE_FROM_ADDRESS` must be a real mailbox in the tenant.

## 4. Update call sites

The old helper still sends, but it is deprecated:

```php
use Raju\EWSMail\ExchangeMailServer;

ExchangeMailServer::sendEmail(
    ['user@email.com'],
    ['subject' => 'Mail From Package', 'body' => 'Message Body'],
    ['optional@cc.to'],
    [$attachmentPath]
);
```

Prefer a Mailable:

```php
use Illuminate\Support\Facades\Mail;

Mail::mailer('microsoft-graph')
    ->to('user@email.com')
    ->cc('optional@cc.to')
    ->send(new OrderShipped($order));
```

Or, if `MAIL_MAILER=microsoft-graph`, drop `mailer()`:

```php
Mail::to('user@email.com')->send(new OrderShipped($order));
```

v1 swallowed errors. Wrap sends that used to ignore failures, or let the exception reach your queue worker.

## 5. Limits

Direct `sendMail` attachments must stay under about **3 MB** total. Larger files need a draft + upload session, which is not in 2.0.0.
