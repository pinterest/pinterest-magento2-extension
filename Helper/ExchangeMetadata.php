<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Constants\IntegrationErrorId;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Constants\FeatureFlag;

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
     * Constructs params for integrations/commerce request
     *
     * @param string $method POST or PATCH
     * @return array $params
     */

    protected function constructParams($method)
    {
        $conversionsEnabled = $this->_pinterestHelper->isConversionConfigEnabled();
        $catalogEnabled = $this->_pinterestHelper->isCatalogConfigEnabled();
        $gdprEnabled = $this->_pinterestHelper->isGdprEnabled();
        $ldpEnabled = $this->_pinterestHelper->isLdpEnabled();
        $featureFlags = [
            FeatureFlag::CATALOG => $catalogEnabled,
            FeatureFlag::TAG => $conversionsEnabled,
            FeatureFlag::CAPI => $conversionsEnabled,
            FeatureFlag::GDPR => $gdprEnabled,
            FeatureFlag::LDP => $ldpEnabled
        ];
        $partner_metadata_param = [
            "feature_flags" => $featureFlags,
            "iframe_version" => PinterestHelper::IFRAME_VERSION,
        ];
        $params = [
            "connected_merchant_id" => $this->_pinterestHelper->getMerchantId(),
            "connected_advertiser_id" => $this->_pinterestHelper->getAdvertiserId(),
            "partner_primary_email" => $this->_pinterestHelper->getStoreEmail(),
            "partner_metadata" => json_encode($partner_metadata_param),
            // TODO add partner_access_token
        ];
        $tagId = $this->_pinterestHelper->getTagId();
        if ($tagId) {
            $params["connected_tag_id"] = $tagId;
        }
        if ($method == "POST") {
            $params["external_business_id"] = $this->_pinterestHelper->getExternalBusinessId();
        }
        return $params;
    }

    /**
     * Performs PATCH integrations/commerce request
     *
     * @return boolean success
     */
    public function patchMetadata()
    {
        try {
            $this->_pluginErrorHelper->clearError("errors/metadata_patch");
            $params = $this->constructParams("PATCH");
            $externalBusinessId = $this->_pinterestHelper->getExternalBusinessId();
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce/". $externalBusinessId);
            $response = $this->_pinterestHttpClient->patch($url, $params, $this->_pinterestHelper->getAccessToken());

            if (!$response->isSuccess()) {
                $errorData = ['statusCode' => $response->getStatusCode(), 'responseBody' => $response->getBody()];
                $this->_pluginErrorHelper->logAndSaveError(
                    "errors/metadata_patch",
                    $errorData,
                    "Failed to PATCH metadata to Pinterest",
                    IntegrationErrorId::GENERIC
                );
                return false;
            } else {
                $this->_pinterestHelper->logInfo("PATCH metadata success");
                return true;
            }

        } catch (\Throwable $e) {
            $this->_pinterestHelper->logException($e);
        }
    }
    
    /**
     * Performs POST integrations/commerce request
     *
     * @param boolean success
     */
    public function postMetadata()
    {
        try {
            $this->_pluginErrorHelper->clearError("errors/metadata_post");
            $params = $this->constructParams("POST");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce");
    
            $response = $this->_pinterestHttpClient->post($url, $params, $this->_pinterestHelper->getAccessToken());

            if (isset($response->code)) {
                $errorData = ['errorCode' => $response->code,'errorMessage' => $response->message];
                $this->_pluginErrorHelper->logAndSaveError(
                    "errors/metadata_post",
                    $errorData,
                    "Failed to POST metadata to Pinterest",
                    IntegrationErrorId::ERROR_CONNECT_NOT_BLOCKING
                );
                return false;
            } else {
                $this->_pinterestHelper->logInfo("POST metadata success");
                return true;
            }

        } catch (\Throwable $e) {
            $this->_pinterestHelper->logException($e);
        }
    }
}
