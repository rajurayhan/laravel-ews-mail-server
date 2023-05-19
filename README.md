# Intro
Big thanks to @rajurayhan and his package `rajurayhan/laravel-ews-mail-server`, which works great but was abandoned.
I needed to send multiple to: and cc: so I upgraded it a little bit to accept the to and (optional) cc values.
I didn't use the "To Name" anymore because it's not required and replaced it with and array and added also the array of optional CC!

_I will keep maintaining and updating this package with new features in the future. (like multi-account support)_

# Send Mail via Exchange Web Services!

## Sending Email via Microsoft Exchange Web Services (EWS) made easy! 

![image info](https://upload.wikimedia.org/wikipedia/commons/a/a0/Microsoft_Exchange_logo.svg)
## Installation

Install via Composer

    composer require klukiyan/laravel-ews-mail-server dev-master

Publish Configuration and Setup your Credentials

    php artisan vendor:publish --tag=ewsmailserver

## Usage
Simple! 
  
    use Raju\EWSMail\ExchangeMailServer;
    ExchangeMailServer::sendEmail(['user@email.com','user2@gmail.com','etc@etc'], ['subject' => 'Mail From Package', 'body' => 'Message Body'],['optional@cc.to']);

## Note     
 Update your credentials config/ews-mail-server.php 

