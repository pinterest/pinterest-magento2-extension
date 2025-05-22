<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\CacheInterface;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\Stdlib\CookieManagerInterface;

class ConversionEventHelper
{
    public const CACHE_TAGS = ['pinterest_conversion_event_saved'];
    public const RECENT_SAVE = "pinterest_event_data_recents";
    public const CACHE_MAX_ITEMS = 500;
    public const MAX_HOLD_SECONDS = 1;

    /**
     * @var Http
     */
    protected $_request;

    /**
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var CustomerDataHelper
     */
    protected $_customerDataHelper;

    /**
     * @var CacheInterface
     */
    protected $_cache;

    /**
     * @var bool
     */
    protected $_disableTag;

    /**
     * @var array
     */
    protected $_lastEventEnqueued;

    /**
     * @var CookieManagerInterface
     */
    protected $_customCookieManager;

    /**
     * @param HTTP $request
     * @param PinterestHttpClient $pinterestHttpClient
     * @param PinterestHelper $pinterestHelper
     * @param CustomerDataHelper $customerDataHelper
     * @param CacheInterface $cache
     * @param CookieManagerInterface $customCookieManager
     */
    public function __construct(
        Http $request,
        PinterestHttpClient $pinterestHttpClient,
        PinterestHelper $pinterestHelper,
        CustomerDataHelper $customerDataHelper,
        CacheInterface $cache,
        CookieManagerInterface $customCookieManager
    ) {
        $this->_request = $request;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_customerDataHelper = $customerDataHelper;
        $this->_cache = $cache;
        $this->_customCookieManager = $customCookieManager;
        $this->_disableTag = !$pinterestHelper->isConversionConfigEnabled();
        $this->_lastEventEnqueued = [];
    }

    /**
     * Fetch cache name by store id
     *
     * @param int $storeId
     */
    private function fetchCacheName($storeId = null)
    {
        return $storeId != null ? self::RECENT_SAVE . "_" . $storeId : self::RECENT_SAVE;
    }

    /**
     * Get client user agent
     */
    private function getUserAgent()
    {
        return $this->_request->getServer("HTTP_USER_AGENT");
    }

    /**
     * Get client IP Address
     */
    private function getClientIP()
    {
        return $this->_request->getClientIp();
    }

    /**
     * Get the pinterest 1p cookie
     */
    private function getPinterestCookie()
    {
        return $this->_customCookieManager->getCookie("_pin_unauth");
    }

    /**
     * Get the epik cookie
     */
    private function getEpik()
    {
        return $this->_customCookieManager->getCookie("_epik");
    }

