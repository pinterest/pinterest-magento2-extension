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
    protected $cache;

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
     * Creates all the data required to captures user info
     */
    private function createUserData()
    {
        $user_data = [
            "client_ip_address" => $this->getClientIP(),
            "client_user_agent" => $this->getUserAgent(),
        ];
        if ($this->getPinterestCookie()) {
            $user_data["external_id"] = [$this->getPinterestCookie()];
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

            // TODO: Customer billing address information
        }
        return $user_data;
    }

    /**
     * Returns the conversions API Access Token
     *
     * TODO: Remove once the tokens are unified
     */
    private function getAccessToken()
    {
        return $this->_pinterestHelper->getEncryptedMetadata("pinterest/token/access_token");
    }

    /**
     * Gets the API endpoint to send the conversions API request to
     */
    private function getAPIEndPoint()
    {
        $advertiserId = $this->_pinterestHelper->getMetadataValue("pinterest/info/advertiser_id");
        return $this->_pinterestHttpClient->getV5ApiEndpoint("ad_accounts/$advertiserId/events");
    }

    /**
     * Create a server side event payload
     *
     * @param string $eventId
     * @param string $eventName
     * @param array $customData
     * @return array
     */
    public function createEventPayload($eventId, $eventName, $customData = [])
    {
        return [
            "event_name" => $eventName,
            "action_source" => "web",
            "event_time" => time(),
            "event_id" => $eventId,
            "user_data" => $this->createUserData(),
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
     */
    public function processConversionEvent($eventId, $eventName, $customData = [])
    {
        if ($this->_disableTag || $this->_pinterestHelper->isUserOptedOutOfTracking()) {
            return;
        }
        try {
            $eventData = $this->createEventPayload($eventId, $eventName, $customData);
            $this->_lastEventEnqueued = $eventData;
            $this->enqueueEvent($eventData);
        } catch (\Exception $e) {
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
     */
    private function getCacheMetadata()
    {
        $cacheData = $this->_cache->load(self::RECENT_SAVE);
        if (!$cacheData) {
            $cacheData = $this->getInitialCacheState();
        }
        return $cacheData;
    }

    /**
     * Set batch metadata
     *
     * @param string $cacheState
     */
    private function setCacheMetadata($cacheState)
    {
        $this->_cache->save($cacheState, self::RECENT_SAVE, self::CACHE_TAGS);
    }

    /**
     * Reset batch Metadata
     */
    private function resetCacheMetadata()
    {
        $this->_cache->save($this->getInitialCacheState(), self::RECENT_SAVE, self::CACHE_TAGS);
    }
    
    /**
     * Enqueue event to the Queue and if the batch criteria is met, post all events and reset queue
     *
     * @param array $eventData
     */
    public function enqueueEvent($eventData)
    {
        if (str_contains($this->getUserAgent(), 'Pinterestbot')) {
            return;
        }

        $meta = json_decode($this->getCacheMetadata(), true);
        $meta["data"][] = $eventData;
        // If batch processing criteria is met, we post the event and reset the queue
        if (time() - $meta["start_time"] > self::MAX_HOLD_SECONDS || count($meta["data"]) >= self::CACHE_MAX_ITEMS) {
            $this->resetCacheMetadata();
            $this->postEvent([
                "data" => $meta["data"]
            ]);
        } else {
            $this->setCacheMetadata(json_encode($meta));
        }
    }

    /**
     * Call the pinterest conversions API enpoint
     *
     * @param array $params
     */
    private function postEvent($params)
    {
        try {
            $numberOfEvents = count($params["data"]);
            $response = $this->_pinterestHttpClient->post($this->getAPIEndPoint(), $params, $this->getAccessToken());
            if (isset($response->code)) {
                $message = isset($response->message) ? $response->message : "n/a";
                $this->_pinterestHelper->logError("Failed to send events via conversion API with {$response->code}:{$message}");
            }
        } catch (\Exception $e) {
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
