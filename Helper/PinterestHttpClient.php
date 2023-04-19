<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\Client;

/**
 * Helper class used for calling Pinterst API endpoints
 */
class PinterestHttpClient
{
    public const PINTEREST_API_ENDPOINT="https://api.pinterest.com";
    public const V5_API_VERSION="v5";

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @param Curl $curl
     */
    public function __construct(
        Curl $curl
    ) {
        $this->_curl= $curl;
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
     */
    public function post(
        $url,
        $params,
        $accessToken,
        $authorization = null,
        $contentType = "application/json",
        $body = null
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
        $this->_curl->post($url, json_encode($params));
        return json_decode($this->_curl->getBody());
    }

    /**
     * Setup and send the delete request
     *
     * The default curl library does not support delete so we are using
     * https://developer.adobe.com/commerce/webapi/get-started/gs-web-api-request/
     *
     * @param string $url
     * @param string $accessToken
     * @return Zend\Http\Response response object
     */
    public function delete($url, $accessToken)
    {
        $httpHeaders = new Headers();
        $httpHeaders->addHeaders([
            "Accept" => "application/json",
            "Authorization" => "Bearer " . $accessToken,
        ]);
        $request = new Request();
        $request->setHeaders($httpHeaders);
        $request->setUri($url);
        $request->setMethod(Request::METHOD_DELETE);
        $client = new Client();
        $res = $client->send($request);
        return Response::fromString($res);
    }
}
