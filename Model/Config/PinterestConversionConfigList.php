<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use \Magento\Framework\Data\OptionSourceInterface;

class PinterestConversionConfigList implements OptionSourceInterface
{
    /**
     * Returns the options for the config
     *
     * @return Array array of key value pairs with labels
     */
    public function toOptionArray()
    {
        return [
        ['value' => 'enabled', 'label' => __('Turn on Pinterest tag and Pinterest API for Conversions')],
        ['value' => 'disabled', 'label' => __('Turn off Pinterest tag and Pinterest API for Conversions')]
        ];
    }
}
