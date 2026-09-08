<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel mailer name
    |--------------------------------------------------------------------------
    |
    | The mailer registered by this package. Point MAIL_MAILER at this value
    | or pass it to Mail::mailer().
    |
    */
    'mailer' => env('EXCHANGE_MAIL_MAILER', 'microsoft-graph'),

    'graph' => [
        'tenant_id' => env('EXCHANGE_TENANT_ID'),
        'client_id' => env('EXCHANGE_CLIENT_ID'),
        'client_secret' => env('EXCHANGE_CLIENT_SECRET'),

        /*
        | The mailbox Graph sends as. Must exist in the tenant. Falls back to
        | MAIL_FROM_ADDRESS when empty.
        */
        'from' => env('EXCHANGE_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),

        'save_to_sent_items' => env('EXCHANGE_SAVE_TO_SENT', true),

        'api_base_url' => env('EXCHANGE_GRAPH_URL', 'https://graph.microsoft.com/v1.0'),

        /*
        | Override the token endpoint for national clouds. Default:
        | https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
        */
        'token_url' => env('EXCHANGE_TOKEN_URL'),
    ],
];
