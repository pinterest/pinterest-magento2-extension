<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Pinterest\PinterestMagento2Extension\Helper\PinterestLaminasClient;
use Laminas\Http\Request;
use Laminas\Http\Headers;
use Pinterest\PinterestMagento2Extension\Logger\Logger;

/**
 * Helper class used for calling Pinterst API endpoints
 */
class PinterestHttpClient
{
    // TODO note: all API request logging will be overhauled as part of larger effort

    public const PINTEREST_API_ENDPOINT="https://api.pinterest.com";
    public const V5_API_VERSION="v5";

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @param Curl $curl
     * @param Logger $logger
     */
    public function __construct(
        Curl $curl,
        Logger $logger
    ) {
        $this->_curl = $curl;
        $this->_logger = $logger;
    }
    
    /**
     * Creates the API endpoint URL to send all requests to
     *
     * @param string $suffix - API endpoint suffix
     * @return string V5 API url
     */
    public function getV5ApiEndpoint($suffix)
    {
        return self::PINTEREST_API_ENDPOINT."/".self::V5_API_VERSION."/".$suffix;
    }

    /**
     * Setup and send the get request
     *
     * @param string $url
     * @param string $accessToken
     * @return mixed response object
     */
    public function get($url, $accessToken)
    {
        $this->_curl->addHeader("Authorization", "Bearer " . $accessToken);
        $this->_curl->get($url);
        return json_decode($this->_curl->getBody());
    }

    /**
     * Get response status for the last get/post call
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->_curl->getStatus();
    }

    /**
     * Setup and send the post request
     *
     * @param string $url
     * @param array $params
     * @param string $accessToken
     * @param string $authorization
     * @param string $contentType
     * @param string $body
     * @return mixed response object
     * @param array $queryParams
     */
    public function post(
        $url,
        $params,
        $accessToken,
        $authorization = null,
        $contentType = "application/json",
        $body = null,
        $queryParams = []
    ) {
        if ($authorization) {
            $this->_curl->addHeader("Authorization", $authorization);
        } else {
            /* default authorization uses the provided access token */
            $this->_curl->addHeader("Authorization", "Bearer " . $accessToken);
        }
        $this->_curl->addHeader("Content-Type", $contentType);
        if ($body) {
            $this->_curl->setOption(CURLOPT_POSTFIELDS, $body);
        }
        if (!empty($queryParams)) {
            $url = $url . "?" . http_build_query($queryParams);
        }
        $this->_curl->post($url, json_encode($params));
        return json_decode($this->_curl->getBody());
    }

    /**
     * Send a PATCH request (modify as necessary)
     *
     * @param string $url
     * @param array $params body params
     * @param string $accessToken
     * @return Laminas\Http\Response
     * */
    public function patch($url, $params, $accessToken)
    {
        $request = new Request();
        $request->setUri($url);
        $request->setContent(json_encode($params));
        $request->setMethod('PATCH');
        $request->getHeaders()->addHeaders([
            "Accept" => "*/*",
            "Content-Type" => "application/json"
        ]);
        $client = new PinterestLaminasClient($this->getBearerAuthString($accessToken));
        $client->clearAuth();
        $response = $client->send($request);
        $this->_logger->info("PATCH " . $url .": response " .$response->getStatusCode());
        return $response;
    }

    /**
     * Send a DELETE request
     *
     * @param string $url
     * @param string $accessToken
     * @return Laminas\Http\Response
     */
    public function delete($url, $accessToken)
    {
        $httpHeaders = new Headers();
        $httpHeaders->addHeaders([
            "Accept" => "application/json",
        ]);
        $request = new Request();
        $request->setHeaders($httpHeaders);
        $request->setUri($url);
        $request->setMethod('DELETE');
        $client = new PinterestLaminasClient($this->getBearerAuthString($accessToken));
        $response = $client->send($request);
        $this->_logger->info("DELETE " . $url .": response " .$response->getStatusCode());
        return $response;
    }

    /**
     * Gets bearer auth access token.
     *
     * @param string $accessToken
     *
     * @return string
     */
    private function getBearerAuthString($accessToken)
    {
        return 'Bearer ' . $accessToken;
    }
}
