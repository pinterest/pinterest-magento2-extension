<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Pinterest\PinterestMagento2Extension\Constants\ConfigSetting;
use Pinterest\PinterestMagento2Extension\Constants\FeatureFlag;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Magento\Store\Model\ScopeInterface;
use InvalidArgumentException;

class ConfigHelper
{
    /**
     * @var WriterInterface
     */
    protected $_writerInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfigInterface;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     *
     * @param WriterInterface $writerInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param PinterestHelper $pinterestHelper
     */
    public function __construct(
        WriterInterface $writerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        PinterestHelper $pinterestHelper
    ) {
        $this->_writerInterface = $writerInterface;
        $this->_scopeConfigInterface = $scopeConfigInterface;
        $this->_pinterestHelper = $pinterestHelper;
    }

    /**
     * Enable catalogs feature in config
     */
    public function enableCatalogsFeature($storeId = null)
    {
        $this->setFeatureFlag("pinterest_catalog_enabled", ConfigSetting::ENABLED, $storeId);
        $this->_pinterestHelper->logInfo($storeId ? "enabled catalog feature for store: " . $storeId : "enabled catalog feature");
    }

    /**
     * Disable catalogs feature in config
     */
    public function disableCatalogsFeature($storeId = null)
    {
        $this->setFeatureFlag("pinterest_catalog_enabled", ConfigSetting::DISABLED, $storeId);
        $this->_pinterestHelper->logInfo($storeId ? "disabled catalog feature for store: " . $storeId : "disabled catalog feature");
    }

    /**
     * Enable conversions feature in config
     */
    public function enableConversionsFeature($storeId = null)
    {
        $this->setFeatureFlag("pinterest_conversion_enabled", ConfigSetting::ENABLED, $storeId);
        $this->_pinterestHelper->logInfo($storeId ? "enabled conversions feature for store: " . $storeId : "enabled conversions feature");
    }

    /**
     * Disable conversions feature in config
     */
    public function disableConversionsFeature($storeId = null)
    {
        $this->setFeatureFlag("pinterest_conversion_enabled", ConfigSetting::DISABLED, $storeId);
        $this->_pinterestHelper->logInfo($storeId ? "disabled conversions feature for store: " . $storeId : "disabled conversions feature");
    }

    /**
     * Given array of feature flags values, save in config
     *
     * @param array $flagsArray
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public function saveFeatureFlags($flagsArray, $storeId = null)
    {
        if (!(array_key_exists(FeatureFlag::TAG, $flagsArray) &&
              array_key_exists(FeatureFlag::CAPI, $flagsArray) &&
              array_key_exists(FeatureFlag::CATALOG, $flagsArray))) {
            throw new InvalidArgumentException('Cannot save invalid feature flags array.');
        }

        if ($flagsArray[FeatureFlag::TAG] === true && $flagsArray[FeatureFlag::CAPI] === true) {
            $this->enableConversionsFeature($storeId);
        } else {
            $this->disableConversionsFeature($storeId);
        }
        if ($flagsArray[FeatureFlag::CATALOG] === true) {
            $this->enableCatalogsFeature($storeId);
        } else {
            $this->disableCatalogsFeature($storeId);
        }
    }

    /**
     * Write feature flag value to config
     *
     * @param string $flag - name of flag
     * @param string $setting - enabled/disabled
     */
    protected function setFeatureFlag($flag, $setting, $scopeId = 0)
    {
        $path = 'PinterestConfig/general/' . $flag;
        $value = $setting;
        $scope = $scopeId != 0 ? ScopeInterface::SCOPE_STORES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->_writerInterface->save($path, $value, $scope, $scopeId);
    }
}
