<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Request\Http;
use Pinterest\PinterestMagento2Extension\Constants\IntegrationErrorId;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;

class CatalogFeedClient
{
    public const ADS_SUPPORTED_COUNTRIES = [
        "AR", "AU", "AT", "BE", "BR", "CA", "CL", "CY", "DK", "FI", "FR", "DE", "GR", "HU", "IE",
        "IT", "JP", "LU", "MT", "MX", "NL", "NZ", "NO", "PL", "PT", "RO", "SK", "ES", "SE", "CH",
        "GB", "US", "CO", "CZ"
    ];

    /**
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var PluginErrorHelper
     */
    protected $_pluginErrorHelper;

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
     * @var array
     */
    protected $feedIds;

    /**
     * @var array
     */
    protected $feedsRegisteredOnPinterest;

    /**
     * Default constructor
     *
     * @param PinterestHttpClient $pinterestHttpClient
     * @param PinterestHelper $pinterestHelper
     * @param LocaleList $localeList
     * @param SavedFile $savedFile
     * @param PluginErrorHelper $pluginErrorHelper
     */
    public function __construct(
        PinterestHttpClient $pinterestHttpClient,
        PinterestHelper $pinterestHelper,
        LocaleList $localeList,
        SavedFile $savedFile,
        PluginErrorHelper $pluginErrorHelper
    ) {
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_localeList = $localeList;
        $this->_savedFile = $savedFile;
        $this->feedsRegisteredOnPinterest = [];
        $this->_pluginErrorHelper = $pluginErrorHelper;
    }

    /**
     * Validates response codes
     *
     * @param string code
     */
    private function validateErrorCode($code)
    {
        return in_array($code, [2625]);
    }

    /**
     * Get the key to store the feed ids in metadata
     *
     * @param int $storeId
     * @return string
     */
    private function getFeedIdsInfoKey($storeId = null)
    {
        return "pinterest/info/feed_ids" . ($storeId != null ? "/{$storeId}" : "");
    }

    /**
     * Get all the feeds registered on Pinterest
     *
     * @return array
     */
    private function getAllExistingFeeds($storeId = null)
    {
        $key = $this->getFeedIdsInfoKey($storeId);
        return $this->_pinterestHelper->getMetadataValue($key) ?
        json_decode($this->_pinterestHelper->getMetadataValue($key)) :
        [];
    }

    /**
     * Update the existing feeds
     *
     * @param bool $newInstall
     * @param array $existingFeedsSavedToMetadata
     */
    private function updateExistingFeeds($newInstall, $existingFeedsSavedToMetadata, $storeId = null)
    {
        if (!$newInstall) {
            $this->deleteStaleFeedsFromPinterest($existingFeedsSavedToMetadata, $this->feedsRegisteredOnPinterest, $storeId);
        }
        $this->_pinterestHelper->saveMetadata($this->getFeedIdsInfoKey($storeId), json_encode(
            array_unique($this->feedsRegisteredOnPinterest)
        ));
        $this->feedsRegisteredOnPinterest = [];
    }

