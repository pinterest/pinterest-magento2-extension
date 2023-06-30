<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml;

use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
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
     * @param Context $context
     * @param PluginErrorHelper $pluginErrorHelper
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     * @param Registry $registry
     * @param CustomerDataHelper $customerDataHelper
     */
    public function __construct(
        Context $context,
        PluginErrorHelper $pluginErrorHelper,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        Registry $registry,
        CustomerDataHelper $customerDataHelper,
        array $data = []
    ) {
        $this->_pluginErrorHelper = $pluginErrorHelper;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_eventManager = $eventManager;
        $this->_registry = $registry;
        $this->_customerDataHelper = $customerDataHelper;
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
    
    public function getCurrency()
    {
        return $this->_pinterestHelper->getCurrency();
    }

    public function getRedirectUrl()
    {
        return $this->_pinterestHelper->getUrl(PinterestHelper::REDIRECT_URI);
    }

    public function isUserConnected()
    {
        return $this->_pinterestHelper->isUserConnected();
    }

    public function isTagEnabled()
    {
        return $this->isUserConnected() &&
            $this->_pinterestHelper->isConversionConfigEnabled();
    }

    public function getMetaTag()
    {
        return $this->_pinterestHelper->getMetadataValue("pinterest/website_claiming/meta_tag");
    }

    public function getTagId()
    {
        return $this->_pinterestHelper->getMetadataValue("pinterest/info/tag_id");
    }

    public function isCookieRestrictionModeEnabled()
    {
        return $this->_pinterestHelper->isCookieRestrictionModeEnabled();
    }

    public function getCurrentWebsiteId()
    {
        return $this->_pinterestHelper->getCurrentWebsiteId();
    }

    /**
     * @param null $store_id
     * @return int
     */
    public function getGdprOption($store_id = null)
    {
        return $this->_pinterestHelper->getGdprOption($store_id);
    }

    /**
     * @param null $store_id
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
     * @param null $store_id
     * @return int
     */
    public function isGdprEnabled($store_id = null)
    {
        return (int) $this->_pinterestHelper->isGdprEnabled($store_id);
    }

    public function scriptConfigBeforeConnect()
    {
        return [
            "baseUrl" => $this->_pinterestHelper->getBaseUrl(),
            "pinterestBaseUrl" => $this->_pinterestHelper->getPinterestBaseUrl(),
            "redirectUri" => $this->_pinterestHelper->getUrl(PinterestHelper::REDIRECT_URI),
            "clientId" => $this->_pinterestHelper->getClientId(),
            "state" => $this->_pinterestHelper->getRandomState(),
            "partnerMetadata" => $this->_pinterestHelper->getPartnerMetadata(),
            "adminhtmlSetupUri" => $this->_pinterestHelper->getUrl(PinterestHelper::ADMINHTML_SETUP_URI),
            "locale" => $this->_pinterestHelper->getUserLocale()
        ];
    }
    public function scriptConfigAfterConnect()
    {
        return [
            "pinterestBaseUrl" => $this->_pinterestHelper->getPinterestBaseUrl(),
            "accessToken" => $this->_pinterestHelper->getAccessToken(),
            "advertiserId" => $this->_pinterestHelper->getAdvertiserId(),
            "merchantId" => $this->_pinterestHelper->getMerchantId(),
            "tagId" => $this ->_pinterestHelper->getTagId(),
            "disconnectURL" => $this->_pinterestHelper->getUrl("pinterestadmin/Setup/Disconnect"),
            "errors" => $this->_pluginErrorHelper->getAllStoredErrors(),
            "partnerMetadata" => $this->_pinterestHelper->getPartnerMetadata(),
            "clientId" => $this->_pinterestHelper->getClientId(),
            "locale" => $this->_pinterestHelper->getUserLocale()
        ];
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
