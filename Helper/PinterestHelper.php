<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Module\ModuleListInterface;
use Pinterest\PinterestMagento2Extension\Constants\FeatureFlag;
use Pinterest\PinterestMagento2Extension\Constants\ConfigSetting;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Helper\EventIdGenerator;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Cache\Manager;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Cookie\Helper\Cookie;
use Magento\Store\Model\ScopeInterface;
use Pinterest\PinterestMagento2Extension\Model\Config\PinterestGDPROptions;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Pinterest\PinterestMagento2Extension\Helper\ProductExporter;

class PinterestHelper extends AbstractHelper
{
    public const CLIENT_ID_PATH='PinterestConfig/general/client_id';
    public const PINTEREST_BASE_URL_PATH='PinterestConfig/general/pinterest_base_url';
    public const PINTEREST_CATALOG_ENABLED_PATH='PinterestConfig/general/pinterest_catalog_enabled';
    public const PINTEREST_CONVERSION_ENABLED_PATH='PinterestConfig/general/pinterest_conversion_enabled';
    public const PINTEREST_GDPR_COOKIE_NAME='PinterestConfig/gdpr/gdpr_cookie_name';
    public const PINTEREST_GDPR_OPTION='PinterestConfig/gdpr/option';
    public const PINTEREST_GDPR_ENABLED='PinterestConfig/gdpr/enabled';
    public const PINTEREST_LDP_ENABLED='PinterestConfig/ldp/enabled';
    public const REDIRECT_URI='pinterestadmin/Setup/PinterestToken';
    public const ADMINHTML_SETUP_URI='pinterestadmin/Setup/Index';
    public const MODULE_NAME='Pinterest_PinterestMagento2Extension';
    public const IFRAME_VERSION='v2';

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
     * @var Manager
     */
    protected $_cacheManager;

     /**
      * @var ProductFactory
      */
    protected $_productFactory;

    /**
     * @var Cookie
     */
    protected $_cookie;

