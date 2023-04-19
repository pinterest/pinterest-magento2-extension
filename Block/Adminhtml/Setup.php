<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml;

use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

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
            !filter_var($this->_pinterestHelper->getConfig("disable_tag"), FILTER_VALIDATE_BOOLEAN);
    }

    public function getMetaTag()
    {
        return $this->_pinterestHelper->getMetadataValue("pinterest/website_claiming/meta_tag");
    }

    public function getTagId()
    {
        return $this->_pinterestHelper->getMetadataValue("pinterest/info/tag_id");
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
        ];
    }
}
