# Laravel Exchange Mail (v2)

Send mail from Laravel through **Microsoft Graph**. This is the supported replacement for the v1 Exchange Web Services package, which used mailbox passwords and a SOAP client that Exchange Online is retiring.

- PHP 8.2+
- Laravel 11, 12, and 13
- Entra ID client credentials (`Mail.Send`)
- Works with `Mail::`, notifications, queues, and `Mail::fake()`
- `ExchangeMailServer::sendEmail()` still works, and is deprecated

Coming from 1.x? Read [UPGRADE.md](UPGRADE.md).

## Why Graph

Exchange Online **basic authentication** for EWS was disabled in 2022. EWS itself is phased out from **1 October 2026** and permanently retired on **1 April 2027**. Microsoft Graph is the API that remains.

## Install

```bash
composer require rajurayhan/laravel-ews-mail-server:^2.0
```

The service provider is auto-discovered. Publish config if you want a local copy:

```bash
php artisan vendor:publish --tag=exchange-mail
```

## Entra ID

1. Create an app registration in Microsoft Entra ID.
2. Add a client secret.
3. Grant the **Microsoft Graph** application permission `Mail.Send` and admin-consent it.
4. Put an [application access policy](https://learn.microsoft.com/en-us/graph/auth-limit-mailbox-access) on the app so it can send only from the mailboxes you intend.

## Environment

```env
MAIL_MAILER=microsoft-graph
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your App"

EXCHANGE_TENANT_ID=00000000-0000-0000-0000-000000000000
EXCHANGE_CLIENT_ID=00000000-0000-0000-0000-000000000000
EXCHANGE_CLIENT_SECRET=your-client-secret
EXCHANGE_FROM_ADDRESS=noreply@yourdomain.com
EXCHANGE_SAVE_TO_SENT=true
```

`EXCHANGE_FROM_ADDRESS` (or `MAIL_FROM_ADDRESS`) must be a mailbox in that tenant. The package registers a `microsoft-graph` mailer; you do not have to edit `config/mail.php`.

National clouds:

```env
EXCHANGE_GRAPH_URL=https://graph.microsoft.us/v1.0
EXCHANGE_TOKEN_URL=https://login.microsoftonline.us/YOUR_TENANT/oauth2/v2.0/token
```

## Usage

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceReady;

Mail::to('ada@example.com')
    ->cc('boss@example.com')
    ->send(new InvoiceReady($invoice));
```

Specify the mailer when it is not the default:

```php
Mail::mailer('microsoft-graph')
    ->to('ada@example.com')
    ->send(new InvoiceReady($invoice));
```

### Deprecated helper

```php
use Raju\EWSMail\ExchangeMailServer;

ExchangeMailServer::sendEmail(
    ['ada@example.com'],
    [
        'subject' => 'Invoice ready',
        'body' => '<p>Invoice 42 is ready.</p>',
        'bcc' => 'archive@example.com',
        'replyTo' => 'help@example.com',
    ],
    ['boss@example.com'],
    [storage_path('app/invoice-42.pdf')]
);
```

Failures throw `Raju\EWSMail\Exceptions\ExchangeMailException`. Missing Graph credentials throw `Raju\EWSMail\Exceptions\ConfigurationException`.

## Limits

`sendMail` in a single request is limited to about **3 MB**. Larger attachments are rejected with a clear exception. Upload sessions are not in 2.0.0.

On-premises Exchange Server does not speak Graph. This package no longer implements EWS.

## Testing your app

```php
Mail::fake();

Mail::to('ada@example.com')->send(new InvoiceReady($invoice));

Mail::assertSent(InvoiceReady::class);
```

## Development

```bash
composer install
vendor/bin/phpunit
```

## License

MIT. See [LICENSE](LICENSE).
