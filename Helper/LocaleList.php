<?php

declare(strict_types=1);

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class LocaleList
{
    private const XML_LOCALE_PATH = "general/locale/code";
    private const XML_DEFAULT_COUNTRY_PATH = "general/country/default";
    private const XML_DEFAULT_CURRENCY_PATH = "currency/options/base";

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var array
     */
    protected $savedLocales;

    /**
     * Locale constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->savedLocales = [];
    }

    /**
     * Get list of Locale for all stores
     *
     * @return array
     */
    public function getListLocaleForAllStores()
    {
        if (count($this->savedLocales) > 0) {
            return $this->savedLocales;
        }
        //Locale code
        $locales = [];
        $stores = $this->storeManager->getStores($withDefault = false);
        //Try to get list of locale for all stores;
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $locales[$storeId] = $this->getCountry($storeId)."\n";
            $locales[$storeId] .= $this->getLocale($storeId);
        }
        $this->savedLocales = $locales;
        return $locales;
    }

    /**
     * Get locale from store
     *
     * @param int $storeId
     *
     * @return mixed
     */
    public function getLocale($storeId)
    {
        return $this->scopeConfig->getValue(
            self::XML_LOCALE_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get country from store id
     *
     * @param int $storeId
     *
     * @return mixed
     */
    private function getCountry($storeId)
    {
        return $this->scopeConfig->getValue(
            self::XML_DEFAULT_COUNTRY_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get currentcy from store id
     *
     * @param int $storeId
     *
     * @return mixed
     */
    public function getCurrency($storeId)
    {
        return $this->scopeConfig->getValue(
            self::XML_DEFAULT_CURRENCY_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
