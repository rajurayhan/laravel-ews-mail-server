<?php

namespace Raju\EWSMail\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Raju\EWSMail\Exceptions\ExchangeMailException;

class MicrosoftGraphTransportTest extends TestCase
{
    protected function fakeSuccessfulGraph(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth2/')) {
                return Http::response([
                    'access_token' => 'test-token',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(null, 202);
        });
    }

    public function test_it_sends_html_mail_through_graph(): void
    {
        $this->fakeSuccessfulGraph();

        Mail::mailer('microsoft-graph')->html(
            '<p>Invoice 42 is ready.</p>',
            function ($message) {
                $message->to('ada@example.com', 'Ada')
                    ->cc('boss@example.com')
                    ->bcc('archive@example.com')
                    ->replyTo('help@example.com')
                    ->subject('Invoice ready');
            }
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/users/noreply%40example.com/sendMail')) {
                return false;
            }

            $payload = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-token')
                && $payload['saveToSentItems'] === true
                && $payload['message']['subject'] === 'Invoice ready'
                && $payload['message']['body']['contentType'] === 'HTML'
                && $payload['message']['body']['content'] === '<p>Invoice 42 is ready.</p>'
                && $payload['message']['toRecipients'][0]['emailAddress']['address'] === 'ada@example.com'
                && $payload['message']['toRecipients'][0]['emailAddress']['name'] === 'Ada'
                && $payload['message']['ccRecipients'][0]['emailAddress']['address'] === 'boss@example.com'
                && $payload['message']['bccRecipients'][0]['emailAddress']['address'] === 'archive@example.com'
                && $payload['message']['replyTo'][0]['emailAddress']['address'] === 'help@example.com'
                && $payload['message']['from']['emailAddress']['address'] === 'noreply@example.com';
        });
    }

    public function test_it_uses_the_message_from_address_as_the_graph_mailbox(): void
    {
        $this->fakeSuccessfulGraph();

        Mail::mailer('microsoft-graph')->html('Hello', function ($message) {
            $message->from('billing@example.com', 'Billing')
                ->to('ada@example.com')
                ->subject('From override');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/users/billing%40example.com/sendMail')
                && $request->data()['message']['from']['emailAddress']['name'] === 'Billing';
        });
    }

    public function test_it_attaches_local_files(): void
    {
        $this->fakeSuccessfulGraph();

        $path = tempnam(sys_get_temp_dir(), 'ews');
        file_put_contents($path, 'attachment-body');

        try {
            Mail::mailer('microsoft-graph')->html('See attached', function ($message) use ($path) {
                $message->to('ada@example.com')
                    ->subject('File')
                    ->attach($path, ['as' => 'notes.txt', 'mime' => 'text/plain']);
            });
        } finally {
            unlink($path);
        }

        Http::assertSent(function ($request) {
            $attachment = $request->data()['message']['attachments'][0] ?? null;

            return is_array($attachment)
                && $attachment['@odata.type'] === '#microsoft.graph.fileAttachment'
                && $attachment['name'] === 'notes.txt'
                && $attachment['contentBytes'] === base64_encode('attachment-body')
                && $attachment['isInline'] === false;
        });
    }

    public function test_it_throws_when_graph_rejects_the_message(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth2/')) {
                return Http::response([
                    'access_token' => 'test-token',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['error' => ['message' => 'Mailbox not found']], 404);
        });

        $this->expectException(ExchangeMailException::class);
        $this->expectExceptionMessage('Microsoft Graph sendMail failed');

        Mail::mailer('microsoft-graph')->html('Hello', function ($message) {
            $message->to('ada@example.com')->subject('Nope');
        });
    }
}
