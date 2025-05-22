<?php

namespace Pinterest\PinterestMagento2Extension\Observer;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Context;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use Pinterest\PinterestMagento2Extension\Helper\ProductExporter;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;

class CatalogProductSaveObserver implements ObserverInterface
{
    public const CACHE_KEY_PREFIX = "Pinterest_CIE_product_saved_";
    public const CACHE_TAGS = ['pinterest_product_snapshot_saved'];
    public const CACHE_TTL = 86400;
    public const CACHE_MAX_ITEMS = 500;
    public const MAX_HOLD_SECONDS = 60;
    public const RECENT_SAVE = "pinterest_product_id_recents";
    public const IN_STOCK = 'in stock';
    public const OUT_OF_STOCK = 'out of stock';
    public const ALL_STORE_VIEWS_ID = 0;

    /**
     * @var mixed
     */
    public $data_for_unittest;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var ProductRepositoryInterface
     */
    private $_productloader;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var ManagerInterface
     */
    private $_messageManager;

    /**
     * @var LocaleList
     */
    private $_localehelper;

    /**
     * @var CatalogFeedClient
     */
    private $_catalogFeedClient;

    /**
     * @var Logger
     */
    private $_appLogger;

    /**
     * @param ProductRepositoryInterface $productloader
     * @param   PinterestHelper $pinterestHelper
     * @param   ManagerInterface $messageManager
     * @param   LocaleList $localelist
     * @param   CatalogFeedClient $catalogFeedClient
     * @param   Logger $appLogger
     * @param  CacheInterface $cache
     */
    public function __construct(
        ProductRepositoryInterface $productloader,
        PinterestHelper $pinterestHelper,
        ManagerInterface $messageManager,
        LocaleList $localelist,
        CatalogFeedClient $catalogFeedClient,
        Logger $appLogger,
        CacheInterface $cache
    ) {
        $this->_productloader = $productloader;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_messageManager = $messageManager;
        $this->_localehelper = $localelist;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_appLogger = $appLogger;
        $this->cache = $cache;
    }

    /**
     * Reserved for batch update
     *
     * If count(cache) >= CACHE_MAX_ITEMS: emit all
     * if time_diff(now, earliest) >= MAX_HOLD_SECONDS: emit all
     * if count(cache) < CACHE_MAX_ITEMS and time_diff(now, earliest) < MAX_HOLD_SECONDS, append and exit
     *
     * @param mixed $new_id a new product_id recently updated or null if not exists
     *
     * @return array product_ids to be emitted
     */
    protected function emitIds($new_id, $store_id)
    {
        $key = self::RECENT_SAVE . $store_id;
        $data = $this->cache->load($key) ?? "";
        if ($data == "") {
            $data = json_encode(["start_time" => time(), "ids" => []]);
        }
        $meta = json_decode($data, true);
        if ($new_id !== null) {
            if (! in_array($new_id, $meta["ids"]??[])) {
                $meta["ids"] []= $new_id;
                $this->cache->save(json_encode($meta), $key, self::CACHE_TAGS);
            }
        }
        $ret_ids = $meta["ids"] ?? [];
        if (time() - $meta["start_time"] >= self::MAX_HOLD_SECONDS || count($ret_ids) >= self::CACHE_MAX_ITEMS) {
            return $ret_ids;
        }
        return [];
    }

    /**
     * Hold or send product updates, see emitIds() for rules
     *
     * @param string $locale
     * @param mixed $new_id
     */
    protected function checkQueue($locale, $new_id, $store_id)
    {
        $empty_json = json_decode("");
        $emitIds = $this->emitIds($new_id, $store_id);
        if (count($emitIds) > 0) {
            // reset cache in case other updates happend before we send updates.
            $this->cache->save(json_encode(["start_time" => time()]), self::RECENT_SAVE . $store_id, self::CACHE_TAGS);

            $items = [];
            foreach ($emitIds as $product_id) {
                $items [] = json_decode($this->cache->load(self::CACHE_KEY_PREFIX . $product_id . $store_id)) ?? $empty_json;
            }

            $this->_catalogFeedClient->updateCatalogItems($locale, $items, $store_id);
            $this->splash($items);
        }
    }

