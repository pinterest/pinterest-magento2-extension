<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml;

use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Pinterest\PinterestMagento2Extension\Model\Config\PinterestGDPROptions;
use Magento\Cookie\Helper\Cookie;

class Setup extends Template
{
    /**
     * @var PluginErrorHelper $pluginErrorHelper
     */
    protected $_pluginErrorHelper;

    /**
     * @var PinterestHelper $pinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var EventManager
     */
    protected $_eventManager;

    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var CustomerDataHelper
     */
    protected $_customerDataHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param Context $context
     * @param PluginErrorHelper $pluginErrorHelper
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     * @param Registry $registry
     * @param CustomerDataHelper $customerDataHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        PluginErrorHelper $pluginErrorHelper,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        Registry $registry,
        CustomerDataHelper $customerDataHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_pluginErrorHelper = $pluginErrorHelper;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_eventManager = $eventManager;
        $this->_registry = $registry;
        $this->_customerDataHelper = $customerDataHelper;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Returns the hashed emailId for enhanced matching
     *
     * @return string|null
     */
    public function getHashedEmailId()
    {
        $emailId = $this->_customerDataHelper->getEmail();
        return $emailId ?
            $this->_customerDataHelper->hash($emailId) :
            null;
    }

    /**
     * Get current store currency code
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->_pinterestHelper->getCurrency();
    }

    /**
     * Get redirect URL based
     *
     * @return mixed
     */
    public function getRedirectUrl()
    {
        return $this->_pinterestHelper->getUrl(PinterestHelper::REDIRECT_URI);
    }

    /**
     * Get current store id
     *
     * @return number
     */
    public function getCurrentStoreId()
    {
        return $this->_pinterestHelper->getCurrentStoreId();
    }

    /**
     * Checks if the user has valid connection to pinterest
     * @param int $storeId
     *
     * @return bool
     */
    public function isUserConnected($storeId = null)
    {
        return $this->_pinterestHelper->isUserConnected($storeId);
    }

    /**
     * Returns true if the tag is enabled
     * @param int $storeId
     *
     * @return bool
     */
    public function isTagEnabled($storeId = null)
    {
        return $this->isUserConnected($storeId) &&
            $this->_pinterestHelper->isConversionConfigEnabled($storeId);
    }

    /**
     * Gets metadata for meta_tag value
     *
     * @param string
     */
    public function getMetaTag($storeId = null)
    {
        return $this->_pinterestHelper->getMetadataValue("pinterest/website_claiming/meta_tag" . ($storeId != null ? "/$storeId" : ""));
    }

    /**
     * Gets metadata for tag_id value
     * @param int $storeId
     *
     * @param string
     */
    public function getTagId($storeId = null)
    {
        return $this->_pinterestHelper->getMetadataValue($this->_pinterestHelper->getInfoByStoreAndName("tag_id", $storeId));
    }

    /**
     * Checks if user has consented to tracking via cookies.
     *
     * @return bool
     */
    public function isCookieRestrictionModeEnabled()
    {
        return $this->_pinterestHelper->isCookieRestrictionModeEnabled();
    }

    /**
     * Returns current website id.
     *
     * @return int
     */
    public function getCurrentWebsiteId()
    {
        return $this->_pinterestHelper->getCurrentWebsiteId();
    }

    /**
     * Gets GDPR option for a store id
     *
     * @param int $store_id
     * @return int
     */
    public function getGdprOption($store_id = null)
    {
        return $this->_pinterestHelper->getGdprOption($store_id);
    }

    /**
     * Gets GDPR cookie name for a store id.
     *
     * @param int $store_id
     * @return string
     */
    public function getGDPRCookieName($store_id = null)
    {
        if ($this->_pinterestHelper->getGdprOption($store_id) == PinterestGDPROptions::USE_COOKIE_RESTRICTION_MODE) {
            return Cookie::IS_USER_ALLOWED_SAVE_COOKIE;
        } else {
            return $this->_pinterestHelper->getGDPRCookieName($store_id) ?
                $this->_pinterestHelper->getGDPRCookieName($store_id) : Cookie::IS_USER_ALLOWED_SAVE_COOKIE;
        }
    }

    /**
     * Returns true if GDPR is enabled in config for this extension
     *
     * @param int $store_id
     * @return int
     */
    public function isGdprEnabled($store_id = null)
    {
        return (int) $this->_pinterestHelper->isGdprEnabled($store_id);
    }

    /**
     * Returns true if LDP is enabled in config for this extension
     *
     * @return int
     */
    public function isLdpEnabled($store_id = null)
    {
        return (int) $this->_pinterestHelper->isLdpEnabled($store_id);
    }

    /**
     * Returns the customer's hashed state for tag events
     *
     * @return string|null
     */
    public function getHashedState()
    {
        $state = $this->_customerDataHelper->getState();
        return $state ?
            $this->_customerDataHelper->hash($state) :
            null;
    }

