<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Store\Model\StoreManagerInterface;

class ExternalBusinessIdHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->_storeManager = $storeManager;
    }

    /**
     * Generate uniqid from store id & baseURL
     *
     * @param string $advertiserId
     * @return string
     */
    public function generateExternalBusinessId($advertiserId)
    {
        return uniqid($this->generateExternalBusinessIdPrefix(). $advertiserId . "_");
    }

    /**
     * Generate constant portion of external business id
     *
     * @param string $advertiserId
     * @return string
     */
    public function generateExternalBusinessIdPrefix()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $baseUrl = parse_url($this->_storeManager->getStore()->getBaseUrl())["host"];
        return ("magento_pins_" . $storeId . "_" . $baseUrl . "_");
    }
}
