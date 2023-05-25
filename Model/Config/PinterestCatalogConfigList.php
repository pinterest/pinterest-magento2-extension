<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

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
        ['value' => 'enabled', 'label' => __('Enable catalogs and realtime updates')],
        ['value' => 'disabled', 'label' => __('Disable catalogs and realtime updates')]
        ];
    }
}