    /**
     * Returns the customer's hashed country for tag events
     *
     * @return string|null
     */
    public function getHashedCountry()
    {
        $country = $this->_customerDataHelper->getCountry();
        return $country ?
            $this->_customerDataHelper->hash($country) :
            null;
    }

    /**
     * Returns configuration before connecting.
     *
     * @return array
     */
    public function scriptConfigBeforeConnect()
    {
        return [
            "baseUrl" => $this->_pinterestHelper->getBaseUrl(),
            "pinterestBaseUrl" => $this->_pinterestHelper->getPinterestBaseUrl(),
            "iframeVersion" => (PinterestHelper::IFRAME_VERSION),
            "redirectUri" => $this->_pinterestHelper->getUrl(PinterestHelper::REDIRECT_URI),
            "clientId" => $this->_pinterestHelper->getClientId(),
            "state" => $this->_pinterestHelper->getRandomState(),
            "partnerMetadata" => $this->_pinterestHelper->getPartnerMetadata(),
            "adminhtmlSetupUri" => $this->_pinterestHelper->getUrl(PinterestHelper::ADMINHTML_SETUP_URI),
            "locale" => $this->_pinterestHelper->getUserLocale()
        ];
    }

    /**
     * Returns configuration after connecting.
     *
     * @return array
     */
    public function scriptConfigAfterConnect()
    {
        return [
            "pinterestBaseUrl" => $this->_pinterestHelper->getPinterestBaseUrl(),
            "iframeVersion" =>  (PinterestHelper::IFRAME_VERSION),
            "accessToken" => $this->_pinterestHelper->getAccessToken(),
            "advertiserId" => $this->_pinterestHelper->getAdvertiserId(),
            "merchantId" => $this->_pinterestHelper->getMerchantId(),
            "tagId" => ($this ->_pinterestHelper->getTagId()? $this ->_pinterestHelper->getTagId(): ""),
            "disconnectURL" => $this->_pinterestHelper->getUrl("pinterestadmin/Setup/Disconnect"),
            "errors" => $this->_pluginErrorHelper->getAllStoredErrors(),
            "partnerMetadata" => $this->_pinterestHelper->getPartnerMetadata(),
            "clientId" => $this->_pinterestHelper->getClientId(),
            "locale" => $this->_pinterestHelper->getUserLocale()
        ];
    }

    /**
     * Returns configuration after connecting for multistore.
     *
     * @return array
     */
    public function scriptConfigMultistore($storeId)
    {
        $metadata = $this->_pinterestHelper->getPartnerMetadata($storeId);
        return [
            "pinterestBaseUrl" => $this->_pinterestHelper->getPinterestBaseUrl(),
            "iframeVersion" =>  (PinterestHelper::IFRAME_VERSION),
            "accessToken" => $this->_pinterestHelper->getAccessToken($storeId),
            "advertiserId" => $this->_pinterestHelper->getAdvertiserId($storeId),
            "merchantId" => $this->_pinterestHelper->getMerchantId($storeId),
            "tagId" => ($this ->_pinterestHelper->getTagId($storeId)? $this ->_pinterestHelper->getTagId($storeId): ""),                    
            "disconnectURL" => $this->_pinterestHelper->getUrl("pinterestadmin/Setup/DisconnectMultisite"),
            "setupURL" => $this->_pinterestHelper->getUrl("pinterestadmin/Setup/Index"),
            "errors" => $this->_pluginErrorHelper->getAllStoredErrors(),
            "partnerMetadata" => $metadata,
            "clientId" => $this->_pinterestHelper->getClientId(),
            "businessId" => $this->_pinterestHelper->getExternalBusinessId($storeId),
            "locale" => $this->_pinterestHelper->getUserLocale(),
            "settingsURL" => $this->_pinterestHelper->getUrl(PinterestHelper::ADMINHTML_SETTINGS_URI),
            "siteId" => $storeId,
            "siteName" => $metadata["storeName"],
            "siteURL" => $metadata["baseUrl"]
        ];
    }
    
    /**
     * Returns website list
     *
     * @return string
     */
    public function getWebsites()
    {
        return $this->_pinterestHelper->getStoresData();
    }

    /**
     * Returns connected stores ids
     *
     * @return array
     */
    public function getConnectedStoreIds()
    {
        $connectedStores = $this->_pinterestHelper->getMetadataValue("pinterest/multisite/stores");
        if ($connectedStores == null || strlen($connectedStores) == 0) {
            return [];
        }
        return array_map([$this, 'scriptConfigMultistore'], explode(",", $connectedStores));
    }

    /**
     * Returns axios url for conversion event tracker
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConversionEventUrl()
    {
        return sprintf("%spin/Tag/ConversionEvent", $this->_pinterestHelper->getBaseUrl());
    }
}
