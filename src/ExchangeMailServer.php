<?php

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
use \jamesiarmes\PhpEws\Type\FileAttachmentType;
use \jamesiarmes\PhpEws\Type\SingleRecipientType;
use \jamesiarmes\PhpEws\Request\SendItemType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\TargetFolderIdType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAttachmentsType;
use Illuminate\Support\Facades\Storage;

class ExchangeMailServer {
  public static function sendEmail($recs, $messageData, $cc = [], $attachments = []) {
    $host       = config('ews-mail-server.host');
    $username   = config('ews-mail-server.username');
    $password   = config('ews-mail-server.password');
    $version    = config('ews-mail-server.version');

    $client = new Client($host, $username, $password, $version, [
        'curlopts' => [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ],
    ]);


    $savedMessage = self::saveMessage($client, $recs, $messageData, $cc, $attachments);

    if ($savedMessage) {
      self::sendMessage($client, $savedMessage);
    }
  }

  private static function saveMessage($client, $recs, $messageData, $cc = [], $attachments = []) {
    // Build the request
    $username = config('ews-mail-server.username');
    $request = new CreateItemType();
    $request->Items = new NonEmptyArrayOfAllItemsType();

    // Save the message, but do not send it.
    $request->MessageDisposition = MessageDispositionType::SAVE_ONLY;

    // Create the message.
    $message = new MessageType();
    $message->Subject = $messageData['subject'];
    $message->ToRecipients = new ArrayOfRecipientsType();
    $message->CcRecipients = new ArrayOfRecipientsType();

    // Set the sender.
    $message->From = new SingleRecipientType();
    $message->From->Mailbox = new EmailAddressType();
    $message->From->Mailbox->EmailAddress = $username;

    // Set the recipients.
    foreach ($recs as $rec) {
      $recipient = new EmailAddressType();
      $recipient->EmailAddress = $rec;
      $message->ToRecipients->Mailbox[] = $recipient;
    }
    foreach ($cc as $ccRec) {
      $recipient = new EmailAddressType();
      $recipient->EmailAddress = $ccRec;
      $message->CcRecipients->Mailbox[] = $recipient;
    }

    // Set the message body.
    $bodyContent = htmlentities($messageData['body']);
    $message->Body = new BodyType();
    $message->Body->BodyType = BodyTypeType::HTML;
    $message->Body->_ = html_entity_decode($bodyContent);

    // Add attachments if provided.
    // Inside the saveMessage method, in the attachments loop:
    if (!empty($attachments)) {
      $message->Attachments = new NonEmptyArrayOfAttachmentsType();
      
      foreach ($attachments as $attachmentPath) {
          info('starting the attachment');
          if (file_exists($attachmentPath)) {
              $attachment = new FileAttachmentType();
              
              // Set the file name
              $attachment->Name = basename($attachmentPath);
              
              // Set the MIME type
              $finfo = finfo_open(FILEINFO_MIME_TYPE);
              $attachment->ContentType = finfo_file($finfo, $attachmentPath);
              finfo_close($finfo);
              
              // Read and encode content
              $fileContent = file_get_contents($attachmentPath);
              if ($fileContent === false) {
                  info("Failed to read file: " . $attachmentPath);
                  continue;
              } else {
              }
              
              // Ensure proper encoding
              $attachment->Content = $fileContent;
              $attachment->IsInline = false;
              
              // Add to attachments
              $message->Attachments->FileAttachment[] = $attachment;
          } else {
              info("File does not exist: " . $attachmentPath);
          }
      }
    }



    // Add the message to the request.
    $request->Items->Message[] = $message;

    // Send the request to create the item (save it as a draft).
    try {
      $response = $client->CreateItem($request);
      // Process response messages.
      foreach ($response->ResponseMessages->CreateItemResponseMessage as $response_message) {
        if ($response_message->ResponseClass == ResponseClassType::SUCCESS) {
          foreach ($response_message->Items->Message as $item) {
            return [
              'id' => $item->ItemId->Id,
              'ChangeKey' => $item->ItemId->ChangeKey,
            ];
          }
        }
      }
    } catch (\Exception $e) {
      info("Failed to save email: " . $e->getMessage());
      return null;
    }

    return null;
  }

  private static function sendMessage($client, $draftItems) {
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
