<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;

use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;

use Magento\Framework\Webapi\Exception as HTTPExceptionCodes;

class CatalogFeedClientTest extends TestCase
{
    /**
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var LocaleList
     */
    protected $_localeList;

    /**
     * @var SavedFile
     */
    protected $_savedFile;

    /**
     * @var CatalogFeedClient
     */
    protected $_catalogFeedClient;

    public function setUp() : void
    {
        $this->_pinterestHttpClient = $this->createMock(PinterestHttpClient::class);
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_localeList = $this->createMock(LocaleList::class);
        $this->_savedFile = $this->createMock(SavedFile::class);

        $this->_catalogFeedClient = new CatalogFeedClient(
            $this->_pinterestHttpClient,
            $this->_pinterestHelper,
            $this->_localeList,
            $this->_savedFile,
        );
    }

    private const POST_RESPONSE_200 = '{
        "id": "feed_US",
        "name": "test feed",
        "default_locale": "en_US",
        "default_country": "US",
        "default_currency": "USD",
        "default_availability": "IN_STOCK",
        "format": "XML",
        "location": "https://abc.com/media/Pinterest/catalogs/en_US/catalog.xml",
        "status": "ACTIVE"
      }';

    public function testCreateFeed()
    {
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $ret = $this->_catalogFeedClient->createFeed(
            "https://abc.com/media/Pinterest//catalogs/en_US/catalog.xml",
            [
                "location" => "www.pinterest.com",
                "name" => "testFeedName"
            ]
        );
        $this->assertTrue($ret);
    }

    public function testCreateFeedFailure()
    {
        $response_401 ='{
            "code": 29,
            "message": "You are not permitted to access that resource."
          }';
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode($response_401));
        $ret = $this->_catalogFeedClient->createFeed(
            "https://abc.com/media/Pinterest/catalogs/en_US/catalog.xml",
            [
                "location" => "www.pinterest.com",
                "name" => "testFeedName"
            ]
        );
        $this->assertFalse($ret);
    }

    public function testCreateAllFeedsSuccess()
    {
        $this->_localeList->method('getListLocaleForAllStores')->willReturn([1=>"US\nen_US", 2=>"GB\nen_GB"]);
        $this->_pinterestHelper->method('getMediaBaseUrlByStoreId')->willReturn("https://abc.com/");
        $this->_savedFile->method('getFileSystemPath')->willReturn("/dev/null");
        $this->_savedFile->method('getExportUrl')->willReturn("www.pinterest.com/media/Pinterest/catalogs");
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $ret = $this->_catalogFeedClient->createAllFeeds(true);
        $this->assertEquals(2, $ret);
    }

    public function testCreateAllFeedsSuccessWithAdsAccount()
    {
        $url = "www.pinterest.com/media/Pinterest/catalogs";
        $adAccountId = "549766267106";
        $locale = "en_US";
        $data = [
            "default_country" => "US",
            "default_locale" => $locale,
            "default_currency" => "USD",
            "format" => "XML",
            "location" => $url,
            "name" => $this->_catalogFeedClient->getFeedName($locale, $url)
        ];
        $queryParams = [
            "ad_account_id" => $adAccountId
        ];
        $this->_localeList->method('getListLocaleForAllStores')->willReturn([1=>"US\nen_US"]);
        $this->_localeList->method('getCurrency')->willReturn("USD");
        $this->_pinterestHelper->method('getMediaBaseUrlByStoreId')->willReturn("https://abc.com/");
        $this->_pinterestHelper->method('getAdvertiserId')->willReturn($adAccountId);
        $this->_savedFile->method('getFileSystemPath')->willReturn("/dev/null");
        $this->_savedFile->method('getExportUrl')->willReturn($url);
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $this->_pinterestHttpClient->expects($this->once())->method("post")->with(null, $data, '', null, 'application/json', null, $queryParams);
        $ret = $this->_catalogFeedClient->createAllFeeds(true);
        $this->assertEquals(1, $ret);
    }

    public function testCreateAllFeedsFailure()
    {
        $this->_localeList->method('getListLocaleForAllStores')->willReturn([1=>"US\nen_US", 2=>"GB\nen_GB"]);
        $this->_pinterestHelper->method('getMediaBaseUrlByStoreId')->willReturn("https://abc.com/");
        $this->_savedFile->method('getFileSystemPath')->willReturn("/dev/null");
        $this->_savedFile->method('getExportUrl')->willReturn("www.pinterest.com/media/Pinterest/catalogs");
        $response_401 ='{
                "code": 29,
                "message": "You are not permitted to access that resource."
              }';
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode($response_401));
        $ret = $this->_catalogFeedClient->createAllFeeds();
        $this->assertEquals(0, $ret);
    }

    public function testGetAllFeedsSuccess()
    {
        $response_200 =[
          "items" => [
            [
              "id" => "test",
              "name" => "sameCatalog"
            ]
          ]
        ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $ret = $this->_catalogFeedClient->getAllFeeds();
        $this->assertEquals(count($ret), 2);
        $this->assertEquals($ret["test"]->id, "test");
        $this->assertEquals($ret["test"]->name, "sameCatalog");
        $this->assertEquals($ret["sameCatalog"]->id, "test");
        $this->assertEquals($ret["sameCatalog"]->name, "sameCatalog");
    }

    private function apiResponseMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getStatusCode', 'getBody'])
                ->getMock();
    }

    public function testGetAllFeedsWithError()
    {
        $response_401 ='{
          "code": 29,
          "message": "You are not permitted to access that resource."
        }';
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode($response_401));
        $ret = $this->_catalogFeedClient->getAllFeeds();
        $this->assertEquals($ret, []);
    }

    public function testdeleteFeedsWithSuccess()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->method("delete")->willReturn($responseMock);
        $ret = $this->_catalogFeedClient->deleteFeed("1234");
        $this->assertTrue($ret);
    }

    public function testdeleteFeedsWith404Response()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(404);
        $this->_pinterestHttpClient->method("delete")->willReturn($responseMock);
        $ret = $this->_catalogFeedClient->deleteFeed("1234");
        $this->assertTrue($ret);
    }

    public function testdeleteFeedsWith500Response()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(500);
        $this->_pinterestHttpClient->method("delete")->willReturn($responseMock);
        $ret = $this->_catalogFeedClient->deleteFeed("1234");
        $this->assertFalse($ret);
    }

    public function testCreateFeedHelperNewInstallWithNoFeedId()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode((CatalogFeedClientTest::POST_RESPONSE_200)));
        $this->_pinterestHttpClient->expects($this->never())->method("patch");
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "name" => $feedName
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["feed_US"]);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperNewInstallWithCurrencyChanged()
    {
        
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $this->_pinterestHttpClient->expects($this->once())->method("patch")->willReturn(json_decode((CatalogFeedClientTest::POST_RESPONSE_200)));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "id" => "feed_US",
                "name" => $feedName,
                "default_currency" => "BRL"
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName,
            "default_currency" => "USD"
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["feed_US"]);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperIdMissingInFeed()
    {
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Missing id in Feed: Test");
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $response = [
            "items" => [
              [
                "name" => "Test",
              ]
            ]
          ];
          $existingPinterestFeeds = [];
        foreach (json_decode(json_encode($response))->items as $item) {
            $existingPinterestFeeds[$item->name]= $item;
        }
        $data = [
            "location" => "www.pinterest.com",
            "name" => "Test"
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["Test"]);
    }

    public function testCreateFeedHelperNewInstallWithSameExistingFeeds()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->expects($this->once())->method("delete")->willReturn($responseMock);
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "id" => "1234",
                "name" => $feedName
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $ret = $this->_catalogFeedClient->createFeedsForNewInstall([
            "location" => "www.pinterest.com",
            "name" => $feedName
        ], $existingPinterestFeeds);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperNewInstallWithDifferentExistingFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $existingPinterestFeeds = json_decode(json_encode([
            [
                "id" => "1234",
                "name" => "Randome_name"
            ]
        ]));
        $ret = $this->_catalogFeedClient->createFeedsForNewInstall([
            "location" => "www.pinterest.com",
            "name" => "testFeedName"
        ], $existingPinterestFeeds);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperSubsequentInstallWithSameFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->never())->method("post");
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "id" => "1234",
                "name" => $feedName,
                "default_currency" => "USD"
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName,
            "default_currency" => "USD"
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["1234"]);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperSubsequentInstallWithNewFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "id" => "1234",
                "name" => "random_name",
                "default_currency" => "USD"
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName,
            "default_currency" => "USD"
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["1234"]);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperSubsequentInstallWithNewFeedsWithPinterestClean()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->expects($this->once())->method("delete")->willReturn($responseMock);
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $response_200 =[
            "items" => [
              [
                "id" => "1234",
                "name" => $feedName
              ]
            ]
          ];
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode($response_200)));
        $existingPinterestFeeds = $this->_catalogFeedClient->getAllFeeds();
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, []);
        $this->assertTrue($ret);
    }

    public function testDeleteStaleFeedsWithoutAnyStaleFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_catalogFeedClient->deleteStaleFeedsFromPinterest([], ["1234", "1235"]);
    }

    public function testDeleteStaleFeedsWithoutAnyStaleFeedsWithExistingFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_catalogFeedClient->deleteStaleFeedsFromPinterest(["1234"], ["1234", "1235"]);
    }

    public function testDeleteStaleFeedsWithoutAnyStaleFeedsWithExistingFeedsAndNoNewFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_catalogFeedClient->deleteStaleFeedsFromPinterest(["1234", "1235"], ["1234", "1235"]);
    }

    public function testDeleteStaleFeedsWithStaleFeeds()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->expects($this->once())->method("delete")->willReturn($responseMock);
        $this->_catalogFeedClient->deleteStaleFeedsFromPinterest(["1234", "1235", "1236"], ["1234", "1235"]);
    }
}
