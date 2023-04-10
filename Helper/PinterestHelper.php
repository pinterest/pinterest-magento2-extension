<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Module\ModuleListInterface;
use Pinterest\PinterestBusinessConnectPlugin\Model\MetadataFactory;
use Pinterest\PinterestBusinessConnectPlugin\Logger\Logger;
use Pinterest\PinterestBusinessConnectPlugin\Helper\EventIdGenerator;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHttpClient;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Cache\Manager;

class PinterestHelper extends AbstractHelper
{
    public const CLIENT_ID_PATH='PinterestConfig/general/client_id';
    public const PINTEREST_BASE_URL_PATH='PinterestConfig/general/pinterest_base_url';
    public const REDIRECT_URI='pinterestadmin/Setup/PinterestToken';
    public const ADMINHTML_SETUP_URI='pinterestadmin/Setup/Index';
    public const MODULE_NAME='Pinterest_PinterestBusinessConnectPlugin';

    /**
     * @var Session
     */
    protected $_session;

    /**
     * @var Cart
     */
    protected $_cart;
    
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var MetadataFactory
     */
    protected $_metadataFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * @var PinterestHttpClient $pinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * @var Manager
     */
    protected $_cacheManager;

    /**
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param MetadataFactory $metadataFactory
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param Logger $logger
     * @param ModuleListInterface $moduleList
     * @param CategoryFactory $categoryFactory
     * @param ProductRepositoryInterface $productRepository
     * @param Cart $cart
     * @param Session $session
     * @param PinterestHttpClient $pinterestHttpClient
     * @param Manager $cacheManager
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        MetadataFactory $metadataFactory,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        Logger $logger,
        ModuleListInterface $moduleList,
        CategoryFactory $categoryFactory,
        ProductRepositoryInterface $productRepository,
        Cart $cart,
        Session $session,
        PinterestHttpClient $pinterestHttpClient,
        Manager $cacheManager
    ) {
        parent::__construct($context);
        $this->_objectManager = $objectManager;
        $this->_metadataFactory = $metadataFactory;
        $this->_storeManager = $storeManager;
        $this->_encryptor = $encryptor;
        $this->_logger = $logger;
        $this->_moduleList = $moduleList;
        $this->_categoryFactory = $categoryFactory;
        $this->_productRepository = $productRepository;
        $this->_cart = $cart;
        $this->_session = $session;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_cacheManager = $cacheManager;
    }

    /**
     * Get the client ID for pinterest app
     *
     * @retun string pinterest client id
     */
    public function getClientId()
    {

        return $this->scopeConfig->getValue(self::CLIENT_ID_PATH);
    }

    /**
     * Get the pinterest base URL
     *
     * @retun string pinterest base url
     */
    public function getPinterestBaseUrl()
    {

        return $this->scopeConfig->getValue(self::PINTEREST_BASE_URL_PATH);
    }

    /**
     * Send partner metadata to Pinterest V5 API
     *
     * @param array $info
     */
    public function exchangeMetadata($info)
    {
        try {
            $this->resetApiErrorState("errors/metadata_post");
            $params = [
                "external_business_id" => $this->getExternalBusinessId(),
                "connected_merchant_id" => $info['merchant_id'],
                "connected_advertiser_id" => $info['advertiser_id'],
                "connected_tag_id" => $info['tag_id'],
                "partner_primary_email" => $this->getStoreEmail(),
                // TODO add partner_access_token COIN-1895
            ];
    
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("integrations/commerce");
    
            $response = $this->_pinterestHttpClient->post($url, $params, $this->getAccessToken());
            
            if (isset($response->code)) {
                $this->logError("Failed to send metadata to V5 API");
                $this->logAndSaveAPIErrors($response, "errors/metadata_post");
                return false;
            } else {
                $this->logInfo("V5 API data sent successfully");
                return true;
            }
        } catch (\Exception $e) {
            $this->logException($e);
        }
    }

    /**
     * Return admin email
     *
     * @return string
     */
    public function getStoreEmail()
    {
        return $this->_session->getUser()->getEmail();
    }

    /**
     * Generate uniqid from store id & baseURL
     *
     * @param string $advertiserId
     * @return string
     */
    public function generateExternalBusinessId($advertiserId)
    {
        $this->logInfo("Generating new External Business Id");
        $storeId = $this->_storeManager->getStore()->getId();
        $baseUrl = parse_url($this->_storeManager->getStore()->getBaseUrl())["host"];
        return uniqid("magento_pins_" . $storeId . "_" . $baseUrl . "_" . $advertiserId . "_");
    }

    /**
     * Get product with SKU ID
     *
     * @param string $productSku
     * @return \Magento\Catalog\Model\Product
     */
    public function getProductWithSku($productSku)
    {
        return $this->_productRepository->get($productSku);
    }

