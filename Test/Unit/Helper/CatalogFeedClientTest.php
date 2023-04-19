<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;

use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;

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
            []
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
            []
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
        $this->assertEquals(count($ret), 1);
        $this->assertEquals($ret[0]->id, "test");
        $this->assertEquals($ret[0]->name, "sameCatalog");
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

    public function testCreateFeedHelperNewInstallWithSameExistingFeeds()
    {
        $responseMock = $this->apiResponseMock();
        $responseMock->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->expects($this->once())->method("delete")->willReturn($responseMock);
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $existingPinterestFeeds = json_decode(json_encode([
            [
                "id" => "1234",
                "name" => $feedName
            ]
        ]));
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
        $existingPinterestFeeds = json_decode(json_encode([
            [
                "id" => "1234",
                "name" => $feedName
            ]
        ]));
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName
        ];
        $ret = $this->_catalogFeedClient->createMissingFeedsOnPinterest($data, $existingPinterestFeeds, ["1234"]);
        $this->assertTrue($ret);
    }

    public function testCreateFeedHelperSubsequentInstallWithNewFeeds()
    {
        $this->_pinterestHttpClient->expects($this->never())->method("delete");
        $this->_pinterestHttpClient->expects($this->once())->method("post")->willReturn(json_decode(CatalogFeedClientTest::POST_RESPONSE_200));
        $feedName = $this->_catalogFeedClient->getFeedName("en_US", "www.pinterest.com");
        $existingPinterestFeeds = json_decode(json_encode([
            [
                "id" => "1234",
                "name" => "random_name"
            ]
        ]));
        $data = [
            "location" => "www.pinterest.com",
            "name" => $feedName
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
        $existingPinterestFeeds = json_decode(json_encode([
            [
                "id" => "1234",
                "name" => $feedName
            ]
        ]));
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