    /**
     * Use Magento's CacheInterface to store product alteration data
     *
     * Another way is to use product's extension attributes
     *
     * e.g.
     *   $ea = $product->getExtensionAttributes();
     *   $old_data = $ea->getProductAlteration();
     *   $ea->setProductAlteration($new_data);
     *   $product->setExtensionAttributes($ea);
     * where set/getProductAlteration should be defined using AbstractExtensibleModel
     *
     * CacheInterface is preferred over EA as it is much faster and more flexible.
     *
     * @param string $product_id
     * @param array $json_data
     *
     * @return bool
     */
    protected function isChanged($product_id, $json_data, $store_id)
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $product_id . $store_id;
        $cacheValue = $this->cache->load($cacheKey);
        if ($json_data == $cacheValue) {
            return false;
        }
        $this->cache->save($json_data, $cacheKey, self::CACHE_TAGS, self::CACHE_TTL);
        return true;
    }

    /**
     * Check if special price field should be set for the current product.
     * Adds check as magento allows special price dates to be empty
     *
     * @param Product $product
     * @param string special price
     */
    protected function getSpecialPrice($product)
    {
        if(isset($product["special_price"])) {
            $now = time();
            $validPriceBefore = $product["special_to_date"] ? strtotime($product["special_to_date"]) >= $now : true;
            $validPriceAfter = $product["special_from_date"] ? strtotime($product["special_from_date"]) <= $now : true;
            if($validPriceAfter && $validPriceBefore) {
                return $product["special_price"];
            }
        }
        
        return null;
    }

    /**
     * Updates the product information after detecting changes
     * @param Product $product
     * @param string $store_id
     */
    protected function updateProduct($product, $store_id)
    {
        try {
            $product_id = $product["entity_id"];
            $sku = $product["sku"] ?? null;
            $special_price = $this->getSpecialPrice($product);
            $price = $product["price"];
            $stock_status = $product["quantity_and_stock_status"] ?? [];
            $stock_data = $product["stock_data"] ?? [];
            $is_in_stock = ($stock_status["is_in_stock"] ?? 0) || ($stock_data["is_in_stock"] ?? 0);
            $qty = ($stock_status["qty"] ?? 0) || ($stock_data["qty"] ?? 0);
            $availability = ($is_in_stock && $qty) ? self::IN_STOCK : self::OUT_OF_STOCK;

            $currency = " ".$this->_localehelper->getCurrency($store_id);
            $locale = $this->_localehelper->getLocale($store_id);
            if ($special_price) {
                $special_price .= $currency;
            }

            $data = [
                "item_id"  => ProductExporter::getUniqueId($product),
                "attributes" => [
                    "price" => "{$price}{$currency}",
                    "sale_price" => $special_price,
                    "availability" => $availability
                ],
            ];
            $json_data = json_encode($data);
            $this->data_for_unittest = $json_data;
            $is_updated = $this->isChanged($product_id, $json_data, $store_id);
            $this->checkQueue($locale, $is_updated? $product_id : null, $store_id);
            
            return $is_updated;

        } catch (\Throwable $e) {
            $this->_messageManager->addError(
                "Exception when sending notification, message: {$e}"
            );
            return false;
        }
    }

    /**
     * Define execute
     *
     * @param Observer $observer
     *
     * @return bool
     */
    public function execute(Observer $observer)
    {
        if (!$this->_pinterestHelper->isCatalogAndRealtimeUpdatesEnabled()) {
            return false;
        }
        /**
         * there are two methods to get product's information
         *
         * a) $product= $this->_productloader->getById($product_id); or
         * b) $product = $observer->getProduct();
         *
         * we choose method b) as it's already loaded in the context and the product object contains more
         * information like extension_attributes. To view all attributes, see
         *
         * $this->_appLogger->info("Observer:". print_r($product->debug(), true));
         *
         */

         $product = $observer->getProduct();
         if($this->_pinterestHelper->isMultistoreOn()){
            $storeIds = $product["store_id"] == self::ALL_STORE_VIEWS_ID ? $product->getStoreIds() : [$product["store_id"]];
            $connectedStores = $this->_pinterestHelper->getMappedStores();
            $filterNonConnected = function($id) use ($connectedStores){
                return in_array($id, $connectedStores);
            };
            $success = true;
            foreach(array_filter($storeIds, $filterNonConnected) as $storeId){
                $success &= $this->updateProduct($product, $storeId);
            }
            
            return $success == TRUE;
         } else {
            return $this->updateProduct($product, $product["store_id"]);
         }
    }

     /**
      * Show message in admin UI
      *
      * @param array $items
      *
      * @return void
      */
    protected function splash($items)
    {
        if (count($items) == 0) {
            return;
        }
        $ids = [];
        $items = array_slice($items, 0, 100);
        foreach ($items as $item) {
            $ids []= $item->item_id ?? 0;
        }
        $this->_messageManager->addSuccess('Notification sent for: ['.implode(",", $ids).']');
    }
}
