<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\ConversionEventHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;

class ConversionEventHelperTest extends TestCase
{
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
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var CustomerDataHelper
     */
    protected $_customerDataHelper;

    /**
     * @var CacheInterface
     */
    protected $cache;

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
        $this->_conversionEventHelper = new ConversionEventHelper(
            $this->_request,
            $this->_pinterestHttpClient,
            $this->_pinterestHelper,
            $this->_customerDataHelper,
            $this->_cache
        );
    }

    public function testEventDataGetsPopulatedCorrectly()
    {
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->assertEquals(7, count($payload));
        $this->assertEquals("sample_event_id", $payload["event_id"]);
        $this->assertEquals("sample_event_name", $payload["event_name"]);
        $this->assertEquals("ss-adobe", $payload["partner_name"]);
        $this->assertEquals("ss-adobe", $payload["custom_data"]["np"]);
    }

    public function testDefaultCustomValuesFieldsOverrideInput()
    {
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "np" => "Salesforce"
        ]);
        $this->assertEquals("ss-adobe", $payload["custom_data"]["np"]);
    }

    public function testCustomFieldsCanBeAddedTo()
    {

        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);

        // 1 NP data
        // 2 search Term
        $this->assertEquals(2, count($payload["custom_data"]));
    }

    public function testUserAgentInfoIsPresentInEventPayload()
    {
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $userAgentInfo = $payload["user_data"]["client_user_agent"];
        $this->assertEquals("safari", $userAgentInfo);
    }

    public function testUserIPAddressIsPresentInEventPayload()
    {
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $userIpAddress = $payload["user_data"]["client_ip_address"];
        $this->assertEquals("0.0.0.0", $userIpAddress);
    }

    public function testLoggedInUserEventPayload()
    {
        $this->_customerDataHelper->method("isUserLoggedIn")->willReturn(1);
        $this->_customerDataHelper->method("getEmail")->willReturn("test@pins.com");
        $this->_customerDataHelper->method("getFirstName")->willReturn("Tony");
        $this->_customerDataHelper->method("getLastName")->willReturn("Stark");
        $this->_customerDataHelper->method("getDateOfBirth")->willReturn("19650404");
        $this->_customerDataHelper->method("getGender")->willReturn("m");
        $this->_customerDataHelper
            ->method("hash")
            ->will($this->returnValueMap([
                ["test@pins.com", "test@pins.com"],
                ["Tony", "Tony"],
                ["Stark", "Stark"],
                ["19650404", "19650404"],
                ["m", "m"],
            ]));

        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $user_data = $payload["user_data"];
        $this->assertEquals("test@pins.com", $user_data["em"][0]);
        $this->assertEquals("Tony", $user_data["fn"][0]);
        $this->assertEquals("Stark", $user_data["ln"][0]);
        $this->assertEquals("19650404", $user_data["db"][0]);
        $this->assertEquals("m", $user_data["ge"][0]);
    }

    public function testLoggedOutUserEventPayload()
    {
        $this->_customerDataHelper->method("isUserLoggedIn")->willReturn(0);
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $user_data = $payload["user_data"];
        // User Agent and Ip Address
        $this->assertEquals(2, count($user_data));
    }

    public function testEventDataisQueuedIfConditionsAreNotMet()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $this->_cache->expects($this->once())->method("save");
        $this->_cache->method("load")->willReturn($this->_conversionEventHelper->getInitialCacheState());
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testEventDataisFlushedIfMoreThan60seconds()
    {
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_cache->expects($this->once())->method("save");
        $oldTime = time() - (5*60);
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => $oldTime, "data" => []]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testEventDataisFlushedIfMoreThan500events()
    {
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_cache->expects($this->once())->method("save");
        $data = [];
        for ($i = 0; $i <= 500; $i++) {
            $data[] = ["event_id" => "sampleEvent"];
        }
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => time() - 1, "data" => $data]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }
}
