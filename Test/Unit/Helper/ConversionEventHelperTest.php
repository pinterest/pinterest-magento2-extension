<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\ConversionEventHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\Stdlib\CookieManagerInterface;
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
    protected $_cache;

    /**
     * @var CookieManagerInterface
     */
    protected $_customCookieManager;

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

    public function testUserEpikIdInEventPayload()
    {
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $userExternalId = $payload["user_data"]["click_id"];
        $this->assertEquals("randomCookieValue", $userExternalId);
    }
    
    public function testUserExternalIdInEventPayload()
    {
        $this->_customerDataHelper
            ->method("hash")
            ->will($this->returnValueMap([
                ["randomCookieValue", "randomCookieValue"]
            ]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $userExternalId = $payload["user_data"]["external_id"];
        $this->assertEquals("randomCookieValue", $userExternalId[0]);
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

    public function testBillingAddressPayload()
    {
        $this->_customerDataHelper->method("getCity")->willReturn("sanfrancisco");
        $this->_customerDataHelper->method("getState")->willReturn("ca");
        $this->_customerDataHelper->method("getCountry")->willReturn("US");
        $this->_customerDataHelper->method("getZipCode")->willReturn("94107");
        $this->_customerDataHelper
            ->method("hash")
            ->will($this->returnValueMap([
                ["sanfrancisco", "sanfrancisco"],
                ["ca", "ca"],
                ["US", "US"],
                ["94107", "94107"]
            ]));

        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $user_data = $payload["user_data"];
        $this->assertEquals("sanfrancisco", $user_data["ct"][0]);
        $this->assertEquals("ca", $user_data["st"][0]);
        $this->assertEquals("US", $user_data["country"][0]);
        $this->assertEquals("94107", $user_data["zp"][0]);
    }

    public function testLoggedOutUserEventPayload()
    {
        $this->_customerDataHelper->method("isUserLoggedIn")->willReturn(0);
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", [
            "search_term" => "Pants"
        ]);
        $user_data = $payload["user_data"];
        // User Agent, Ip Address, External Id and Epik Id
        $this->assertEquals(4, count($user_data));
    }

    public function testEventDataisQueuedIfConditionsAreNotMet()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $this->_cache->expects($this->once())->method("save");
        $this->_cache->method("load")->willReturn($this->_conversionEventHelper->getInitialCacheState());
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testEventDataisNotQueuedIfBot()
    {
        $mock_request = $this->createMock(Http::class);
        $mock_request->method("getServer")->willReturn("Pinterestbot");
        $conversionEventHelper = new ConversionEventHelper(
            $mock_request,
            $this->_pinterestHttpClient,
            $this->_pinterestHelper,
            $this->_customerDataHelper,
            $this->_cache,
            $this->_customCookieManager
        );
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $this->_cache->expects($this->never())->method("save");
        $payload = $conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $conversionEventHelper->enqueueEvent($payload);
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

    public function testPostEventWarningMessage()
    {
        $responseData = [
            "num_events_processed" => 1,
            "num_events_received" => 1,
            "events" => [
                [
                    "status" => "processed",
                    "error_message" => "",
                    "warning_message" => "'external_id' is not in sha256 hashed format"
                ]
            ]
        ];
        $responseData = json_decode(json_encode($responseData), false);
        $this->_pinterestHttpClient->method("post")->willReturn($responseData);
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_pinterestHelper->expects($this->once())->method("logWarning");
        $this->_pinterestHelper->expects($this->never())->method("logError");
        $data = [];
        for ($i = 0; $i <= 500; $i++) {
            $data[] = ["event_id" => "sampleEvent"];
        }
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => time() - 1, "data" => $data]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testPostEventNoMessage()
    {
        $responseData = [
            "num_events_processed" => 1,
            "num_events_received" => 1,
            "events" => [
                [
                    "status" => "processed",
                    "error_message" => "",
                    "warning_message" => ""
                ]
            ]
        ];
        $responseData = json_decode(json_encode($responseData), false);
        $this->_pinterestHttpClient->method("post")->willReturn($responseData);
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_pinterestHelper->expects($this->never())->method("logWarning");
        $this->_pinterestHelper->expects($this->never())->method("logError");
        $data = [];
        for ($i = 0; $i <= 500; $i++) {
            $data[] = ["event_id" => "sampleEvent"];
        }
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => time() - 1, "data" => $data]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testPostEventErrorMessage()
    {
        $responseData = [
            "num_events_processed" => 1,
            "num_events_received" => 1,
            "events" => [
                [
                    "status" => "processed",
                    "error_message" => "ERROR MESSAGE TEST",
                    "warning_message" => ""
                ]
            ]
        ];
        $responseData = json_decode(json_encode($responseData), false);
        $this->_pinterestHttpClient->method("post")->willReturn($responseData);
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_pinterestHelper->expects($this->never())->method("logWarning");
        $this->_pinterestHelper->expects($this->once())->method("logError");
        $data = [];
        for ($i = 0; $i <= 500; $i++) {
            $data[] = ["event_id" => "sampleEvent"];
        }
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => time() - 1, "data" => $data]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }

    public function testPostEventMultipleMessages()
    {
        $responseData = [
            "num_events_processed" => 1,
            "num_events_received" => 1,
            "events" => [
                [
                    "status" => "processed",
                    "error_message" => "ERROR MESSAGE TEST",
                    "warning_message" => ""
                ],
                [
                    "status" => "processed",
                    "error_message" => "",
                    "warning_message" => "'external_id' is not in sha256 hashed format"
                ]
            ]
        ];
        $responseData = json_decode(json_encode($responseData), false);
        $this->_pinterestHttpClient->method("post")->willReturn($responseData);
        $this->_pinterestHttpClient->expects($this->once())->method("post");
        $this->_pinterestHelper->expects($this->once())->method("logWarning");
        $this->_pinterestHelper->expects($this->once())->method("logError");
        $data = [];
        for ($i = 0; $i <= 500; $i++) {
            $data[] = ["event_id" => "sampleEvent"];
        }
        $this->_cache->method("load")->willReturn(json_encode(["start_time" => time() - 1, "data" => $data]));
        $payload = $this->_conversionEventHelper->createEventPayload("sample_event_id", "sample_event_name", []);
        $this->_conversionEventHelper->enqueueEvent($payload);
    }
}