    /**
     * Get number of items inc art
     *
     * @return int
     */
    public function getCartNumItems()
    {
        return count($this->_cart->getQuote()->getAllVisibleItems());
    }

    /**
     * Get cart subtotal
     *
     * @return string
     */
    public function getCartSubtotal()
    {
        return $this->_cart->getQuote()->getSubtotal();
    }
    
    /**
     * Get any object from object manager
     *
     * @param string $fullClassName
     * @return mixed
     */
    public function getObject($fullClassName)
    {
        return $this->_objectManager->get($fullClassName);
    }

    /**
     * Create an object from object manager
     *
     * @param string $fullClassName
     * @param array $arguments
     * @return mixed
     */
    public function createObject($fullClassName, array $arguments = [])
    {
        return $this->_objectManager->create($fullClassName, $arguments);
    }

    /**
     * Get default store view
     */
    public function getDefaultStore()
    {
        return $this->_storeManager->getDefaultStoreView();
    }

    /**
     * Get all stores
     */
    public function getStores()
    {
        return $this->_storeManager->getStores(true);
    }

    /**
     * Get a specific store by ID
     *
     * @param integer $storeId
     */
    public function getStoreById($storeId)
    {
        return $this->_storeManager->getStore($storeId);
    }

    /**
     * Get base url of a specific store by ID
     *
     * @param integer $storeId
     */
    public function getBaseUrlByStoreId($storeId)
    {
        return $this->getStoreById($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB);
    }

    /**
     * Get base url of a specific store by ID
     *
     * @param integer $storeId
     */
    public function getMediaBaseUrlByStoreId($storeId)
    {
        return $this->getStoreById($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Get current store currency code
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrency()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Get full URL based on partial URL
     *
     * @param string $partialURL
     * @return mixed
     */
    public function getUrl($partialURL)
    {
        $urlInterface = $this->getObject(\Magento\Backend\Model\UrlInterface::class);
        return $urlInterface->getUrl($partialURL);
    }
    
    /**
     * Get base url for the default store
     */
    public function getBaseUrl()
    {
        return $this->getDefaultStore()->getBaseUrl(
            UrlInterface::URL_TYPE_WEB,
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'
        );
    }

    /**
     * Get Unique state
     */
    public function getRandomState()
    {
        $state = EventIdGenerator::guidv4();
        $this->saveMetadata('ui/state', $state);
        return $state;
    }

    /**
     * Used to save non encrypted data from the db
     *
     * @param string $metadataKey
     * @param mixed $metadataValue
     */
    public function saveMetadata($metadataKey, $metadataValue)
    {
        try {
            $metadataRow = $this->_metadataFactory->create();
            $metadataRow->setData([
                'metadata_key' => $metadataKey,
                'metadata_value' => $metadataValue
            ]);
            $metadataRow->save();
        } catch (\Exception $e) {
            $this->_logger->info("In exception of saveMetadata ". $e->getMessage());
            $this->logException($e);
        }
    }

    /**
     * Used to save encrypted data from the db
     *
     * @param string $metadataKey
     * @param mixed $metadataValue
     */
    public function saveEncryptedMetadata($metadataKey, $metadataValue)
    {
        $this->saveMetadata($metadataKey, $this->_encryptor->encrypt($metadataValue));
    }

    /**
     * Used to get non encrypted data from the db
     *
     * @param string $metadataKey
     */
    public function getMetadataValue($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
        } catch (\Exception $e) {
            $this->logException($e);
            return null;
        }
        return $metadataRow ? $metadataRow->getData('metadata_value') : null;
    }

    /**
     * Used to get timestamp of last row update from the db
     *
     * @param string $metadataKey
     */
    public function getUpdatedAt($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
        } catch (\Exception $e) {
            $this->logException($e);
            return null;
        }
        return $metadataRow ? $metadataRow->getData('updated_at') : null;
    }

