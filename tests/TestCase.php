<?php

namespace Raju\EWSMail\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Raju\EWSMail\ExchangeMailServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ExchangeMailServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'microsoft-graph');
        $app['config']->set('mail.from.address', 'noreply@example.com');
        $app['config']->set('mail.from.name', 'Example');
        $app['config']->set('exchange-mail.graph', [
            'tenant_id' => 'tenant-id',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'from' => 'noreply@example.com',
            'save_to_sent_items' => true,
            'api_base_url' => 'https://graph.microsoft.com/v1.0',
            'token_url' => null,
        ]);
    }
}
