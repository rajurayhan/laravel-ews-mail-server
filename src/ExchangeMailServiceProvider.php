<?php

namespace Raju\EWSMail;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Raju\EWSMail\Graph\GraphTokenClient;
use Raju\EWSMail\Transport\MicrosoftGraphTransport;

class ExchangeMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/exchange-mail.php', 'exchange-mail');

        $this->app->singleton(GraphTokenClient::class, function ($app) {
            $graph = $app['config']->get('exchange-mail.graph', []);

            return new GraphTokenClient(
                tenantId: (string) ($graph['tenant_id'] ?? ''),
                clientId: (string) ($graph['client_id'] ?? ''),
                clientSecret: (string) ($graph['client_secret'] ?? ''),
                tokenUrl: isset($graph['token_url']) ? (string) $graph['token_url'] : null,
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/exchange-mail.php' => config_path('exchange-mail.php'),
        ], 'exchange-mail');

        $this->publishes([
            __DIR__.'/../config/exchange-mail.php' => config_path('exchange-mail.php'),
        ], 'ewsmailserver');

        $this->registerMailer();
    }

    protected function registerMailer(): void
    {
        $config = $this->app->make('config');
        $existing = $config->get('mail.mailers.microsoft-graph', []);
        $config->set('mail.mailers.microsoft-graph', array_merge([
            'transport' => 'microsoft-graph',
        ], is_array($existing) ? $existing : []));

        Mail::extend('microsoft-graph', function () {
            $graph = config('exchange-mail.graph', []);

            return new MicrosoftGraphTransport(
                tokens: $this->app->make(GraphTokenClient::class),
                fromAddress: (string) ($graph['from'] ?: config('mail.from.address') ?: ''),
                saveToSentItems: (bool) ($graph['save_to_sent_items'] ?? true),
                apiBaseUrl: (string) ($graph['api_base_url'] ?? 'https://graph.microsoft.com/v1.0'),
            );
        });
    }
}
