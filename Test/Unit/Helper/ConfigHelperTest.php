<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\ConfigHelper;
use Pinterest\PinterestMagento2Extension\Constants\ConfigSetting;
use Pinterest\PinterestMagento2Extension\Constants\FeatureFlag;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

use InvalidArgumentException;

class ConfigHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var WriterInterface
     */
    protected $_writerInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfigInterface;

    public function setUp() : void
    {
        $this->_writerInterface = $this->createMock(WriterInterface::class);
        $this->_scopeConfigInterface = $this->createMock(ScopeConfigInterface::class);
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);

        $this->_configHelper = new ConfigHelper(
            $this->_writerInterface,
            $this->_scopeConfigInterface,
            $this->_pinterestHelper
        );
    }

    public function testSaveValidFeatureFlags()
    {
        $mockFeatureFlagsArray = [
            FeatureFlag::TAG => true,
            FeatureFlag::CAPI => true,
            FeatureFlag::CATALOG => false
        ];
        $expectedPath1 = 'PinterestConfig/general/pinterest_conversion_enabled';
        $expectedPath2 = 'PinterestConfig/general/pinterest_catalog_enabled';
        $this->_writerInterface->expects($this->any())->method("save")->withConsecutive(
            [$expectedPath1, ConfigSetting::ENABLED, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
            [$expectedPath2, ConfigSetting::DISABLED, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0]
        );
        $this->_configHelper->saveFeatureFlags($mockFeatureFlagsArray);
    }

    public function testSaveValidFeatureFlags2()
    {
        $mockFeatureFlagsArray = [
            FeatureFlag::TAG => false,
            FeatureFlag::CAPI => false,
            FeatureFlag::CATALOG => true
        ];
        $expectedPath1 = 'PinterestConfig/general/pinterest_conversion_enabled';
        $expectedPath2 = 'PinterestConfig/general/pinterest_catalog_enabled';
        $this->_writerInterface->expects($this->any())->method("save")->withConsecutive(
            [$expectedPath1, ConfigSetting::DISABLED, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
            [$expectedPath2, ConfigSetting::ENABLED, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0]
        );
        $this->_configHelper->saveFeatureFlags($mockFeatureFlagsArray);
    }

    public function testSaveInvalidFeatureFlags()
    {
        $mockFeatureFlagsArray = [
            FeatureFlag::TAG => true,
            FeatureFlag::CAPI => true
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->_configHelper->saveFeatureFlags($mockFeatureFlagsArray);
    }
}
