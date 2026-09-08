<?php

namespace Raju\EWSMail\Tests;

use Illuminate\Support\Facades\Http;
use Raju\EWSMail\Exceptions\ExchangeMailException;
use Raju\EWSMail\ExchangeMailServer;

class ExchangeMailServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://graph.microsoft.com/*' => Http::response(null, 202),
        ]);
    }

    public function test_legacy_helper_sends_through_graph(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ews');
        file_put_contents($path, 'csv');

        try {
            ExchangeMailServer::sendEmail(
                ['ada@example.com', 'grace@example.com'],
                [
                    'subject' => 'Mail From Package',
                    'body' => '<p>Message body</p>',
                    'bcc' => 'archive@example.com',
                    'replyTo' => 'help@example.com',
                ],
                ['boss@example.com'],
                [$path],
            );
        } finally {
            unlink($path);
        }

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $message = $request->data()['message'];

            return $message['subject'] === 'Mail From Package'
                && $message['body']['content'] === '<p>Message body</p>'
                && count($message['toRecipients']) === 2
                && $message['ccRecipients'][0]['emailAddress']['address'] === 'boss@example.com'
                && $message['bccRecipients'][0]['emailAddress']['address'] === 'archive@example.com'
                && $message['replyTo'][0]['emailAddress']['address'] === 'help@example.com'
                && isset($message['attachments'][0]);
        });
    }

    public function test_legacy_helper_requires_a_recipient(): void
    {
        $this->expectException(ExchangeMailException::class);
        $this->expectExceptionMessage('At least one recipient is required.');

        ExchangeMailServer::sendEmail([], ['subject' => 'x', 'body' => 'y']);
    }

    public function test_legacy_helper_rejects_missing_attachments(): void
    {
        $this->expectException(ExchangeMailException::class);
        $this->expectExceptionMessage('Attachment not found');

        ExchangeMailServer::sendEmail(
            'ada@example.com',
            ['subject' => 'x', 'body' => 'y'],
            [],
            ['/tmp/does-not-exist-ews-mail']
        );
    }
}