    /**
     * Create all the feeds based on the number of locales
     *
     * @param bool $newInstall
     */
    public function createAllFeeds($newInstall = true)
    {
        $created = [];
        $success_count = 0;
        $country_locales = $this->_localeList->getListLocaleForAllStores();
        $isMultistore = $this->_pinterestHelper->isMultistoreOn();
        $mappedStores = $isMultistore ? $this->_pinterestHelper->getMappedStores() : [];
        $existingPinterestFeeds = $isMultistore ? [] : $this->getAllFeeds();
        $existingFeedsSavedToMetadata = $isMultistore ? [] :  $this->getAllExistingFeeds();

        $this->_pinterestHelper->logInfo("Country locales to register feeds for: ".implode(" ", $country_locales));
        if (!$isMultistore) {
            $this->_pinterestHelper->logInfo("Existing feeds saved to magento metadata: ".implode(" ", $existingFeedsSavedToMetadata));
        }
        foreach ($country_locales as $storeId => $country_locale) {
            $this->_pinterestHelper->logInfo("Processing store feed for store: {$storeId}");
            if ($isMultistore) {
                if (!in_array($storeId, $mappedStores)) {
                    $this->_pinterestHelper->logInfo("Store {$storeId} is not mapped. Skipping");
                    continue;
                }
                $existingPinterestFeeds = $this->getAllFeeds($storeId);
                $existingFeedsSavedToMetadata = $this->getAllExistingFeeds($storeId);
                $this->_pinterestHelper->logInfo("Existing feeds saved to magento metadata: ".implode(" ", $existingFeedsSavedToMetadata));
            }
            $baseUrl = $this->_pinterestHelper->getMediaBaseUrlByStoreId($storeId);
            $pair = explode("\n", $country_locale);
            $country = $pair[0];
            $locale = $pair[1];
            $currency = $this->_localeList->getCurrency($storeId);
            $url = $this->_savedFile->getExportUrl($baseUrl, $locale, $isMultistore ? $storeId : null);
            $filename = $this->_savedFile->getFileSystemPath($baseUrl, $locale, false, $isMultistore ? $storeId : null);
            if (! is_readable($filename)) {
                $this->_pinterestHelper->logError("Can't read feed file {$filename}, skipping.");
                continue;
            }

            $key = $baseUrl.$locale . ($isMultistore ? $storeId : "");
            if (array_key_exists($key, $created)) {
                continue;
            }

            if (!in_array($country, self::ADS_SUPPORTED_COUNTRIES)) {
                $this->_pinterestHelper->logInfo("Country {$country} is not supported for ads on Pinterest. Skipping");
                continue;
            }

            $this->_pinterestHelper->logInfo("Creating catalog feed for {$country}/{$locale} with {$url}");
            $country = substr(strtoupper($country), 0, 2);
            $feedName = $this->getFeedName($locale, $url, $isMultistore ? $storeId : null);
            $adAccountId = $this->_pinterestHelper->getAdvertiserId($isMultistore ? $storeId : null);
            $queryParams = [
                "ad_account_id" => $adAccountId
            ];
            $data = [
                "default_country" => $country,
                "default_locale" => $locale,
                "default_currency" => $currency,
                "format" => "XML",
                "location" => $url,
                "name" => $feedName
            ];

            if ($newInstall) {
                $success = $this->createFeedsForNewInstall(
                    $data,
                    $existingPinterestFeeds,
                    $queryParams,
                    $storeId
                );
            } else {
                $success = $this->createMissingFeedsOnPinterest(
                    $data,
                    $existingPinterestFeeds,
                    $existingFeedsSavedToMetadata,
                    $queryParams,
                    $storeId
                );
            }
            $success_count += $success ? 1 : 0;
            $created[$key] = 1;
            if ($isMultistore) {
                $this->updateExistingFeeds($newInstall, $existingFeedsSavedToMetadata, $storeId);
            }
        }
        if (!$isMultistore) {
            $this->updateExistingFeeds($newInstall, $existingFeedsSavedToMetadata);
        }
        return $success_count;
    }

    /**
     * Deletes stale feeds from Pinterest
     *
     * It checks if there is any feedName in the previous snapshot (on magento)
     * that is not present in the latest snapshot.
     * If that is the case, we delete that feedName
     *
     * @param array $existingFeedsSavedToMetadata
     * @param array $feedsRegisteredOnPinterest
     */
    public function deleteStaleFeedsFromPinterest($existingFeedsSavedToMetadata, $feedsRegisteredOnPinterest, $storeId = null)
    {
        foreach ($existingFeedsSavedToMetadata as $feedId) {
            /* If the feedname is not in the latest snapshot of feeds registered on Pinterest,
                that means that it is stale and must be deleted */
            if (!in_array($feedId, $feedsRegisteredOnPinterest)) {
                $this->_pinterestHelper->logInfo("Should remove feed with id {$feedId} as it is no longer needed");
                // We should clean up this feed but for now it is still expected
                $success = $this->deleteFeed($feedId, $storeId);
                if (!$success) {
                    // If we cannot delete it, we add it back to feeds registered as it is not cleaned up yet
                    $this->feedsRegisteredOnPinterest[] = $feedId;
                }
            }
        }
    }

