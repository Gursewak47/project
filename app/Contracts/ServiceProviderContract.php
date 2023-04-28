<?php

namespace App\Contracts;

use Illuminate\Support\Facades\Http;

interface ServiceProviderContract
{
    /**
     * Determine if the provider credentials are valid.
     *
     * @return bool
     */
    public function valid();

    /**
     * @return Http
     */
    public function client();

    /**
     * Make the request third party service.
     *
     * @param string $method
     * @param string $path
     * @param array $parameters
     * @return mixed
     */
    public function request(string $method, string $path, array $parameters);

    /**
     * Make the access token for third party services.
     *
     * @return mixed
     */
    public function token();
}
