<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Pinterest\PinterestMagento2Extension\Constants\ConfigSetting;
use Pinterest\PinterestMagento2Extension\Constants\FeatureFlag;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
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
    public function enableCatalogsFeature()
    {
        $this->setFeatureFlag("pinterest_catalog_enabled", ConfigSetting::ENABLED);
        $this->_pinterestHelper->logInfo("enabled catalog feature");
    }

    /**
     * Disable catalogs feature in config
     */
    public function disableCatalogsFeature()
    {
        $this->setFeatureFlag("pinterest_catalog_enabled", ConfigSetting::DISABLED);
        $this->_pinterestHelper->logInfo("disabled catalog feature");
    }

    /**
     * Enable conversions feature in config
     */
    public function enableConversionsFeature()
    {
        $this->setFeatureFlag("pinterest_conversion_enabled", ConfigSetting::ENABLED);
        $this->_pinterestHelper->logInfo("enabled conversions feature");
    }

    /**
     * Disable conversions feature in config
     */
    public function disableConversionsFeature()
    {
        $this->setFeatureFlag("pinterest_conversion_enabled", ConfigSetting::DISABLED);
        $this->_pinterestHelper->logInfo("disabled conversions feature");
    }

    /**
     * Given array of feature flags values, save in config
     *
     * @param array $flagsArray
     *
     * @throws InvalidArgumentException
     * @return void
     */
    public function saveFeatureFlags($flagsArray)
    {
        if (!(array_key_exists(FeatureFlag::TAG, $flagsArray) &&
              array_key_exists(FeatureFlag::CAPI, $flagsArray) &&
              array_key_exists(FeatureFlag::CATALOG, $flagsArray))) {
            throw new InvalidArgumentException('Cannot save invalid feature flags array.');
        }

        if ($flagsArray[FeatureFlag::TAG] === true && $flagsArray[FeatureFlag::CAPI] === true) {
            $this->enableConversionsFeature();
        } else {
            $this->disableConversionsFeature();
        }
        if ($flagsArray[FeatureFlag::CATALOG] === true) {
            $this->enableCatalogsFeature();
        } else {
            $this->disableCatalogsFeature();
        }
    }

    /**
     * Write feature flag value to config
     *
     * @param string $flag - name of flag
     * @param string $setting - enabled/disabled
     */
    protected function setFeatureFlag($flag, $setting)
    {
        $path = 'PinterestConfig/general/' . $flag;
        $value = $setting;
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0 ;
        $this->_writerInterface->save($path, $value, $scope, $scopeId);
    }
}
