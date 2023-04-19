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
use Pinterest\PinterestMagento2Extension\Logger\Logger;

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
     * Construct
     */
    public function __construct(
        ProductRepositoryInterface $productloader,
        ManagerInterface $messageManager,
        LocaleList $localelist,
        CatalogFeedClient $catalogFeedClient,
        Logger $appLogger,
        CacheInterface $cache
    ) {
        $this->_productloader = $productloader;
        $this->_messageManager = $messageManager;
        $this->_localehelper = $localelist;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_appLogger = $appLogger;
        $this->cache = $cache;
    }

    /**
     * Reserved for batch update
     *
     * if count(cache) >= CACHE_MAX_ITEMS: emit all
     * if time_diff(now, earliest) >= MAX_HOLD_SECONDS: emit all
     * if count(cache) < CACHE_MAX_ITEMS and time_diff(now, earliest) < MAX_HOLD_SECONDS, append and exit
     *
     * @param mixed $new_id a new product_id recently updated or null if not exists
     *
     * @return array product_ids to be emitted
     */
    protected function emit_ids($new_id)
    {
        $data = $this->cache->load(self::RECENT_SAVE) ?? "";
        if ($data == "") {
            $data = json_encode(["start_time" => time(), "ids" => []]);
        }
        $meta = json_decode($data, true);
        if (!is_null($new_id)) {
            if (! in_array($new_id, $meta["ids"]??[])) {
                $meta["ids"] []= $new_id;
                $this->cache->save(json_encode($meta), self::RECENT_SAVE, self::CACHE_TAGS);
            }
        }
        $ret_ids = $meta["ids"] ?? [];
        if (time() - $meta["start_time"] >= self::MAX_HOLD_SECONDS || count($ret_ids) >= self::CACHE_MAX_ITEMS) {
            return $ret_ids;
        }
        return [];
    }


    /**
     *
     * Hold or send product updates, see emit_ids() for rules
     *
     *
     */
    protected function check_queue($locale, $new_id)
    {
        $empty_json = json_decode("");
        $emit_ids = $this->emit_ids($new_id);
        if (count($emit_ids) > 0) {
            // reset cache in case other updates happend before we send updates.
            $this->cache->save(json_encode(["start_time" => time()]), self::RECENT_SAVE, self::CACHE_TAGS);

            $items = [];
            foreach ($emit_ids as $product_id) {
                $items [] = json_decode($this->cache->load(self::CACHE_KEY_PREFIX . $product_id)) ?? $empty_json;
            }

            $this->_catalogFeedClient->updateCatalogItems($locale, $items);
            $this->splash($items);
        }
    }

    /**
     *
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
     */
    protected function is_changed($product_id, $json_data)
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $product_id;
        $cacheValue = $this->cache->load($cacheKey);
        if ($json_data == $cacheValue) {
            return false;
        }
        $this->cache->save($json_data, $cacheKey, self::CACHE_TAGS, self::CACHE_TTL);
        return true;
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
        $is_updated = false;
        try {
            if (!$this->_catalogFeedClient->isUserConnected()) {
                return $is_updated;
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
            $product_id = $product["entity_id"];
            $store_id = $product["store_id"];
            $sku = $product["sku"] ?? null;
            $special_price = $product["special_price"] ?? null;
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
                "item_id"  => $sku,
                "attributes" => [
                    "price" => "{$price}{$currency}",
                    "sale_price" => $special_price,
                    "availability" => $availability
                ],
            ];
            $json_data = json_encode($data);
            $this->data_for_unittest = $json_data;
            $is_updated = $this->is_changed($product_id, $json_data);
            $this->check_queue($locale, $is_updated? $product_id : null);
        } catch (Exception $e) {
            $this->_messageManager->addError(
                "Exception when sending notification, message: {$e}"
            );
            $is_updated = false;
        }
        return $is_updated;
    }

     /**
      * Show message in admin UI
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
