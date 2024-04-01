<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use \Magento\Framework\Data\OptionSourceInterface;

class PinterestYesNoOptions implements OptionSourceInterface
{
    const YES = 1;
    const NO = 2;
    /**
     * Returns the options for the config
     *
     * @return Array array of key value pairs with labels
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::YES, 'label' => __('Yes')],
            ['value' => self::NO, 'label' => __('No')]
        ];
    }
}
