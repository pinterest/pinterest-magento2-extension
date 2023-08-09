<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use Pinterest\PinterestMagento2Extension\Constants\ConfigSetting;
use \Magento\Framework\Data\OptionSourceInterface;

class PinterestCatalogConfigList implements OptionSourceInterface
{
    /**
     * Returns the options for the config
     *
     * @return Array array of key value pairs with labels
     */
    public function toOptionArray()
    {
        return [
        ['value' => ConfigSetting::ENABLED, 'label' => __('Turn on catalog ingestion')],
        ['value' => ConfigSetting::DISABLED, 'label' => __('Turn off catalog ingestion')]
        ];
    }
}