    /**
     * Delete the data assosiated with the metadata key
     *
     * @param string $metadataKey
     */
    public function deleteMetadata($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
            $metadataRow->delete();
        } catch (\Exception $e) {
            $this->logException($e);
        }
    }

    /**
     * Delete all the metadata values
     */
    public function deleteAllMetadata()
    {
        try {
            $collection = $this->_metadataFactory->create()->getCollection();
            foreach ($collection as $item) {
                $item->delete();
            }
            $this->logInfo("Successfully deleted connection details from database");
            return true;
        } catch (\Exception $e) {
            $this->logException($e);
            return false;
        }
    }

    /**
     * Used to get encrypted data from the db
     *
     * @param string $metadataKey
     */
    public function getEncryptedMetadata($metadataKey)
    {
        return $this->_encryptor->decrypt($this->getMetadataValue($metadataKey));
    }

    /**
     * Checks if the user has valid connection to pinterest
     *
     * @return bool
     */
    public function isUserConnected()
    {
        $expires_in = $this->getMetadataValue('pinterest/token/expires_in');
        if (null == $this->getMetadataValue('pinterest/token/expires_in')) {
            return false;
        }
        $token_issued = strtotime($this->getUpdatedAt('pinterest/token/access_token'));
        $now = time();
        if ($expires_in != null && $now < $token_issued+$expires_in) {
            // TODO make an API request to check if token is valid
            return true;
        }
        return false;
    }

    /**
     * Get Magento version
     */
    public function getMagentoVersion()
    {
        return $this->_objectManager->get(ProductMetadataInterface::class)->getVersion();
    }

    /**
     * Get magento plugin version
     */
    public function getPluginVersion()
    {
        return $this->_moduleList->getOne(self::MODULE_NAME)['setup_version'];
    }

    /**
     * Get partner metadata
     *
     * @return array
     */
    public function getPartnerMetadata()
    {
        return [
            "magentoVersion" => $this->getMagentoVersion(),
            "pluginVersion" => $this->getPluginVersion(),
            "phpVersion" => PHP_VERSION,
            "baseUrl" => $this->getBaseUrl()
        ];
    }

    /**
     * Get access token from metadata
     *
     * @return string\null
     */
    public function getAccessToken()
    {
        return $this->getEncryptedMetadata("pinterest/token/access_token");
    }

    /**
     * Get external business id from metadata
     *
     * @return string\null
     */
    public function getExternalBusinessId()
    {
        return $this->getMetadataValue("pinterest/info/business_id");
    }

    /**
     * Get advetiser Id from metadata
     *
     * @return string\null
     */
    public function getAdvertiserId()
    {
        return $this->getMetadataValue("pinterest/info/advertiser_id");
    }

    /**
     * Get merchant Id from metadata
     *
     * @return string\null
     */
    public function getMerchantId()
    {
        return $this->getMetadataValue("pinterest/info/merchant_id");
    }

    /**
     * Get client hash from metadata
     *
     * @return string\null
     */
    public function getClientHash()
    {
        return $this->getEncryptedMetadata("pinterest/info/client_hash");
    }

    /**
     * Get refresh token from metadata
     *
     * @return string\null
     */
    public function getRefreshToken()
    {
        return $this->getEncryptedMetadata("pinterest/token/refresh_token");
    }
    /**
     * Get tag Id from metadata
     *
     * @return string\null
     */
    public function getTagId()
    {
        return $this->getMetadataValue("pinterest/info/tag_id");
    }

    /**
     * Returns the Category Name from the list of category Ids
     *
     * @param array $categoryIds
     */
    public function getCategoryNamesFromIds($categoryIds)
    {
        $categoryNames = [];
        foreach ($categoryIds as $id) {
            $categoryNames[] = $this->_categoryFactory->create()->load($id)->getName();
        }
        return $categoryNames;
    }

    /**
     * Log exception
     *
     * @param Exception $e
     */
    public function logException(\Exception $e)
    {
        $this->logError($e->getMessage());
        $this->logError($e->getTraceAsString());
        $this->logError($e);
    }

    /**
     * Log Info
     *
     * @param string $message
     */
    public function logInfo($message)
    {
        $this->_logger->info($message);
    }

    /**
     * Log error
     *
     * @param string $message
     */
    public function logError($message)
    {
        $this->_logger->error($message);
    }

    /**
     * Log the response code and save the code and message to the database
     * TO BE DELETED
     *
     * @param mixed $response - API response
     * @param string $dbPath - Path of where to save the errors in DB
     */
    public function logAndSaveAPIErrors($response, $dbPath)
    {
        $code = $response->code;
        $message =  $response->message;
        $this->_logger->error("Code: ".$code);
        $this->saveMetadata($dbPath."/code", $code);
        $this->_logger->error("Message: ".$message);
        $this->saveMetadata($dbPath."/message", $message);
    }

    /**
     * Reset the API errors states for a given DB path
     * TO BE DELETED
     *
     * @param string $dbPath
     */
    public function resetApiErrorState($dbPath)
    {
        $this->deleteMetadata($dbPath."/code");
        $this->deleteMetadata($dbPath."/message");
    }

    /**
     * Gets an array of all the base URLs assosiated with the magento account
     *
     * @return array of base urls
     */
    public function getBaseUrls()
    {
        $stores = $this->getStores();
        $base_urls = [];
        foreach ($stores as $store) {
            $base_urls[] = $store->getBaseUrl(
                UrlInterface::URL_TYPE_WEB,
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'
            );
        }
        return $base_urls;
    }

    /**
     * Flushes the required cache for the plugin during connect/disconnect
     */
    public function flushCache()
    {
        $this->_cacheManager->clean(["config","layout","block_html","full_page"]);
    }
}
