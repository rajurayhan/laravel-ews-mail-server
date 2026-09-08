<?php

namespace Raju\EWSMail\Transport;

use Illuminate\Support\Facades\Http;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Raju\EWSMail\Exceptions\ConfigurationException;
use Raju\EWSMail\Exceptions\ExchangeMailException;
use Raju\EWSMail\Graph\GraphTokenClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class MicrosoftGraphTransport extends AbstractTransport
{
    public const SIMPLE_SEND_LIMIT = 3_000_000;

    public function __construct(
        protected GraphTokenClient $tokens,
        protected string $fromAddress,
        protected bool $saveToSentItems = true,
        protected string $apiBaseUrl = 'https://graph.microsoft.com/v1.0',
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $mailbox = $this->mailbox($email, $message);
        $attachments = $this->attachments($email);

        $payload = [
            'message' => [
                'subject' => $email->getSubject() ?? '',
                'body' => [
                    'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                    'content' => $this->body($email),
                ],
                'toRecipients' => $this->mapAddresses($email->getTo()),
                'ccRecipients' => $this->mapAddresses($email->getCc()),
                'bccRecipients' => $this->mapAddresses($email->getBcc()),
                'replyTo' => $this->mapAddresses($email->getReplyTo()),
                'from' => $this->mapAddress(new Address($mailbox, $this->fromName($email))),
            ],
            'saveToSentItems' => $this->saveToSentItems,
        ];

        if ($attachments !== []) {
            $payload['message']['attachments'] = $attachments;
        }

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > self::SIMPLE_SEND_LIMIT) {
            throw new ExchangeMailException(
                'This message is larger than 3 MB. Microsoft Graph sendMail cannot accept it in a single request. Split the attachments or wait for upload-session support.'
            );
        }

        $url = rtrim($this->apiBaseUrl, '/').'/users/'.rawurlencode($mailbox).'/sendMail';

        $response = Http::withToken($this->tokens->token())
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw new ExchangeMailException(
                'Microsoft Graph sendMail failed (HTTP '.$response->status().'): '.$response->body()
            );
        }
    }

    protected function mailbox(Email $email, SentMessage $message): string
    {
        $from = $message->getEnvelope()->getSender()->getAddress()
            ?: ($email->getFrom()[0] ?? null)?->getAddress()
            ?: $this->fromAddress;

        if ($from === '') {
            throw new ConfigurationException(
                'Set EXCHANGE_FROM_ADDRESS or MAIL_FROM_ADDRESS to a mailbox that exists in your Microsoft 365 tenant.'
            );
        }

        return $from;
    }

    protected function fromName(Email $email): string
    {
        return ($email->getFrom()[0] ?? null)?->getName() ?: '';
    }

    protected function body(Email $email): string
    {
        $html = $email->getHtmlBody();
        if (is_string($html)) {
            return $html;
        }

        $text = $email->getTextBody();

        return is_string($text) ? $text : '';
    }

    /**
     * @param  Address[]  $addresses
     * @return list<array{emailAddress: array{address: string, name?: string}}>
     */
    protected function mapAddresses(array $addresses): array
    {
        return array_values(array_map($this->mapAddress(...), $addresses));
    }

    /**
     * @return array{emailAddress: array{address: string, name?: string}}
     */
    protected function mapAddress(Address $address): array
    {
        $email = ['address' => $address->getAddress()];
        if ($address->getName() !== '') {
            $email['name'] = $address->getName();
        }

        return ['emailAddress' => $email];
    }

    /**
     * @return list<array{0?: mixed, @odata.type: string, name: string, contentType: string, contentBytes: string, isInline: bool, contentId?: string}>
     */
    protected function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $part) {
            $headers = $part->getPreparedHeaders();
            $name = $headers->getHeaderParameter('Content-Disposition', 'filename')
                ?: $part->getFilename()
                ?: 'attachment';
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $body = $part->getBody();

            $attachment = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $name,
                'contentType' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'contentBytes' => base64_encode($body),
                'isInline' => $disposition === 'inline',
            ];

            if ($headers->has('Content-ID')) {
                $contentId = $headers->get('Content-ID')?->getBody();
                if (is_array($contentId)) {
                    $contentId = $contentId[0] ?? null;
                }
                if (is_string($contentId) && $contentId !== '') {
                    $attachment['contentId'] = $contentId;
                }
            }

            $attachments[] = $attachment;
        }

        return $attachments;
    }
}
