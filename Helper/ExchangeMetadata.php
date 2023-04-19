<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Constants\IntegrationErrorId;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;

class ExchangeMetadata
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
     * @var PluginErrorHelper
     */
    protected $_pluginErrorHelper;

    /**
     *
     * @param PinterestHelper $pinterestHelper
     * @param PinterestHttpClient $pinterestHttpClient
     * @param PluginErrorHelper $pluginErrorHelper
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        PinterestHttpClient $pinterestHttpClient,
        PluginErrorHelper $pluginErrorHelper
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_pluginErrorHelper = $pluginErrorHelper;
    }

    /**
     * Send partner metadata to Pinterest V5 API
     *
     * @param array $info
     */
    public function exchangeMetadata($info)
    {
        try {
            $this->_pluginErrorHelper->clearError("errors/metadata_post");
            $params = [
                "external_business_id" => $this->_pinterestHelper->getExternalBusinessId(),
                "connected_merchant_id" => $info['merchant_id'],
                "connected_advertiser_id" => $info['advertiser_id'],
                "connected_tag_id" => $info['tag_id'],
                "partner_primary_email" => $this->_pinterestHelper->getStoreEmail(),
                // TODO add partner_access_token COIN-1895
            ];
    
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce");
    
            $response = $this->_pinterestHttpClient->post($url, $params, $this->_pinterestHelper->getAccessToken());

            if (isset($response->code)) {
                $errorData = ['errorCode' => $response->code];
                $this->_pluginErrorHelper->logAndSaveError(
                    "errors/metadata_post",
                    $errorData,
                    "Failed to send metadata to Pinterest",
                    IntegrationErrorId::ERROR_CONNECT_NOT_BLOCKING
                );
                return false;
            } else {
                $this->_pinterestHelper->logInfo("V5 API data sent successfully");
                return true;
            }

        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
    }
}
