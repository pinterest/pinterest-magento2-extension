<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Magento\Backend\App\Action\Context;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use Pinterest\PinterestMagento2Extension\Helper\DisconnectHelper;
use Pinterest\PinterestMagento2Extension\Helper\LoggingHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Laminas\Http\Response;

class DisconnectHelperTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var DisconnectHelper
     */
    protected $_disconnectHelper;
    
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var Context
     */
    protected $_context;

    /**
     * @var PinteretHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var CatalogFeedClient
     */
    protected $_catalogFeedClient;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var SavedFile
     */
    protected $_savedFile;

    /**
     * @var LoggingHelper
     */
    protected $_loggingHelper;

    public function setUp() : void
    {
        $this->_context = $this->createMock(Context::class);
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_pinterestHttpClient = $this->createMock(PinterestHttpClient::class);
        $this->_catalogFeedClient = $this->createMock(CatalogFeedClient::class);
        $this->_resultJsonFactory = $this->createMock(JsonFactory::class);
        $this->_savedFile = $this->createMock(SavedFile::class);
        $this->_loggingHelper = $this->createMock(LoggingHelper::class);
        $this->_disconnectHelper = new DisconnectHelper(
            $this->_context,
            $this->_pinterestHelper,
            $this->_pinterestHttpClient,
            $this->_catalogFeedClient,
            $this->_resultJsonFactory,
            $this->_savedFile,
            $this->_loggingHelper,
        );
    }

    private function setJsonValidationMock($expectedSuccess, $expectedErrors)
    {
        $jsonMock = $this->createMock(Json::class);
        $jsonMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(
                    function ($args) use ($expectedSuccess, $expectedErrors) {
                        $errorsArray = $args['errorTypes'];
                        $success = $args['success'];
                        $this->assertSame($expectedErrors, $errorsArray);
                        $this->assertSame($expectedSuccess, $success);
                        return true;
            }))
            ->willReturnSelf();
        
        return $jsonMock;
    }

    public function testAvoidDeleteWhenUserIsNotLogged()
    {
        $this->_pinterestHelper->method('isUserConnected')->willReturn(false);
        $this->assertTrue($this->_disconnectHelper->disconnectAndCleanup());
    }

    public function testCollectErrors()
    {
        $this->_pinterestHelper->method('isUserConnected')->willReturn(true);
        $this->_resultJsonFactory->method('create')
        ->willReturn($this->setJsonValidationMock(0, ['deletePinterestMetadata', 'deletePluginMetadata']));
        $this->_disconnectHelper->disconnectAndCleanup();
    }

    public function testDisconnectFeeds()
    {
        $feedsMetadataKey = "pinterest/info/feed_ids";
        $testIntegrationsURL = 'pinterestURL';
        $testAccessToken = 'accessToken';
        // delete metadata from pinterest process
        $this->_pinterestHelper->expects($this->once())->method('getAccessToken')->with(null)->willReturn($testAccessToken);
        $this->_pinterestHelper->expects($this->once())->method('getExternalBusinessId')->with(null)->willReturn('businessId');
        $this->_pinterestHttpClient->expects($this->once())->method('getV5ApiEndpoint')->with('integrations/commerce/businessId')->willReturn($testIntegrationsURL);
        $responseMock = $this->createMock(Response::class);
        $responseMock->expects($this->once())->method('getStatusCode')->willReturn(204);
        $this->_pinterestHttpClient->expects($this->once())->method('delete')->with($testIntegrationsURL, $testAccessToken)->willReturn($responseMock);
        // delete feed process
        $this->_pinterestHelper->method('isUserConnected')->with(null)->willReturn(true);
        $this->_pinterestHelper->method('getMetadataValue')->with($feedsMetadataKey)->willReturn('[1,2,3]');
        $this->_pinterestHelper->expects($this->once())->method('deleteMetadata')->with($feedsMetadataKey);
        $this->_catalogFeedClient->expects($this->exactly(3))->method('deleteFeed')->willReturn(true);
        // delete local metadata
        $this->_pinterestHelper->expects($this->once())->method('deleteAllMetadata')->willReturn(true);
        // delete catalog
        $this->_savedFile->expects($this->once())->method('deleteCatalogs')->with(null);
        // delete caches
        $this->_loggingHelper->expects($this->once())->method('flushCache')->with(null);
        $this->_pinterestHelper->expects($this->once())->method('flushCache')->with(null);
        // test generated json
        $this->_resultJsonFactory->method('create')
        ->willReturn($this->setJsonValidationMock(1, []));
        // trigger test
        $this->_disconnectHelper->disconnectAndCleanup();
    }

    public function testDisconnectFeedsWithStoreId()
    {
        $storeId = '1';
        $expectedMetadataKey = "pinterest/info/feed_ids/1";
        $testIntegrationsURL = 'pinterestURL';
        $testAccessToken = 'accessToken';
        // delete metadata from pinterest process
        $this->_pinterestHelper->expects($this->once())->method('getAccessToken')->with($storeId)->willReturn($testAccessToken);
        $this->_pinterestHelper->expects($this->once())->method('getExternalBusinessId')->with($storeId)->willReturn('businessId');
        $this->_pinterestHttpClient->expects($this->once())->method('getV5ApiEndpoint')->with('integrations/commerce/businessId')->willReturn($testIntegrationsURL);
        $responseMock = $this->createMock(Response::class);
        $responseMock->expects($this->exactly(2))->method('getStatusCode')->willReturn(404);
        $this->_pinterestHttpClient->expects($this->once())->method('delete')->with($testIntegrationsURL, $testAccessToken)->willReturn($responseMock);
        // delete feed process
        $this->_pinterestHelper->method('isUserConnected')->with($storeId)->willReturn(true);
        $this->_pinterestHelper->method('getMetadataValue')->with($expectedMetadataKey)->willReturn('[1,2,3]');
        $this->_pinterestHelper->expects($this->once())->method('deleteMetadata')->with($expectedMetadataKey);
        $this->_catalogFeedClient->expects($this->exactly(3))->method('deleteFeed')->willReturn(true);
        // delete local metadata
        $this->_pinterestHelper->expects($this->once())->method('deleteMetadataForStore')->with($storeId)->willReturn(true);
        // delete catalogs
        $this->_savedFile->expects($this->once())->method('deleteCatalogs')->with($storeId);
        // test generated json
        $this->_resultJsonFactory->method('create')
        ->willReturn($this->setJsonValidationMock(1, []));
        // delete caches
        $this->_loggingHelper->expects($this->once())->method('flushCache')->with($storeId);
        $this->_pinterestHelper->expects($this->once())->method('flushCache')->with($storeId);
        // trigger test
        $this->_disconnectHelper->disconnectAndCleanup($storeId);
    }
}