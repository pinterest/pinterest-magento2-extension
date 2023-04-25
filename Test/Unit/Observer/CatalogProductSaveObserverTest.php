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
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productloader;

    /**
     *
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     *
     * @var LocaleList
     */
    protected $localehelper;

    /**
     *
     * @var CatalogFeedClient
     */
    protected $httpClient;

    /**
     *
     * @var Logger
     */
    protected $logger;

    /**
     *
     * @var CatalogProductSaveObserver
     */
    protected $catalogProductSaveObserver;

    /**
     * Used to set the values before running a test
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->productloader = $this->createMock(ProductRepositoryInterface::class);
        $this->pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->catalogFeedClient = $this->createMock(CatalogFeedClient::class);
        $this->localehelper = $this->createMock(LocaleList::class);
        $this->logger = $this->createMock(Logger::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->catalogProductSaveObserver = new CatalogProductSaveObserver(
            $this->productloader,
            $this->pinterestHelper,
            $this->messageManager,
            $this->localehelper,
            $this->catalogFeedClient,
            $this->logger,
            $this->cache
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
        $this->localehelper->method('getCurrency')->willReturn("USD");
        $this->localehelper->method('getLocale')->willReturn("en_US");
        $this->catalogFeedClient->method('isUserConnected')->willReturn(true);
        $this->catalogFeedClient->method('updateCatalogItems')->willReturn(true);
        $this->pinterestHelper->method('isCatalogAndRealtimeUpdatesEnabled')->willReturn(true);

        $success = $this->catalogProductSaveObserver->execute($observer);
        $this->assertTrue($success);
        $this->assertEquals($cacheValue, $this->catalogProductSaveObserver->data_for_unittest);
    }
}
