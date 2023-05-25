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
        ['value' => 'enabled', 'label' => __('Enable Client Tag and Conversion API')],
        ['value' => 'disabled', 'label' => __('Disable Client Tag and Conversion API')]
        ];
    }
}
