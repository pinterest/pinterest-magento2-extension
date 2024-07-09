<?php

declare(strict_types=1);

namespace Pinterest\PinterestMagento2Extension\Observer;

use Pinterest\PinterestMagento2Extension\Observer\CatalogProductSaveObserver;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;

class CatalogProductSaveObserverTest extends TestCase
{
    /**
     * @var CacheInterface
     */
    protected $_cache;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var ProductRepositoryInterface
     */
    protected $_productloader;

    /**
     *
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     *
     * @var LocaleList
     */
    protected $_localehelper;

    /**
     *
     * @var CatalogFeedClient
     */
    protected $_httpClient;

    /**
     *
     * @var Logger
     */
    protected $_logger;

    /**
     * @var CatalogFeedClient
     */
    protected $_catalogFeedClient;

    /**
     *
     * @var CatalogProductSaveObserver
     */
    protected $_catalogProductSaveObserver;

    /**
     * Used to set the values before running a test
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->_productloader = $this->createMock(ProductRepositoryInterface::class);
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_messageManager = $this->createMock(ManagerInterface::class);
        $this->_catalogFeedClient = $this->createMock(CatalogFeedClient::class);
        $this->_localehelper = $this->createMock(LocaleList::class);
        $this->_logger = $this->createMock(Logger::class);
        $this->_cache = $this->createMock(CacheInterface::class);

        $this->_catalogProductSaveObserver = new CatalogProductSaveObserver(
            $this->_productloader,
            $this->_pinterestHelper,
            $this->_messageManager,
            $this->_localehelper,
            $this->_catalogFeedClient,
            $this->_logger,
            $this->_cache
        );
    }
    public function testExecute()
    {
        $all_data = [];
        $observer = new Observer([
            "product" => [
                "entity_id" => 4,
                "store_id" => 1,
                "price" => "12.0",
                "special_price" => "8.0",
                "quantity_and_stock_status" => [
                    "qty" => "0"
                ],
                "stock_data" => [
                    "is_in_stock"=> "1",
                    "qty" => "1"
                ],
                "sku" => "111"
            ]
        ]);

        $cacheValue = json_encode([
            "item_id"  => "111",
            "attributes" => [
                "price" => "12.0 USD",
                "sale_price" => "8.0 USD",
                "availability" => "in stock"
            ],
        ]);
        $this->_localehelper->method('getCurrency')->willReturn("USD");
        $this->_localehelper->method('getLocale')->willReturn("en_US");
        $this->_catalogFeedClient->method('isUserConnected')->willReturn(true);
        $this->_catalogFeedClient->method('updateCatalogItems')->willReturn(true);
        $this->_pinterestHelper->method('isCatalogAndRealtimeUpdatesEnabled')->willReturn(true);

        $success = $this->_catalogProductSaveObserver->execute($observer);
        $this->assertTrue($success);
        $this->assertEquals($cacheValue, $this->_catalogProductSaveObserver->data_for_unittest);
    }
}
