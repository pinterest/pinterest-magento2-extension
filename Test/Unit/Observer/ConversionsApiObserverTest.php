<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Observer;

use Pinterest\PinterestMagento2Extension\Observer\ConversionsApiObserver;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\ConversionEventHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Event\Observer;

class ConversionsApiObserverTest extends TestCase
{

    /**
     * @var ConversionsApiObserver
     */
    protected $_conversionsApiObserver;

    /**
     * @var ConversionEventHelper
     */
    protected $_conversionEventHelper;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var Http
     */
    protected $_request;

    /**
     * @var ConversionEventHelper
     */
    protected $_pinterestHttpClient;

    /**
     * @var PinterestHttpClient
     */
    protected $_customerDataHelper;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var CookieManagerInterface
     */
    protected $_customCookieManager;

    /**
     * Used to set the values before running a test
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_pinterestHelper->method("getMetadataValue")->willReturn("1234");
        $this->_request = $this->createMock(Http::class);
        $this->_request->method("getServer")->willReturn("safari");
        $this->_request->method("getClientIp")->willReturn("0.0.0.0");
        $this->_pinterestHttpClient = $this->createMock(PinterestHttpClient::class);
        $this->_customerDataHelper = $this->createMock(CustomerDataHelper::class);
        $this->_cache = $this->createMock(CacheInterface::class);
        $this->_customCookieManager = $this->createMock(CookieManagerInterface::class);
        $this->_customCookieManager->method('getCookie')->willReturn("randomCookieValue");
        $this->_conversionEventHelper = new ConversionEventHelper(
            $this->_request,
            $this->_pinterestHttpClient,
            $this->_pinterestHelper,
            $this->_customerDataHelper,
            $this->_cache,
            $this->_customCookieManager
        );
        $this->_conversionsApiObserver = new ConversionsApiObserver($this->_conversionEventHelper);
    }

    public function testPageVisitEventCreated()
    {
        $observer = new Observer([
            "event_id" => "1234",
            "event_name" => "page_visit",
            "custom_data" => [
                "content_ids" => ["sample_id"],
                "contents" => [[
                    "item_price"=>"99.00"
                ]],
                "currency" => "USD"
            ]

        ]);
        $this->_conversionsApiObserver->execute($observer);
        $event_data = $this->_conversionEventHelper->getLastEventEnqueued();
        $this->assertEquals("1234", $event_data["event_id"]);
        $this->assertEquals("page_visit", $event_data["event_name"]);
        $this->assertEquals("ss-adobe", $event_data["custom_data"]["np"]);
        $this->assertEquals(1, count($event_data["custom_data"]["content_ids"]));
        $this->assertEquals("sample_id", $event_data["custom_data"]["content_ids"][0]);
        $this->assertEquals(1, count($event_data["custom_data"]["contents"]));
        $this->assertEquals("USD", $event_data["custom_data"]["currency"]);
        $this->assertEquals("99.00", $event_data["custom_data"]["contents"][0]["item_price"]);
    }

    public function testSearchEventCreated()
    {
        $observer = new Observer([
            "event_id" => "1234",
            "event_name" => "search",
            "custom_data" => [
                "search_string" => "pants"
            ]
        ]);

        $this->_conversionsApiObserver->execute($observer);
        $event_data = $this->_conversionEventHelper->getLastEventEnqueued();
        $this->assertEquals("1234", $event_data["event_id"]);
        $this->assertEquals("search", $event_data["event_name"]);
        $this->assertEquals("pants", $event_data["custom_data"]["search_string"]);
        $this->assertEquals("ss-adobe", $event_data["custom_data"]["np"]);
    }

    public function testCheckoutEventCreated()
    {
        $observer = new Observer([
            "event_id" => "1234",
            "event_name" => "checkout",
            "custom_data" => [
                "content_ids" => ["sample_id", "sample_id_2"],
                "contents" => [[
                    "item_price"=>"99.00",
                    "quantity" => 3
                ], [
                    "item_price"=>"49.00",
                    "quantity" => 1
                ]],
                "value" => "346.00",
                "num_items" => 2,
                "currency" => "USD"
            ],
        ]);

        $this->_conversionsApiObserver->execute($observer);
        $event_data = $this->_conversionEventHelper->getLastEventEnqueued();
        $this->assertEquals("1234", $event_data["event_id"]);
        $this->assertEquals("checkout", $event_data["event_name"]);
        $this->assertEquals("ss-adobe", $event_data["custom_data"]["np"]);

        // Content Ids
        $this->assertEquals(2, count($event_data["custom_data"]["content_ids"]));
        $this->assertEquals("sample_id", $event_data["custom_data"]["content_ids"][0]);
        $this->assertEquals("sample_id_2", $event_data["custom_data"]["content_ids"][1]);

        // Contents
        $contents = $event_data["custom_data"]["contents"];
        $this->assertEquals(2, count($contents));
        $this->assertEquals("99.00", $contents[0]["item_price"]);
        $this->assertEquals(3, $contents[0]["quantity"]);
        $this->assertEquals("49.00", $contents[1]["item_price"]);
        $this->assertEquals(1, $contents[1]["quantity"]);
        
        $this->assertEquals("346.00", $event_data["custom_data"]["value"]);
        $this->assertEquals(2, $event_data["custom_data"]["num_items"]);
        $this->assertEquals("USD", $event_data["custom_data"]["currency"]);
    }

    public function testAddToCartEventCreated()
    {
        $observer = new Observer([
            "event_id" => "1234",
            "event_name" => "add_to_cart",
            "custom_data" => [
                "content_ids" => ["sample_id", "sample_id_2"],
                "num_items" => 3,
                "value" => "34.89",
                "currency" => "USD",
            ],
        ]);

        $this->_conversionsApiObserver->execute($observer);
        $event_data = $this->_conversionEventHelper->getLastEventEnqueued();
        $this->assertEquals("1234", $event_data["event_id"]);
        $this->assertEquals(2, count($event_data["custom_data"]["content_ids"]));
        $this->assertEquals("sample_id", $event_data["custom_data"]["content_ids"][0]);
        $this->assertEquals("sample_id_2", $event_data["custom_data"]["content_ids"][1]);
        $this->assertEquals("34.89", $event_data["custom_data"]["value"]);
        $this->assertEquals(3, $event_data["custom_data"]["num_items"]);
        $this->assertEquals("USD", $event_data["custom_data"]["currency"]);
        $this->assertEquals("add_to_cart", $event_data["event_name"]);
        $this->assertEquals("ss-adobe", $event_data["custom_data"]["np"]);
    }
}
