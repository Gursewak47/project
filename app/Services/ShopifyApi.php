<?php

namespace App\Services;

use App\Contracts\ServiceProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ShopifyApi implements ServiceProviderContract
{
    private string $baseUrl
    ;
    private string $shop_url;

    private string $apiKey;

    private string $password;

    public function __construct()
    {
        $this->apiKey     = 'ddf14d440fb985e6c1310a0dbdf0bfaf';
        $this->password = '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';
        $this->shop_url      = 'fuelpumps-com.myshopify.com';
    }

    public function valid()
    {
        return true;
    }

    private function getHeaders()
    {
        return [
            'Authorization'         => 'Basic '.base64_encode($this->apiKey.':'.$this->password),
            'Content-Type'                => 'application/json',
        ];
    }

    public function client()
    {
        return Http::retry(3, (10 * 1000))
        ->withHeaders(array_merge($this->getHeaders(), [
            'X-Shopify-Access-Token' => $this->token(),
        ]));
    }

    public function request(string $method, string $uri, array $parameters)
    {
        return $this->client()->{$method}($this->baseUrl.$uri, $parameters);
    }

    public function token()
    {
        return Http::retry(3, (10 * 1000))
        ->withHeaders($this->getHeaders())
            ->asForm()
            ->post($this->baseUrl.'/v3/token', [
                'grant_type' => 'client_credentials',
            ])->json()['access_token'];
    }
}
