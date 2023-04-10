<?php
namespace Pinterest\PinterestBusinessConnectPlugin\Helper;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHttpClient;
use Pinterest\PinterestBusinessConnectPlugin\Helper\CatalogFeedClient;
use Pinterest\PinterestBusinessConnectPlugin\Helper\SavedFile;
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
     *
     * @param Context $context
     * @param PinterestHelper $pinterestHelper
     * @param PinterestHttpClient $pinterestHttpClient
     * @param CatalogFeedClient $catalogFeedClient
     * @param JsonFactory $resultJsonFactory
     * @param SavedFile $savedFile
     */
    public function __construct(
        Context $context,
        PinterestHelper $pinterestHelper,
        PinterestHttpClient $pinterestHttpClient,
        CatalogFeedClient $catalogFeedClient,
        JsonFactory $resultJsonFactory,
        SavedFile $savedFile
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_savedFile = $savedFile;
    }

    /**
     * Delete the metadata from Pinterest
     *
     * @return bool if the call was successful
     */
    private function deleteMetadataFromPinterest()
    {
        $this->_pinterestHelper->logInfo("Deleting metadata from Pinterest");
        try {
            $businessAccount = $this->_pinterestHelper->getExternalBusinessId();
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce/".$businessAccount);
            $response = $this->_pinterestHttpClient->delete($url, $this->_pinterestHelper->getAccessToken());
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
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Delete all feeds created in Magento
     *
     * @return bool if the call was successful
     */
    private function deleteFeedsFromPinterest()
    {
        $this->_pinterestHelper->logInfo("Deleting feeds from Pinterest");
        $feedIds = $this->_pinterestHelper->getMetadataValue("pinterest/info/feed_ids") ?
            json_decode($this->_pinterestHelper->getMetadataValue("pinterest/info/feed_ids")) :
            [];
        $unsuccessfulDeletes = [];
        foreach ($feedIds as $feedId) {
            if (!$this->_catalogFeedClient->deleteFeed($feedId)) {
                $unsuccessfulDeletes[] = $feedId;
            }
        }
        // Update db state
        if (count($unsuccessfulDeletes) == 0) {
            $this->_pinterestHelper->deleteMetadata("pinterest/info/feed_ids");
        } else {
            $this->_pinterestHelper->saveMetadata("pinterest/info/feed_ids", json_encode($unsuccessfulDeletes));
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
     * @return bool successful
     */
    public function disconnectAndCleanup()
    {
        if ($this->_pinterestHelper->isUserConnected()) {
            $error_types = [];
            $this->_pinterestHelper->logInfo("Attemping to disconnect from Pinterest");

            $successDeleteFeeds = $this->deleteFeedsFromPinterest();
            if (!$successDeleteFeeds) {
                array_push($error_types, "deleteFeedsFromPinterest");
            };
            $success = $successDeleteFeeds;

            $successDeletePinterestMetadata = $this->deleteMetadataFromPinterest();
            if (!$successDeletePinterestMetadata) {
                array_push($error_types, "deletePinterestMetadata");
            };
            $success &= $successDeletePinterestMetadata;

            $successDeletePluginMetadata = $this->_pinterestHelper->deleteAllMetadata();
            if (!$successDeletePluginMetadata) {
                array_push($error_types, "deletePluginMetadata");
            };
            $success &= $successDeletePluginMetadata;

            $this->_pinterestHelper->logInfo("Deleting all catalogs from: ".SavedFile::DIRECTORY_NAME_PATH);
            $this->_savedFile->deleteCatalogs();
            $this->_pinterestHelper->logInfo("flush cache during disconnect");
            $this->_pinterestHelper->flushCache();

            $result = $this->_resultJsonFactory->create();
            $result->setData(["errorTypes" => $error_types, "success" => $success]);
            return $result;
        }

        $this->_pinterestHelper->logInfo("disconnectAndCleanup called without user being connected.");
        return true;
    }
}
