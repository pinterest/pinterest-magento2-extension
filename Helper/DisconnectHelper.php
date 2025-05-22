<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;
use Pinterest\PinterestMagento2Extension\Helper\LoggingHelper;
use Magento\Framework\Controller\Result\JsonFactory;

class DisconnectHelper
{

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var PinterestHttpClient
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

    /**
     *
     * @param Context $context
     * @param PinterestHelper $pinterestHelper
     * @param PinterestHttpClient $pinterestHttpClient
     * @param CatalogFeedClient $catalogFeedClient
     * @param JsonFactory $resultJsonFactory
     * @param SavedFile $savedFile
     * @param LoggingHelper $loggingHelper
     */
    public function __construct(
        Context $context,
        PinterestHelper $pinterestHelper,
        PinterestHttpClient $pinterestHttpClient,
        CatalogFeedClient $catalogFeedClient,
        JsonFactory $resultJsonFactory,
        SavedFile $savedFile,
        LoggingHelper $loggingHelper
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_savedFile = $savedFile;
        $this->_loggingHelper = $loggingHelper;
    }

    /**
     * Delete the metadata from Pinterest
     *
     * @return bool if the call was successful
     */
    private function deleteMetadataFromPinterest($storeId = null)
    {
        $this->_pinterestHelper->logInfo("Deleting metadata from Pinterest");
        try {
            $businessAccount = $this->_pinterestHelper->getExternalBusinessId($storeId);
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce/".$businessAccount);
            $response = $this->_pinterestHttpClient->delete($url, $this->_pinterestHelper->getAccessToken($storeId));
            if ($response->getStatusCode() === 204 || $response->getStatusCode() === 404) {
                /**
                 * If status code is 204, then we have deleted the metadata successfully
                 * If status code is 404, then we can no longer find the metadata. It might have been
                 * delete by another API call
                */
                $this->_pinterestHelper->logInfo("Succesfully deleted metadata from Pinterest for $businessAccount");
                return true;
            } else {
                $this->_pinterestHelper->logError("Could not delete metadata from Pinterest with
                 status code:" . $response->getStatusCode());
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while deleting metadata from Pinterest");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Delete all feeds for a store
     * 
     * @param string $storeId
     * @return bool if the call was successful
     */
    public function deleteFeedsFromPinterest($storeId = null)
    {
        $baseMetadataKey = "pinterest/info/feed_ids";
        $metadataFeedKey = $storeId != null ? $baseMetadataKey . "/{$storeId}" : $baseMetadataKey;
        $this->_pinterestHelper->logInfo("Deleting feeds from Pinterest");
        $feedIds = $this->_pinterestHelper->getMetadataValue($metadataFeedKey) ?
            json_decode($this->_pinterestHelper->getMetadataValue($metadataFeedKey)) :
            [];
        $unsuccessfulDeletes = [];
        foreach ($feedIds as $feedId) {
            if (!$this->_catalogFeedClient->deleteFeed($feedId, $storeId)) {
                $unsuccessfulDeletes[] = $feedId;
            }
        }
        // Update db state
        if (count($unsuccessfulDeletes) == 0) {
            $this->_pinterestHelper->deleteMetadata($metadataFeedKey);
        } else {
            $this->_pinterestHelper->saveMetadata($metadataFeedKey, json_encode($unsuccessfulDeletes));
        }
        return count($unsuccessfulDeletes) == 0;
    }

    /**
     * Execute the Disconnect flow
     *
     * Steps:
     * 1) Delete feeds from Pinterest
     * 2) Delete metadata fron Pinteret
     * 3) Delete local Metadata
     * 4) Delete catalog xml files
     * 5) Flush cache
     *
     * @param string $storeId
     * @return bool successful
     */
    public function disconnectAndCleanup($storeId = null)
    {
        if ($this->_pinterestHelper->isUserConnected($storeId)) {
            $error_types = [];
            $this->_pinterestHelper->logInfo("Attemping to disconnect from Pinterest");

            $successDeleteFeeds = $this->deleteFeedsFromPinterest($storeId);
            if (!$successDeleteFeeds) {
                array_push($error_types, "deleteFeedsFromPinterest");
            };
            $success = $successDeleteFeeds;

            $successDeletePinterestMetadata = $this->deleteMetadataFromPinterest($storeId);
            if (!$successDeletePinterestMetadata) {
                array_push($error_types, "deletePinterestMetadata");
            };
            $success &= $successDeletePinterestMetadata;

            // flush log cache before delete access token
            $this->_loggingHelper->flushCache($storeId);

            $successDeletePluginMetadata = false;
            if($storeId != null){
                $successDeletePluginMetadata = $this->_pinterestHelper->deleteMetadataForStore($storeId);
            } else {
                $successDeletePluginMetadata = $this->_pinterestHelper->deleteAllMetadata();
            }

            if (!$successDeletePluginMetadata) {
                array_push($error_types, "deletePluginMetadata");
            };
            $success &= $successDeletePluginMetadata;

            $this->_pinterestHelper->logInfo("Deleting all catalogs from: ".SavedFile::DIRECTORY_NAME_PATH);
            $this->_savedFile->deleteCatalogs($storeId);
            $this->_pinterestHelper->logInfo("flush cache during disconnect");
            $this->_pinterestHelper->flushCache($storeId);

            $result = $this->_resultJsonFactory->create();
            $result->setData(["errorTypes" => $error_types, "success" => $success]);
            return $result;
        }

        $this->_pinterestHelper->logInfo("disconnectAndCleanup called without user being connected.");
        return true;
    }
}
