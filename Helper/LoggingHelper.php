<?php
namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Framework\App\CacheInterface;
use Pinterest\PinterestMagento2Extension\Helper\DbHelper;
use Pinterest\PinterestMagento2Extension\Helper\ExternalBusinessIdHelper;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;

/** Modelled after ConversionEventHelper.php */
class LoggingHelper
{
    // cache constants
    protected const CACHE_MAX_ITEMS = 500;
    protected const MAX_HOLD_SECONDS = 3;
    protected const LOG_CACHE_ID = "pinterest_log_data_recents";

    // logging API constants
    protected const APP_EVENT = 'APP';
    protected const API_EVENT = 'API';
    protected const LEVEL_INFO = 'INFO';
    protected const LEVEL_WARNING = 'WARN';
    protected const LEVEL_ERROR = 'ERROR';

    /**
     * @var CacheInterface
     */
    protected $_cache;

    /**
     * @var DbHelper
     */
    protected $_dbHelper;

    /**
     * @var ExternalBusinessIdHelper
     */
    protected $_externalBusinessIdHelper;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @param CacheInterface $cache
     * @param DbHelper $dbHelper
     * @param ExternalBusinessIdHelper $externalBusinessIdHelper
     * @param Logger $logger
     * @param PinterestHttpClient $pinterestHttpClient
     */
    public function __construct(
        CacheInterface $cache,
        DbHelper $dbHelper,
        ExternalBusinessIdHelper $externalBusinessIdHelper,
        Logger $logger,
        PinterestHttpClient $pinterestHttpClient
    ) {
        $this->_cache = $cache;
        $this->_dbHelper = $dbHelper;
        $this->_externalBusinessIdHelper = $externalBusinessIdHelper;
        $this->_logger = $logger;
        $this->_pinterestHttpClient = $pinterestHttpClient;
    }

    /**
     * Log a string error message
     *
     * @param string $logMessage
     */
    public function logError($logMessage)
    {
        $this->_logger->error($logMessage);
        $payload = $this->createBaselogPayload(self::LEVEL_ERROR, $logMessage);
        $this->enqueueLog($payload);
    }

    /**
     * Log a string info message
     *
     * @param string $logMessage
     */
    public function logInfo($logMessage)
    {
        $this->_logger->info($logMessage);
        $payload = $this->createBaselogPayload(self::LEVEL_INFO, $logMessage);
        $this->enqueueLog($payload);
    }

    /**
     * Log a string warning message
     *
     * @param string $logMessage
     */
    public function logWarning($logMessage)
    {
        $this->_logger->warning($logMessage);
        $payload = $this->createBaselogPayload(self::LEVEL_WARNING, $logMessage);
        $this->enqueueLog($payload);
    }

    /**
     * Log an exception
     *
     * @param Exception $e
     */
    public function logException(\Exception $e)
    {
        $this->_logger->error($e->getMessage());
        $this->_logger->error($e->getTraceAsString());
        $this->_logger->error($e);
        
        $payload = $this->createBaselogPayload(self::LEVEL_ERROR);
        $errorArray = $this->createErrorPayloadArray($e);
        $payload['error'] = $errorArray;
        $this->enqueueLog($payload);
    }
    
    /**
     * Create array to use in error portion of request payload
     *
     * @param Exception $e
     */
    public function createErrorPayloadArray(\Exception $e)
    {
        $error = [
            'file_name' => $e->getFile(),
            'line_number' => $e->getLine(),
            'message' => $e->getMessage(),
            'number' => $e->getCode(),
            'stack_trace' => $e->getTraceAsString()
        ];
        return $error;
    }

    /**
     * Format log to be sent in batch to API
     *
     * @param string $logLevel
     * @param string $message
     * @param string $eventType
     *
     * @return array $payload
     */
    protected function createBaselogPayload($logLevel, $message = null, $eventType = self::APP_EVENT)
    {
        $payload = [
            'client_timestamp' => floor(microtime(true) * 1000),
            'event_type' => $eventType,
            'log_level' => $logLevel,
            'external_business_id' => $this->_externalBusinessIdHelper->generateExternalBusinessIdPrefix()
        ];
        if ($message) {
            $payload['message'] = $message;
        }
        // TODO append ad id to external business id + add other id fields.
        // We will do this seperately to properly address to performance issues of doing this.
        return $payload;
    }

    /**
     * Get the initial state of the cache
     *
     * @return string
     */
    protected function getInitialCacheState()
    {
        return json_encode(["start_time" => time(), "logs" => []]);
    }

    /**
     * Get current cache state
     *
     * @return string
     */
    protected function getCacheState()
    {
        $cacheData = $this->_cache->load(self::LOG_CACHE_ID);
        if (!$cacheData) {
            $cacheData = $this->getInitialCacheState();
        }
        return $cacheData;
    }

    /**
     * Enqueue log to cache
     *
     * @param array $log
     */
    public function enqueueLog($log)
    {
        $cacheState = json_decode($this->getCacheState(), true);
        $cacheState["logs"][] = $log;
        if ($this->isCacheFull($cacheState)) {
            $this->sendCachedLogs($cacheState["logs"]);
        } else {
            $this->_cache->save(json_encode($cacheState), self::LOG_CACHE_ID);
        }
    }

    /**
     * Check if cache is full
     *
     * @param array $cacheState
     */
    protected function isCacheFull($cacheState)
    {
        $cacheAge = time() - $cacheState["start_time"];
        if ($cacheAge > self::MAX_HOLD_SECONDS || count($cacheState["logs"]) >= self::CACHE_MAX_ITEMS) {
            return true;
        }
        return false;
    }

    /**
     * Reset cache to empty state
     */
    protected function resetCache()
    {
        $this->_cache->save($this->getInitialCacheState(), self::LOG_CACHE_ID);
    }
    
    /**
     * Flush log cache (sends logs to Pinterest first)
     */
    public function flushCache()
    {
        $cacheState = json_decode($this->getCacheState(), true);
        if (array_key_exists("logs", $cacheState) && count($cacheState["logs"]) > 0) {
            $this->sendCachedLogs($cacheState["logs"]);
        }
    }

    /**
     * Send cached logs to Pinterest and clear cache
     *
     * @param array $logs
     */
    protected function sendCachedLogs($logs)
    {
        $accessToken = $this->_dbHelper->getAccessToken();
        if ($accessToken == null) {
            // Wait to send logs until access token is saved in DB. This may happen on initial connection
            return;
        }
        try {
            $this->_logger->info("Sending batched logs to Pinterest");
            $requestPayload = [
                "logs" => $logs
            ];
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/logs");
            $response = $this->_pinterestHttpClient->post(
                $url,
                $requestPayload,
                $accessToken,
                null,
                "application/json",
                null,
                [],
                true
            );
            $this->resetCache(); // reset cache in all cases to avoid backup
            if ($response && $response["statusCode"]) {
                $status = $response["statusCode"];
                $this->_logger->info("integrations/logs response: ".$status." ".$response["body"]);
                if ($status > 299) {
                    $this->_logger->info("Failed to send batched logs to Pinterest.");
                } elseif ($status > 200) {
                    $this->_logger->info("Success sending logs to Pinterest");
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
            $this->_logger->error($e->getTraceAsString());
        }
    }
}
