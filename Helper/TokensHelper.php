<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Constants\MetadataName;

class TokensHelper
{

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;
    /**
     * @var PinterestHttpClient $pinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     *
     * @param PinterestHelper $pinterestHelper
     * @param PinterestHttpClient $pinterestHttpClient
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        PinterestHttpClient $pinterestHttpClient
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_pinterestHttpClient = $pinterestHttpClient;
    }

    /**
     * Calls the refresh API for the token and stores the results in $tokenPrefix
     * 
     * @return true if token is refreshed successfully
     */
    private function callRefreshApi($refreshToken, $clientHash, $tokenPrefix) {
        $this->_pinterestHelper->logInfo("Attempting to refresh tokens");
        try { 
            $this->_pinterestHelper->resetApiErrorState("errors/refresh_tokens");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("oauth/token");
            $authorization = ("Basic " . $clientHash);
            $payload = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ];
            $response = $this->_pinterestHttpClient->post(
                $url,
                [],
                '',
                $authorization,
                "application/x-www-form-urlencoded",
                http_build_query($payload)
            );

            if ($response && isset($response->access_token)) {
                $this->_pinterestHelper->saveEncryptedMetadata($tokenPrefix . 'access_token', $response->access_token);
                $this->_pinterestHelper->saveMetadata($tokenPrefix . 'token_type', $response->token_type);
                $this->_pinterestHelper->saveMetadata($tokenPrefix . 'expires_in', $response->expires_in);
                $this->_pinterestHelper->saveMetadata($tokenPrefix . 'scope', $response->scope);
                if (isset($response->refresh_token) && isset($response->refresh_token_expires_in)
                    && strlen($response->refresh_token) > 0 && strlen($response->refresh_token_expires_in) > 0) {
                    $this->_pinterestHelper->saveEncryptedMetadata(
                        $tokenPrefix . 'refresh_token',
                        $response->refresh_token
                    );
                    $this->_pinterestHelper->saveMetadata(
                        $tokenPrefix . 'refresh_token_expires_in',
                        $response->refresh_token_expires_in
                    );
                };
                $this->_pinterestHelper->logInfo("Success refreshing tokens");
                return true;
            } else {
                $this->_pinterestHelper->logError("Failed to refresh tokens");
                $this->_pinterestHelper->logAndSaveAPIErrors($response, "errors/refresh_tokens");
                $this->_pinterestHelper->logError("Message: ".$response->message);
            }

        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while refreshing tokens");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }


    /**
     * Requests and stores new access and refresh tokens for $storeId.
     *
     * @return true if the call is successful
     */
    public function refreshStoreToken($storeId)
    {
        $refreshToken = $this->_pinterestHelper->getRefreshToken($storeId);
        $clientHash = $this->_pinterestHelper->getClientHash($storeId);
        return $this->callRefreshApi($refreshToken, $clientHash, MetadataName::PINTEREST_TOKEN_PREFIX . $storeId . '/');
    }

    /**
     * Requests and stores new access and refresh tokens.
     *
     * @return true if the call is successful
     */
    public function refreshTokens()
    {
        $refreshToken = $this->_pinterestHelper->getRefreshToken();
        $clientHash = $this->_pinterestHelper->getClientHash();
        return $this->callRefreshApi($refreshToken, $clientHash, MetadataName::PINTEREST_TOKEN_PREFIX);
    }
}
