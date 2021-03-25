# Send Mail via Exchange Web Services!

## Sending Email via Microsoft Exchange Web Services (EWS) made easy! 

![image info](https://upload.wikimedia.org/wikipedia/commons/a/a0/Microsoft_Exchange_logo.svg)
## Installation

Install via Composer

    composer require rajurayhan/laravel-ews-mail-server dev-master

Publish Configuration and Setup your Credentials

    php artisan vendor:publish --tag=ewsmailserver

## Usage
Simple! 
  
    use Raju\EWSMail\ExchangeMailServer;
    ExchangeMailServer::sendEmail(['name' => 'Raju at LHG', 'email' => 'raju@lhgraphics.com'], ['subject' => 'Mail From Package', 'body' => 'Message Body']);

## Note     
 Update your credentials config/ews-mail-server.php 
 
## Find Me
	Email: devraju.bd@gmail.com 