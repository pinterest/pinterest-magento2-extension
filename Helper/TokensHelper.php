<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;

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
     * Requests and stores new access and refresh tokens.
     *
     * @return true if the call is successful
     */
    public function refreshTokens()
    {
        $this->_pinterestHelper->logInfo("Attempting to refresh tokens");
        try {
            $this->_pinterestHelper->resetApiErrorState("errors/refresh_tokens");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("oauth/token");
            $refreshToken = $this->_pinterestHelper->getRefreshToken();
            $authorization = ("Basic " . ($this->_pinterestHelper->getClientHash()));
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

                $this->_pinterestHelper->saveEncryptedMetadata('pinterest/token/access_token', $response->access_token);
                $this->_pinterestHelper->saveMetadata('pinterest/token/token_type', $response->token_type);
                $this->_pinterestHelper->saveMetadata('pinterest/token/expires_in', $response->expires_in);
                $this->_pinterestHelper->saveMetadata('pinterest/token/scope', $response->scope);
                if (isset($response->refresh_token) && isset($response->refresh_token_expires_in)
                    && strlen($response->refresh_token) > 0 && strlen($response->refresh_token_expires_in) > 0) {
                    $this->_pinterestHelper->saveEncryptedMetadata(
                        'pinterest/token/refresh_token',
                        $response->refresh_token
                    );
                    $this->_pinterestHelper->saveMetadata(
                        'pinterest/token/refresh_token_expires_in',
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
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }
}
