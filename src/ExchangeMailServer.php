<?php

namespace Raju\EWSMail;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Raju\EWSMail\Exceptions\ExchangeMailException;

class ExchangeMailServer
{
    /**
     * Send a message through the configured Microsoft Graph mailer.
     *
     * @param  string|list<string>  $recs
     * @param  array{subject?: string, body?: string, from?: string, replyTo?: string|list<string>, bcc?: string|list<string>}  $messageData
     * @param  string|list<string>  $cc
     * @param  list<string>  $attachments  Absolute file paths
     *
     * @deprecated 2.0.0 Use Laravel's Mail facade with the microsoft-graph mailer.
     */
    public static function sendEmail($recs, $messageData, $cc = [], $attachments = []): void
    {
        trigger_deprecation(
            'rajurayhan/laravel-ews-mail-server',
            '2.0.0',
            'ExchangeMailServer::sendEmail() is deprecated. Use Mail::mailer(\'microsoft-graph\') with a Mailable instead.'
        );

        $recipients = array_values(array_filter((array) $recs, fn ($address) => is_string($address) && $address !== ''));
        if ($recipients === []) {
            throw new ExchangeMailException('At least one recipient is required.');
        }

        $subject = (string) ($messageData['subject'] ?? '');
        $body = (string) ($messageData['body'] ?? '');
        $cc = array_values(array_filter((array) $cc, fn ($address) => is_string($address) && $address !== ''));
        $attachments = is_array($attachments) ? $attachments : [];

        Mail::mailer(config('exchange-mail.mailer', 'microsoft-graph'))
            ->html($body, function (Message $message) use ($recipients, $subject, $cc, $attachments, $messageData) {
                $message->to($recipients)->subject($subject);

                if ($cc !== []) {
                    $message->cc($cc);
                }

                if (! empty($messageData['bcc'])) {
                    $message->bcc((array) $messageData['bcc']);
                }

                if (! empty($messageData['from'])) {
                    $message->from($messageData['from']);
                }

                if (! empty($messageData['replyTo'])) {
                    $message->replyTo($messageData['replyTo']);
                }

                foreach ($attachments as $path) {
                    if (! is_string($path) || ! is_file($path)) {
                        throw new ExchangeMailException('Attachment not found: '.(is_string($path) ? $path : get_debug_type($path)));
                    }

                    $message->attach($path);
                }
            });
    }
}
