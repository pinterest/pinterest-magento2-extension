<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Laminas\Http\Client;
use Laminas\Http\Client\Exception\RuntimeException;
use Laminas\Http\Request;
use Laminas\Uri\Http;

/*
 * The purpose of this client is to allow for bearer authentication. The existing Laminas client adds
 * basic authentication to the Authorization header before a request is sent.
 * See https://docs.laminas.dev/laminas-http/client/advanced/#http-authentication
 */

class PinterestLaminasClient extends Client
{
    /**
     * @var string
     */
    protected $authorizationValue;

    /**
     * Constructor -- for now we need to pass in auth explicitly
     *
     * @param string $authorizationValue
     * @param string $uri
     * @param array|Traversable $options
     */
    public function __construct($authorizationValue = null, $uri = null, $options = null)
    {
        parent::__construct($uri, $options);
        $this->authorizationValue = $authorizationValue;
    }


    /**
     * Prepare the request headers
     *
     * @param resource|string $body
     * @param Http $uri
     * @return array
     * @throws Exception\RuntimeException
     */
    protected function prepareHeaders($body, $uri)
    {
        $headers = parent::prepareHeaders($body, $uri);
        // New auth header will be added in some cases by parent function
        if (array_key_exists("Authorization", $headers)) {
            $authHeader = $headers['Authorization'];
            $headers->removeHeader($authHeader); // remove that header so we can overwrite
        }
        if ($this->authorizationValue) {
            $headers['Authorization'] = $this->authorizationValue;
        }
        return $headers;
    }
}
