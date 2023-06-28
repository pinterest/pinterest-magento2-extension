<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Request\Http;
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
     * Default constructor
     *
     * @param PinterestHttpClient $pinterestHttpClient
     * @param PinterestHelper $pinterestHelper
     * @param LocaleList $localeList
     * @param SavedFile $savedFile
     */
    public function __construct(
        PinterestHttpClient $pinterestHttpClient,
        PinterestHelper $pinterestHelper,
        LocaleList $localeList,
        SavedFile $savedFile
    ) {
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_localeList = $localeList;
        $this->_savedFile = $savedFile;
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
        $existingFeedsSavedToMetadata = $this->_pinterestHelper->getMetadataValue("pinterest/info/feed_ids") ?
            json_decode($this->_pinterestHelper->getMetadataValue("pinterest/info/feed_ids")) :
            [];

        $existingPinterestFeeds = $this->getAllFeeds();

        $this->_pinterestHelper->logInfo("Country locales to register feeds for: ".json_encode($country_locales));
        $this->_pinterestHelper->logInfo("Existing feeds saved to magento metadata: ".json_encode($existingFeedsSavedToMetadata));
        
        foreach ($country_locales as $storeId => $country_locale) {
            $baseUrl = $this->_pinterestHelper->getMediaBaseUrlByStoreId($storeId);
            $pair = explode("\n", $country_locale);
            $country = $pair[0];
            $locale = $pair[1];
            $currency = $this->_localeList->getCurrency($storeId);
            $url = $this->_savedFile->getExportUrl($baseUrl, $locale);
            $filename = $this->_savedFile->getFileSystemPath($baseUrl, $locale, false);
            if (! is_readable($filename)) {
                $this->_pinterestHelper->logError("Can't read feed file {$filename}, skipping.");
                continue;
            }

            $key = $baseUrl.$locale;
            if (array_key_exists($key, $created)) {
                continue;
            }

            if (!in_array($country, self::ADS_SUPPORTED_COUNTRIES)) {
                $this->_pinterestHelper->logInfo("Country {$country} is not supported for ads on Pinterest. Skipping");
                continue;
            }

            $this->_pinterestHelper->logInfo("Creating catalog feed for {$country}/{$locale} with {$url}");
            $country = substr(strtoupper($country), 0, 2);
            $feedName = $this->getFeedName($locale, $url);
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
                    $existingPinterestFeeds
                );
            } else {
                $success = $this->createMissingFeedsOnPinterest(
                    $data,
                    $existingPinterestFeeds,
                    $existingFeedsSavedToMetadata
                );
            }
            $success_count += $success ? 1 : 0;
            $created[$key] = 1;
        }
        if (!$newInstall) {
            $this->deleteStaleFeedsFromPinterest($existingFeedsSavedToMetadata, $this->feedsRegisteredOnPinterest);
        }
        $this->_pinterestHelper->saveMetadata("pinterest/info/feed_ids", json_encode(
            array_unique($this->feedsRegisteredOnPinterest)
        ));
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
    public function deleteStaleFeedsFromPinterest($existingFeedsSavedToMetadata, $feedsRegisteredOnPinterest)
    {
        foreach ($existingFeedsSavedToMetadata as $feedId) {
            /* If the feedname is not in the latest snapshot of feeds registered on Pinterest,
                that means that it is stale and must be deleted */
            if (!in_array($feedId, $feedsRegisteredOnPinterest)) {
                $this->_pinterestHelper->logInfo("Should remove feed with id {$feedId} as it is no longer needed");
                // We should clean up this feed but for now it is still expected
                $success = $this->deleteFeed($feedId);
                if (!$success) {
                    // If we cannot delete it, we add it back to feeds registered as it is not cleaned up yet
                    $this->feedsRegisteredOnPinterest[] = $feedId;
                }
            }
        }
    }

    /**
     * Return feed ids of the any existing Pinterest feed that has the same name as $feedName
     *
     * @param string $feedName
     * @param array $existingPinterestFeed
     */
    private function getFeedIdWithSameFeedName($feedName, $existingPinterestFeed)
    {
        foreach ($existingPinterestFeed as $feed) {
            if ($feed->name === $feedName) {
                return $feed->id;
            }
        }
        return null;
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
    public function getFeedName($locale, $url)
    {
        $sha = substr(hash('sha256', $url), 0, 6);
        return "magento2_pbcb_{$locale}_{$sha}";
    }

    /**
     * Register feed on Pinterest for a new install
     *
     * @param string $data
     * @param string $existingPinterestFeeds
     */
    public function createFeedsForNewInstall($data, $existingPinterestFeeds)
    {
        try {
            $existingFeedId = $this->getFeedIdWithSameFeedName($data["name"], $existingPinterestFeeds);
            if ($existingFeedId) {
                // If it is the first time we are installing Magento then we have some feeds that were
                // not cleaned up properly from last install thereby cleaning them up
                $this->_pinterestHelper->logInfo(
                    "Existing FeedId: {$existingFeedId} has the same name. Deleting the existing entry from Pinterest"
                );
                $this->deleteFeed($existingFeedId);
            }
            return $this->createFeed($data["location"], $data);
        } catch (Exception $e) {
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
     */
    public function createMissingFeedsOnPinterest($data, $existingPinterestFeeds, $existingFeedsSavedToMetadata)
    {
        try {
            $existingFeedId = $this->getFeedIdWithSameFeedName($data["name"], $existingPinterestFeeds);
            if ($existingFeedId) {
                if (in_array($existingFeedId, $existingFeedsSavedToMetadata)) {
                    // Feed is already registerd
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
                    $this->deleteFeed($existingFeedId);
                }
            }
            return $this->createFeed($data["location"], $data);
        } catch (Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Create feed on Pinterest
     *
     * @param string $url
     * @param string $data
     * @return bool
     */
    public function createFeed($url, $data)
    {
        // return $data;
        try {
            $feedname = $data['name'];
            $this->_pinterestHelper->logInfo("Creating catalog feed on Pinterest");
            $this->_pinterestHelper->resetApiErrorState("errors/catalog/create/{$feedname}");
            $response = $this->_pinterestHttpClient->post($this->getFeedAPI(), $data, $this->getAccessToken());
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $status = $this->_pinterestHttpClient->getStatus();
                $this->_pinterestHelper->logError(
                    "Catalog feed creation failed for {$url}. HTTP {$status}: {$message}"
                );
                $this->_pinterestHelper->logAndSaveAPIErrors($response, "errors/catalog/create/{$feedname}");
            } else {
                // Expect id to always be present in the response payload but adding as a defensive check
                if (!isset($response->id)) {
                    $this->_pinterestHelper->logError("Catalog feed API did not return an Id for feed: {$feed_id}");
                    return false;
                }
                $this->feedsRegisteredOnPinterest[] = $response->id;
                $this->_pinterestHelper->logInfo("Catalog feed creation successful {$response->id}");
                return true;
            }
        } catch (Exception $e) {
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
    public function updateCatalogItems($locale, $items)
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
                ->post($this->getItemBatchAPI(), $payload, $this->getAccessToken());
            $http_status = $this->_pinterestHttpClient->getStatus();
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $this->_pinterestHelper->logError("Catalog item update failed: HTTP {$http_status}: {$message}");
            } else {
                $message = isset($response->status) ? $response->status : "";
                return true;
            }
        } catch (Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Delete feeds on Pinterest
     *
     * @param string $feedId
     */
    public function deleteFeed($feedId)
    {
        $this->_pinterestHelper->logInfo("Deleting feed {$feedId} from Pinterest");
        try {
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("catalogs/feeds/{$feedId}");
            $response = $this->_pinterestHttpClient->delete($url, $this->_pinterestHelper->getAccessToken());
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
            }
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Helper method to get all the feeds from Pinterest
     */
    public function getAllFeeds()
    {
        $this->_pinterestHelper->logInfo("Getting feeds from Pinterest");
        try {
            $url = $this->getFeedAPI();
            $response = $this->_pinterestHttpClient->get($url, $this->_pinterestHelper->getAccessToken());
            if (isset($response->code)) {
                $message = isset($response->message)? $response->message : "n/a";
                $status = $this->_pinterestHttpClient->getStatus();
                $this->_pinterestHelper->logError("Catalog feed get failed for {$url}. HTTP {$status}: {$message}");
            } else {
                if (isset($response->items)) {
                    $count = count($response->items);
                    $this->_pinterestHelper->logInfo("Got all the feeds (count: {$count}) successfully");
                    return $response->items;
                }
            }
        } catch (\Exception $e) {
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