    /**
     * @var CookieManagerInterface
     */
    protected $_cookieManager;
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
     * @param Manager $cacheManager
     * @param ProductFactory $productFactory
     * @param Cookie $cookie
     * @param CookieManagerInterface $cookieManager
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
        Manager $cacheManager,
        ProductFactory $productFactory,
        Cookie $cookie,
        CookieManagerInterface $cookieManager
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
        $this->_cacheManager = $cacheManager;
        $this->_productFactory = $productFactory;
        $this->_cookie = $cookie;
        $this->_cookieManager = $cookieManager;
    }

    /**
     * Get the client ID for pinterest app
     *
     * @return string pinterest client id
     */
    public function getClientId()
    {

        return $this->scopeConfig->getValue(self::CLIENT_ID_PATH);
    }

    /**
     * Get the pinterest base URL
     *
     * @return string pinterest base url
     */
    public function getPinterestBaseUrl()
    {

        return $this->scopeConfig->getValue(self::PINTEREST_BASE_URL_PATH);
    }

    /**
     * Returns true if catalog is enabled in config
     *
     * @return boolean true/false
     */
    public function isCatalogConfigEnabled()
    {
        return strcmp($this->scopeConfig->getValue(self::PINTEREST_CATALOG_ENABLED_PATH), ConfigSetting::ENABLED) == 0;
    }

    /**
     * Returns true if conversion is enabled in config
     *
     * @return boolean true/false
     */
    public function isConversionConfigEnabled()
    {
        return strcmp(
            $this->scopeConfig->getValue(self::PINTEREST_CONVERSION_ENABLED_PATH),
            ConfigSetting::ENABLED
        ) == 0;
    }

    /**
     * Returns true if LDP is enabled in config for this extension
     *
     * @return bool
     */
    public function isLdpEnabled()
    {
        return $this->scopeConfig->getValue(self::PINTEREST_LDP_ENABLED) == 1;
    }

    /**
     * Gets GDPR option
     *
     * @param string $store_id
     * @return int
     */
    public function getGdprOption($store_id = null)
    {
        return (int) $this->scopeConfig->getValue(
            self::PINTEREST_GDPR_OPTION,
            ScopeInterface::SCOPE_STORE,
            $store_id
        );
    }

    /**
     * Get GDPR cookie name from config
     *
     * @param string $store_id
     * @return string
     */
    public function getGDPRCookieName($store_id = null)
    {
        return $this->scopeConfig->getValue(
            self::PINTEREST_GDPR_COOKIE_NAME,
            ScopeInterface::SCOPE_STORE,
            $store_id
        );
    }

    /**
     * Returns true if GDPR is enabled in config for this extension
     *
     * @param string $store_id
     * @return bool
     */
    public function isGdprEnabled($store_id = null)
    {
        return $this->scopeConfig->getValue(
            self::PINTEREST_GDPR_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store_id
        ) == 1;
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
     * Get total number of products in all stores
     *
     * @return Integer
     */
    public function getProductCountInAllStores()
    {
        $stores = $this->_storeManager->getStores();
        $count = 0;
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $collection = $this->_productFactory->create()->getCollection();
            $filteredCollection = $collection->setStoreId($storeId)
                                             ->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $count = $count + $filteredCollection->count();

            $this->logInfo("getProductCountInAllStores total_count = ".$count." storeid=".$storeId);
        }
        return $count;
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
     * Get all the lastest Items added in cart
     *
     * @return Array[Item]|null
     */
    public function getLastAddedItemsToCart()
    {
        $quote = $this->_cart->getQuote();
        if ($quote) {
            $items = $quote->getAllVisibleItems();
            $lastModifiedItems = [];
            $lastModifiedAt = null;
            
            foreach ($items as $item) {
                // check if item is simple or configurable. Otherwise skip it
                if ($item->getProductType() == Type::TYPE_SIMPLE
                    || $item->getProductType() == Configurable::TYPE_CODE
                    || $item->getProductType() == "grouped") {
                    $modifiedAt = $item->getUpdatedAt();

                    if ($lastModifiedAt === null || $lastModifiedAt < $modifiedAt) {
                        $lastModifiedItems = [$item];
                        $lastModifiedAt = $modifiedAt;
                    } elseif ($lastModifiedAt == $modifiedAt) {
                        $lastModifiedItems[] = $item;
                    }
                }
            }
            return $lastModifiedItems;
        }
        return null;
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
     *
     * TO BE DELETED
     *
     * @param mixed $response - API response
     * @param string $dbPath - Path of where to save the errors in DB
     */
    public function logAndSaveAPIErrors($response, $dbPath)
    {
        $code = isset($response->code)? $response->code : "n/a";
        $message = isset($response->message)? $response->message : "n/a";
        $this->_logger->error("Code: ".$code);
        $this->saveMetadata($dbPath."/code", $code);
        $this->_logger->error("Message: ".$message);
        $this->saveMetadata($dbPath."/message", $message);
    }

    /**
     * Reset the API errors states for a given DB path
     *
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

    /**
     * Get if catalogs and realtime updates are enabled in the config
     *
     * @return bool
     */
    public function isCatalogAndRealtimeUpdatesEnabled()
    {
        return $this->isCatalogConfigEnabled() && $this->isUserConnected();
    }

    /**
     * Get product price
     *
     * @param ProductInterface $product
     */
    public function getProductPrice($product)
    {
        $price = null;

        if ($product) {
            $price = $product
                ->getPriceInfo()
                ->getPrice('regular_price')
                ->getAmount()
                ->getBaseAmount() ?: null;
        }

        if ($price == null) {
            if ($product->getTypeId() == Type::TYPE_SIMPLE) {
                $price = $product->getPrice();
            } else {
                $price = $product->getFinalPrice();
            }
        }

        return $price;
    }

    /**
     * Check if user has consented to tracking via cookies.
     *
     * @return bool
     */
    public function isUserOptedOutOfTracking()
    {
        if (!$this->isGdprEnabled()) {
            return false;
        } elseif ($this->isCookieRestrictionModeEnabled()
                    && $this->getGdprOption() == PinterestGDPROptions::USE_COOKIE_RESTRICTION_MODE) {
            return $this->_cookie->isUserNotAllowSaveCookie();
        } elseif ($this->getGdprOption() == PinterestGDPROptions::IF_COOKIE_NOT_EXIST) {
            // Allowed to track if cookie value is not set
            return $this->_cookieManager->getCookie($self->getGDPRCookieName(), null) != null;
        }
        return false;
    }

    /**
     * Check if cookie restriction mode is enabled
     *
     * @return bool
     */
    public function isCookieRestrictionModeEnabled()
    {
        return $this->_cookie->isCookieRestrictionModeEnabled();
    }

    /**
     * Return current website id.
     *
     * @return int
     * @throws LocalizedException
     */
    public function getCurrentWebsiteId()
    {
        return $this->_storeManager->getWebsite()->getId();
    }

    /**
     * Returns the locale of the admin user
     *
     * @return string
     */
    public function getUserLocale()
    {
        return $this->_session->getUser() ? $this->_session->getUser()->getInterfaceLocale() : null;
    }

    /**
     * Get the content id for the product
     *
     * @param Product $product
     * @return string content id
     */
    public static function getContentId($product)
    {
        $unique_id = ProductExporter::getUniqueId($product);
        return ($product->getTypeId() == Type::TYPE_SIMPLE) ?  "v_".$unique_id : $unique_id;
    }
}
