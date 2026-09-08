<?php

namespace Raju\EWSMail\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Raju\EWSMail\Exceptions\ConfigurationException;
use Raju\EWSMail\Exceptions\ExchangeMailException;
use Raju\EWSMail\Graph\GraphTokenClient;

class GraphTokenClientTest extends TestCase
{
    public function test_it_requests_and_caches_a_token(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response([
                'access_token' => 'token-1',
                'expires_in' => 3600,
            ], 200),
        ]);

        $client = $this->app->make(GraphTokenClient::class);

        $this->assertSame('token-1', $client->token());
        $this->assertSame('token-1', $client->token());

        Http::assertSentCount(1);
        $this->assertTrue(Cache::has('exchange-mail.graph.token.'.hash('sha256', 'tenant-id|client-id')));
    }

    public function test_it_uses_a_custom_token_url(): void
    {
        config(['exchange-mail.graph.token_url' => 'https://login.microsoftonline.us/tenant-id/oauth2/v2.0/token']);
        $this->app->forgetInstance(GraphTokenClient::class);

        Http::fake([
            'https://login.microsoftonline.us/*' => Http::response([
                'access_token' => 'gov-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->assertSame('gov-token', $this->app->make(GraphTokenClient::class)->token());
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->expectException(ConfigurationException::class);

        (new GraphTokenClient('', '', ''))->token();
    }

    public function test_it_rejects_a_failed_token_response(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(ExchangeMailException::class);
        $this->expectExceptionMessage('Failed to obtain a Microsoft Graph access token');

        $this->app->make(GraphTokenClient::class)->token();
    }
}