    /**
     * Patch call to update feed info
     * @param string $feedId
     * @param array $newDataUpdate
     * @return boolean true if call was successful
     */
    public function updateFeedInfo($feedId, $dataToUpdate, $storeId = null)
    {
        try {
            $this->_pinterestHelper->resetApiErrorState("errors/catalogs/feeds/patch/{$feedId}");
            $response = $this->_pinterestHttpClient->patch($this->getFeedAPI($feedId), $dataToUpdate, $this->_pinterestHelper->getAccessToken($storeId));
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $status = $this->_pinterestHttpClient->getStatus();
                if ($this->validateErrorCode($response->code)) {
                    $this->_pinterestHelper->logInfo("Catalog feed update failed. HTTP {$status}: {$message}");
                } else {
                    $this->_pinterestHelper->logAndSaveAPIErrors($response, "errors/catalogs/feeds/patch/{$feedId}");
                }
            } else {
                $updatesLogs = json_encode($dataToUpdate);
                $this->_pinterestHelper->logInfo("FeedId: {$response->id} Updated successful {$updatesLogs}");
                return true;
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while updating feed info");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Checks if the user has valid connection to Pinterest
     *
     * @return bool
     */
    public function isUserConnected()
    {
        return $this->_pinterestHelper->isUserConnected();
    }

    /**
     * Create feed name
     *
     * @param string $locale
     * @param string $url
     */
    public function getFeedName($locale, $url, $storeId = null)
    {
        $sha = substr(hash('sha256', $url. ($storeId != null ? "_{$storeId}" : "")), 0, 6);
        return "magento2_pbcb_{$locale}_{$sha}";
    }

    /**
     * Register feed on Pinterest for a new install
     *
     * @param string $data
     * @param string $existingPinterestFeeds
     * @param array $queryParams
     */
    public function createFeedsForNewInstall($data, $existingPinterestFeeds, $queryParams = [], $storeId = null)
    {
        try {
            if (isset($existingPinterestFeeds[$data["name"]])) {
                if (!isset($existingPinterestFeeds[$data["name"]]->id)) {
                    throw new \Exception("Missing id in Feed: {$data["name"]}");
                }
                $existingFeedId = $existingPinterestFeeds[$data["name"]]->id;
                // If it is the first time we are installing Magento then we have some feeds that were
                // not cleaned up properly from last install thereby cleaning them up
                $this->_pinterestHelper->logInfo(
                    "Existing FeedId: {$existingFeedId} has the same name. Deleting the existing entry from Pinterest"
                );
                $this->deleteFeed($existingFeedId, $storeId);
            }
            return $this->createFeed($data["location"], $data, $queryParams, $storeId);
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while creating feed for new install");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Ensure all feeds are registered with Pinterest
     *
     * @param array $data
     * @param array $existingPinterestFeeds
     * @param array $existingFeedsSavedToMetadata
     * @param array $queryParams
     */
    public function createMissingFeedsOnPinterest($data, $existingPinterestFeeds, $existingFeedsSavedToMetadata, $queryParams = [], $storeId = null)
    {
        try {
            if (isset($existingPinterestFeeds[$data["name"]])) {
                if (!isset($existingPinterestFeeds[$data["name"]]->id)) {
                    throw new \Exception("Missing id in Feed: {$data["name"]}");
                }
                $existingFeedId = $existingPinterestFeeds[$data["name"]]->id;
                if (in_array($existingFeedId, $existingFeedsSavedToMetadata)) {
                    $existingFeedData = $existingPinterestFeeds[$existingFeedId];
                    // Feed is already registerd
                    $dataToUpdate = [];
                    if ($existingFeedData->default_currency != $data["default_currency"]) {
                        $dataToUpdate["default_currency"] = $data["default_currency"];
                        $updateResponse = $this->updateFeedInfo($existingFeedId, $dataToUpdate);
                        if (!$updateResponse) {
                            return false;
                        }
                    }
                    $this->_pinterestHelper->logInfo(
                        "FeedId: {$existingFeedId} already exists on Pinterest. Skipping"
                    );
                    $this->feedsRegisteredOnPinterest[] = $existingFeedId;
                    return true;
                } else {
                    // Deleting the conflicting feed name. The occurance of this should be really low
                    $this->_pinterestHelper->logInfo(
                        "Existing FeedId: {$existingFeedId} has the same name. Deleting the existing entry from Pinterest"
                    );
                    $this->deleteFeed($existingFeedId, $storeId);
                }
            }
            return $this->createFeed($data["location"], $data, $queryParams, $storeId);
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while creating missing feed from Pinterest");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Create feed on Pinterest
     *
     * @param string $url
     * @param string $data
     * @param array $queryParams
     * @return bool
     */
    public function createFeed($url, $data, $queryParams = [], $storeId = null)
    {
        // return $data;
        try {
            $feedname = $data['name'];
            $this->_pinterestHelper->logInfo("Creating catalog feed on Pinterest");
            $this->_pluginErrorHelper->clearError("errors/catalog/create/{$feedname}");
            $response = $this->_pinterestHttpClient->post($this->getFeedAPI(), $data, $this->_pinterestHelper->getAccessToken($storeId), null, "application/json", null, $queryParams);
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $status = $this->_pinterestHttpClient->getStatus();
                if ($this->validateErrorCode($response->code)) {
                    $this->_pinterestHelper->logInfo("Catalog feed creation failed for {$url}. HTTP {$status}: {$message}");
                } else {
                    $this->_pinterestHelper->logError("Catalog feed creation failed for {$url}. HTTP {$status}: {$message}");
                    $errorData = $this->_pinterestHelper->getErrorData($response);
                    $message = isset($response->message)? $response->message : "n/a";
                    $this->_pluginErrorHelper->logAndSaveError(
                        "errors/catalog/create/{$feedname}",
                        $errorData,
                        $message,
                        IntegrationErrorId::ERROR_CREATE_CATALOG_FEED
                    );
                }
            } else {
                // Expect id to always be present in the response payload but adding as a defensive check
                if (!isset($response->id)) {
                    $this->_pinterestHelper->logError("Catalog feed API did not return an Id for feed: {$feedname}");
                    return false;
                }
                $this->feedsRegisteredOnPinterest[] = $response->id;
                $this->_pinterestHelper->logInfo("Catalog feed creation successful {$response->id}");
                return true;
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while creating feed on Pinterest");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Change items in one merchant's catalog
     *
     * @param string $locale
     * @param array $items
     *
     * @return bool
     */
    public function updateCatalogItems($locale, $items, $storeId)
    {
        try {
            $pair = explode("_", $locale);
            $country =  substr(strtoupper($pair[1]), 0, 2);
            $language = substr(strtoupper($pair[0]), 0, 2);

            $payload = [
                "country" => $country,
                "language" => $language,
                "operation" => "UPDATE",
                "items" => $items
            ];

            $response = $this->_pinterestHttpClient
                ->post($this->getItemBatchAPI(), $payload, $this->_pinterestHelper->getAccessToken($this->_pinterestHelper->isMultistoreOn() ? $storeId : null));
            $http_status = $this->_pinterestHttpClient->getStatus();
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                if ($this->validateErrorCode($response->code)) {
                    $this->_pinterestHelper->logInfo("Catalog item update failed: HTTP {$http_status}: {$message}");
                } else {
                    $this->_pinterestHelper->logError("Catalog item update failed: HTTP {$http_status}: {$message}");
                }
            } else {
                $message = isset($response->status) ? $response->status : "";
                return true;
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while updating catalog items");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Delete feeds on Pinterest
     *
     * @param string $feedId
     */
    public function deleteFeed($feedId, $storeId = null)
    {
        $this->_pinterestHelper->logInfo("Deleting feed {$feedId} from Pinterest");
        try {
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("catalogs/feeds/{$feedId}");
            $this->_pluginErrorHelper->clearError("errors/catalogs/feeds/delete/{$feedId}");
            $response = $this->_pinterestHttpClient->delete($url, $this->_pinterestHelper->getAccessToken($storeId));
            if ($response->getStatusCode() === 204 || $response->getStatusCode() === 404) {
                /**
                 * If status code is 204, then we have deleted the feed successfully
                 * If status code is 404, then we can no longer find the feed. It might have been
                 * delete by another API call
                */
                $this->_pinterestHelper->logInfo("Feed ({$feedId}) is no longer present on Pinterest");
                return true;
            } else {
                $this->_pinterestHelper->logError("Could not delete feed ({$feedId}) from Pinterest with
                    status code:" . $response->getStatusCode());
                $this->_pinterestHelper->logError(json_encode($response->getBody()));
                $errorData = $this->_pinterestHelper->getErrorData(json_decode($response->getBody()));
                $this->_pluginErrorHelper->logAndSaveError(
                    "errors/catalogs/feeds/delete/{$feedId}",
                    $errorData,
                    $errorData['message'],
                    IntegrationErrorId::ERROR_DELETE_CATALOG_FEED
                );
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while deleting feed from Pinterest");
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Helper method to get all the feeds from Pinterest
     * @return array contain the feed informations with id and name as array keys
     */
    public function getAllFeeds($storeId = null)
    {
        $this->_pinterestHelper->logInfo("Getting feeds from Pinterest");
        try {
            $url = $this->getFeedAPI();
            $response = $this->_pinterestHttpClient->get($url, $this->_pinterestHelper->getAccessToken($storeId));
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $status = $this->_pinterestHttpClient->getStatus();
                if ($this->validateErrorCode($response->code)) {
                    $this->_pinterestHelper->logInfo("Catalog feed get failed for {$url}. HTTP {$status}: {$message}");
                } else {
                    $this->_pinterestHelper->logError("Catalog feed get failed for {$url}. HTTP {$status}: {$message}");
                }
            } else {
                if (isset($response->items)) {
                    $count = count($response->items);
                    $result = [];
                    foreach ($response->items as $item) {
                        $result[$item->id]= $item;
                        $result[$item->name]= $item;
                    }
                    $this->_pinterestHelper->logInfo("Got all the feeds (count: {$count}) successfully");
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while getting feeds from Pinterest");
            $this->_pinterestHelper->logException($e);
        }
        return [];
    }

    /**
     * Returns the conversions API Access Token
     */
    private function getAccessToken()
    {
        return $this->_pinterestHelper->getEncryptedMetadata("pinterest/token/access_token") ?? "";
    }

    /**
     * Gets the API endpoint to send the catalog feed request to
     *
     * @param string $feed_id
     */
    private function getFeedAPI($feed_id = null)
    {
        $path = "catalogs/feeds";
        $path .= $feed_id ? "/{$feed_id}" : "";
        return $this->_pinterestHttpClient->getV5ApiEndpoint($path);
    }

    /**
     * Gets the API endpoint of catalog item batch request
     */
    private function getItemBatchAPI()
    {
        $path = "catalogs/items/batch";
        return $this->_pinterestHttpClient->getV5ApiEndpoint($path);
    }
}
