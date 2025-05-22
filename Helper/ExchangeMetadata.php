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

    protected function constructParams($method, $storeId = null)
    {
        $conversionsEnabled = $this->_pinterestHelper->isConversionConfigEnabled($storeId);
        $catalogEnabled = $this->_pinterestHelper->isCatalogConfigEnabled($storeId);
        $gdprEnabled = $this->_pinterestHelper->isGdprEnabled($storeId);
        $ldpEnabled = $this->_pinterestHelper->isLdpEnabled($storeId);
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
            "connected_merchant_id" => $this->_pinterestHelper->getMerchantId($storeId),
            "connected_advertiser_id" => $this->_pinterestHelper->getAdvertiserId($storeId),
            "partner_primary_email" => $this->_pinterestHelper->getStoreEmail(),
            "partner_metadata" => json_encode($partner_metadata_param),
            // TODO add partner_access_token
        ];
        $tagId = $this->_pinterestHelper->getTagId($storeId);
        if ($tagId) {
            $params["connected_tag_id"] = $tagId;
        }
        if ($method == "POST") {
            $params["external_business_id"] = $this->_pinterestHelper->getExternalBusinessId($storeId);
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
            $this->_pinterestHelper->logError("An error occurred while PATCHing metadata to Pinterest");
            $this->_pinterestHelper->logException($e);
        }
    }
    
    /**
     * Performs POST integrations/commerce request
     *
     * @param boolean success
     */
    public function postMetadata($storeId = null)
    {
        try {
            $this->_pluginErrorHelper->clearError("errors/metadata_post");
            $params = $this->constructParams("POST", $storeId);
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce");
    
            $response = $this->_pinterestHttpClient->post($url, $params, $this->_pinterestHelper->getAccessToken($storeId));

            if (isset($response->code)) {
                $errorData = ['errorCode' => $response->code,'errorMessage' => $response->message];
                $this->_pluginErrorHelper->logAndSaveError(
                    $storeId ? "errors/metadata_post for storeId:" . $storeId : "errors/metadata_post",
                    $errorData,
                    $storeId ? "Failed to POST metadata to Pinterest for storeId: " . $storeId : "Failed to POST metadata to Pinterest",
                    IntegrationErrorId::ERROR_CONNECT_NOT_BLOCKING
                );
                return false;
            } else {
                $this->_pinterestHelper->logInfo("POST metadata success");
                return true;
            }

        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError($storeId ? "An error occurred while POSTing metadata to Pinterest, for storeId: " . $storeId : "An error occurred while POSTing metadata to Pinterest");
            $this->_pinterestHelper->logException($e);
        }
    }
}
