# Send Mail via Exchange Web Services!

## Sending Email via Microsoft Exchange Web Services (EWS) made easy! 

![image info](https://upload.wikimedia.org/wikipedia/commons/a/a0/Microsoft_Exchange_logo.svg)
## Installation

Install via Composer

    composer require rajurayhan/laravel-ews-mail-server

Publish Configuration and Setup your Credentials

    php artisan vendor:publish --tag=ewsmailserver

## Usage
Simple! 
  
    use Raju\EWSMail\ExchangeMailServer;
    ExchangeMailServer::sendEmail(['user@email.com','user2@gmail.com','etc@etc'], ['subject' => 'Mail From Package', 'body' => 'Message Body'],['optional@cc.to'],[optional $attachment_paths]);

## Note     
 Update your credentials config/ews-mail-server.php 