    /**
     * Creates all the data required to captures user info
     * @param int $storeId
     *
     */
    private function createUserData($storeId = null)
    {
        $user_data = [
            "client_ip_address" => $this->getClientIP(),
            "client_user_agent" => $this->getUserAgent(),
        ];
        if ($this->getPinterestCookie()) {
            $user_data["external_id"] = [$this->_customerDataHelper->hash($this->getPinterestCookie())];
        }
        if ($this->getEpik()) {
            $user_data["click_id"] = $this->getEpik();
        }
        if ($this->_customerDataHelper->isUserLoggedIn()) {
            if ($this->_customerDataHelper->getEmail()) {
                $user_data["em"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getEmail())];
            }
            if ($this->_customerDataHelper->getFirstName()) {
                $user_data["fn"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getFirstName())];
            }
            if ($this->_customerDataHelper->getLastName()) {
                $user_data["ln"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getLastName())];
            }
            if ($this->_customerDataHelper->getGender()) {
                $user_data["ge"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getGender())];
            }
            if ($this->_customerDataHelper->getDateOfBirth()) {
                $user_data["db"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getDateOfBirth())];
            }
        }

        if ($this->_customerDataHelper->getCity()) {
            $user_data["ct"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getCity())];
        }
        if ($this->_customerDataHelper->getState()) {
            $user_data["st"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getState())];
        }
        if ($this->_customerDataHelper->getCountry()) {
            $user_data["country"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getCountry())];
        }
        if ($this->_customerDataHelper->getZipCode()) {
            $user_data["zp"] = [$this->_customerDataHelper->hash($this->_customerDataHelper->getZipCode())];
        }
        if ($this->_pinterestHelper->isLdpEnabled($storeId)) {
            $user_data["opt_out_type"] = "LDP";
        }

        return $user_data;
    }

    /**
     * Returns the conversions API Access Token
     * @param int $storeId
     *
     * TODO: Remove once the tokens are unified
     */
    private function getAccessToken($storeId = null)
    {
        return $this->_pinterestHelper->getEncryptedMetadata($this->_pinterestHelper->getTokenByStoreAndName("access_token", $storeId));
    }

    /**
     * Gets the API endpoint to send the conversions API request to
     * @param int $storeId
     *
     */
    private function getAPIEndPoint($storeId = null)
    {
        $advertiserId = $this->_pinterestHelper->getMetadataValue($this->_pinterestHelper->getInfoByStoreAndName("advertiser_id", $storeId));
        return $this->_pinterestHttpClient->getV5ApiEndpoint("ad_accounts/$advertiserId/events");
    }

    /**
     * Create a server side event payload
     *
     * @param string $eventId
     * @param string $eventName
     * @param array $customData
     * @param int $storeId
     * @return array
     */
    public function createEventPayload($eventId, $eventName, $customData = [], $storeId = null)
    {
        return [
            "event_name" => $eventName,
            "action_source" => "web",
            "event_time" => time(),
            "event_id" => $eventId,
            "user_data" => $this->createUserData($storeId),
            "partner_name" => "ss-adobe",
            "custom_data" => array_merge($customData, [ "np" => "ss-adobe" ])
        ];
    }

    /**
     * Create event payload and add it to the batch metadata queue
     *
     * @param string $eventId
     * @param string $eventName
     * @param array $customData
     * @param int $storeId
     */
    public function processConversionEvent($eventId, $eventName, $customData = [], $storeId = null)
    {
        if ($this->_disableTag || $this->_pinterestHelper->isUserOptedOutOfTracking($storeId)) {
            return;
        }
        try {
            $eventData = $this->createEventPayload($eventId, $eventName, $customData, $storeId);
            $this->_lastEventEnqueued = $eventData;
            $this->enqueueEvent($eventData, $storeId);
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while processing the conversion event");
            $this->_pinterestHelper->logException($e);
        }
    }

    /**
     * Get the initial state of the cache metadata
     */
    public function getInitialCacheState()
    {
        return json_encode(["start_time" => time(), "data" => []]);
    }

    /**
     * Get the batch metadata
     * @param int $storeId
     */
    private function getCacheMetadata($storeId = null)
    {
        $cacheData = $this->_cache->load($this->fetchCacheName($storeId));
        if (!$cacheData) {
            $cacheData = $this->getInitialCacheState();
        }
        return $cacheData;
    }

    /**
     * Set batch metadata
     *
     * @param string $cacheState
     * @param int $storeId
     */
    private function setCacheMetadata($cacheState, $storeId = null)
    {
        $this->_cache->save($cacheState, $this->fetchCacheName($storeId), self::CACHE_TAGS);
    }

    /**
     * Reset batch Metadata
     * @param int $storeId
     */
    private function resetCacheMetadata($storeId = null)
    {
        $this->_cache->save($this->getInitialCacheState(), $this->fetchCacheName($storeId), self::CACHE_TAGS);
    }
    
    /**
     * Enqueue event to the Queue and if the batch criteria is met, post all events and reset queue
     *
     * @param array $eventData
     * @param int $storeId
     */
    public function enqueueEvent($eventData, $storeId = null)
    {
        if (str_contains($this->getUserAgent(), 'Pinterestbot')) {
            return;
        }

        $meta = json_decode($this->getCacheMetadata($storeId), true);
        $meta["data"][] = $eventData;
        
        // If batch processing criteria is met, we post the event and reset the queue
        if (time() - $meta["start_time"] > self::MAX_HOLD_SECONDS || count($meta["data"]) >= self::CACHE_MAX_ITEMS) {
            $this->resetCacheMetadata($storeId);
            $this->postEvent([
                "data" => $meta["data"]
            ], $storeId);
        } else {
            $this->setCacheMetadata(json_encode($meta), $storeId);
        }
    }

    /**
     * Call the pinterest conversions API enpoint
     *
     * @param array $params
     * @param int $storeId
     */
    private function postEvent($params, $storeId = null)
    {
        try {
            $response = $this->_pinterestHttpClient->post($this->getAPIEndPoint($storeId), $params, $this->getAccessToken($storeId));
            if (isset($response->events) && is_array($response->events)) {
                foreach ($response->events as $event) {
                    if ($event->error_message) {
                        $this->_pinterestHelper->logError("postEvent response error: {$event->error_message}");
                    }
                    if ($event->warning_message) {
                        $this->_pinterestHelper->logWarning("postEvent response warning: {$event->warning_message}");
                    }
                }
            }
            if (isset($response->code)) {
                $message = isset($response->message) ? $response->message : "n/a";
                $this->_pinterestHelper->logError(
                    "Failed to send events via conversion API with {$response->code}:{$message}"
                );
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logError("An error occurred while posting the conversion event for store: " . $storeId);
            $this->_pinterestHelper->logException($e);
        }
    }

    /**
     * Test only function to get the last event enqueued
     */
    public function getLastEventEnqueued()
    {
        return $this->_lastEventEnqueued;
    }
}
