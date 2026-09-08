<?php

namespace Raju\EWSMail\Graph;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Raju\EWSMail\Exceptions\ConfigurationException;
use Raju\EWSMail\Exceptions\ExchangeMailException;

class GraphTokenClient
{
    public function __construct(
        protected string $tenantId,
        protected string $clientId,
        protected string $clientSecret,
        protected ?string $tokenUrl = null,
    ) {
    }

    public function token(): string
    {
        $this->assertConfigured();

        $cacheKey = 'exchange-mail.graph.token.'.hash('sha256', $this->tenantId.'|'.$this->clientId);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post($this->endpoint(), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

        if ($response->failed()) {
            throw new ExchangeMailException(
                'Failed to obtain a Microsoft Graph access token (HTTP '.$response->status().'): '.$response->body()
            );
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new ExchangeMailException('Microsoft Graph token response did not include access_token.');
        }

        $ttl = max(30, ((int) $response->json('expires_in', 3600)) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function endpoint(): string
    {
        if (is_string($this->tokenUrl) && $this->tokenUrl !== '') {
            return $this->tokenUrl;
        }

        return 'https://login.microsoftonline.com/'.$this->tenantId.'/oauth2/v2.0/token';
    }

    protected function assertConfigured(): void
    {
        if ($this->tenantId === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new ConfigurationException(
                'Set EXCHANGE_TENANT_ID, EXCHANGE_CLIENT_ID, and EXCHANGE_CLIENT_SECRET to send mail with Microsoft Graph.'
            );
        }
    }
}
