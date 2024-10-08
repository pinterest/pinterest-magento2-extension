<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use \Magento\Framework\Data\OptionSourceInterface;

class PinterestGDPROptions implements OptionSourceInterface
{
    const USE_COOKIE_RESTRICTION_MODE = 1;
    const IF_COOKIE_NOT_EXIST = 2;
    const CMS_COOKIE_BOT = 3;
    /**
     * Returns the options for the config
     *
     * @return Array array of key value pairs with labels
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::USE_COOKIE_RESTRICTION_MODE, 'label' => __('Use Magento Cookie Restriction Mode')],
            ['value' => self::IF_COOKIE_NOT_EXIST, 'label' => __('Enable Tracking if Cookie Exists and not false')],
            ['value' => self::CMS_COOKIE_BOT, 'label' => __('Use CookieBot Consent management Integration')]
        ];
    }
}
