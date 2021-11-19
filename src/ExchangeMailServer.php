<?php

/*
 * This file is part of the Laravel Exchange Mail Server package.
 *
 * Copyright (c) 2021 Raju Rayhan
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Raju\EWSMail;

use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Request\CreateItemType;

use \jamesiarmes\PhpEws\ArrayType\ArrayOfRecipientsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType;

use \jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use \jamesiarmes\PhpEws\Enumeration\MessageDispositionType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;

use \jamesiarmes\PhpEws\Type\BodyType;
use \jamesiarmes\PhpEws\Type\EmailAddressType;
use \jamesiarmes\PhpEws\Type\MessageType;
use \jamesiarmes\PhpEws\Type\SingleRecipientType;
use \jamesiarmes\PhpEws\Request\SendItemType;

use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;

use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\TargetFolderIdType;

class ExchangeMailServer
{
    public static function sendEmail($receiverData, $messageData){

        $host       = config('ews-mail-server.host');
        $username   = config('ews-mail-server.username');
        $password   = config('ews-mail-server.password');
        $version    = config('ews-mail-server.version');

        $client = new Client($host, $username, $password, $version);

        $savedMessage = self::saveMessage($client, $receiverData, $messageData);

        if($savedMessage){
            self::sendMessage($client, $savedMessage);
        }

    }

    private static function saveMessage($client, $receiverData, $messageData){
        // Build the request,
        $username = config('ews-mail-server.username');
        $request = new CreateItemType();
        $request->Items = new NonEmptyArrayOfAllItemsType();

        // Save the message, but do not send it.
        $request->MessageDisposition = MessageDispositionType::SAVE_ONLY;

        // Create the message.
        $message = new MessageType();
        $message->Subject = $messageData['subject'];
        $message->ToRecipients = new ArrayOfRecipientsType();

        // Set the sender.
        $message->From = new SingleRecipientType();
        $message->From->Mailbox = new EmailAddressType();
        $message->From->Mailbox->EmailAddress = $username;

        // Set the recipient.
        $recipient = new EmailAddressType();
        $recipient->Name = $receiverData['name'];
        $recipient->EmailAddress = $receiverData['email'];
        $message->ToRecipients->Mailbox[] = $recipient;

        // Set the message body.
        $body = htmlentities($messageData['body']);
        $message->Body = new BodyType();
        $message->Body->BodyType = BodyTypeType::HTML;
        $message->Body->_ = $messageData['body'];

        // Add the message to the request.
        $request->Items->Message[] = $message;

        $response = $client->CreateItem($request);

        // Iterate over the results, printing any error messages or message ids.
        $response_messages = $response->ResponseMessages->CreateItemResponseMessage;
        $draftItems = [];
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                $code = $response_message->ResponseCode;
                $message = $response_message->MessageText;
                // fwrite(STDERR, "Message failed to create with \"$code: $message\"\n");
                continue;
            }

            // Iterate over the created messages, printing the id for each.
            foreach ($response_message->Items->Message as $item) {
                $output = '- Id: ' . $item->ItemId->Id . "\n";
                $output .= '- Change key: ' . $item->ItemId->ChangeKey . "\n";
                // fwrite(STDOUT, "Message created successfully.\n$output");
                $draftItems['id'] = $item->ItemId->Id;
                $draftItems['ChangeKey'] = $item->ItemId->ChangeKey;
            }
        }

        return $draftItems;
    }

    private static function sendMessage($client, $draftItems){
        $request = new SendItemType();
        $request->SaveItemToFolder = true;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        // Add the message to the request.
        $item = new ItemIdType();
        $item->Id = $draftItems['id'];
        $item->ChangeKey = $draftItems['ChangeKey'];
        $request->ItemIds->ItemId[] = $item;

        // Configure the folder to save the sent message to.
        $send_folder = new TargetFolderIdType();
        $send_folder->DistinguishedFolderId = new DistinguishedFolderIdType();
        $send_folder->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::SENT;
        $request->SavedItemFolderId = $send_folder;

        $response = $client->SendItem($request);

        // Iterate over the results, printing any error messages.
        $response_messages = $response->ResponseMessages->SendItemResponseMessage;
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                $code = $response_message->ResponseCode;
                $message = $response_message->MessageText;
                continue;
            }

        }

        return true;
    }
}
