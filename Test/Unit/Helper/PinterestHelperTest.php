<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\DbHelper;
use Pinterest\PinterestMagento2Extension\Helper\LoggingHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Model\Metadata;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Cache\Manager;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ProductFactory;
use Magento\Cookie\Helper\Cookie;
use Magento\Framework\Stdlib\CookieManagerInterface;

class PinterestHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Session
     */
    protected $_session;

    /**
     * @var Cart
     */
    protected $_cart;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;
    
    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepositoryInterface;

    /**
     * @var Context
     */
    protected $_context;

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
    protected $_storeManagerInterface;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptorInterface;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var ModuleListInterface
     */
    protected $_moduleListInterface;

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
     * @var LoggingHelper
     */
    protected $_loggingHelper;

    /**
     * @var DbHelper
     */
    protected $_dbHelper;

    public function setUp() : void
    {
        $this->_context = $this->createMock(Context::class);
        $this->_objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->_metadataFactory = $this->createMock(MetadataFactory::class);
        $this->_storeManagerInterface = $this->createMock(StoreManagerInterface::class);
        $this->_encryptorInterface = $this->createMock(EncryptorInterface::class);
        $this->_logger = $this->createMock(Logger::class);
        $this->_moduleListInterface = $this->createMock(ModuleListInterface::class);
        $this->_categoryFactory = $this->createMock(CategoryFactory::class);
        $this->_productRepositoryInterface = $this->createMock(ProductRepositoryInterface::class);
        $this->_cart = $this->createMock(Cart::class);
        $this->_session = $this->sessionMock();
        $this->_cacheManager = $this->createMock(Manager::class);
        $this->_productFactory = $this->createMock(ProductFactory::class);
        $this->_cookie = $this->createMock(Cookie::class);
        $this->_cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->_loggingHelper = $this->createMock(LoggingHelper::class);
        $this->_dbHelper = $this->createMock(DbHelper::class);
        
        $this->_pinterestHelper = new PinterestHelper(
            $this->_context,
            $this->_objectManager,
            $this->_metadataFactory,
            $this->_storeManagerInterface,
            $this->_encryptorInterface,
            $this->_logger,
            $this->_moduleListInterface,
            $this->_categoryFactory,
            $this->_productRepositoryInterface,
            $this->_cart,
            $this->_session,
            $this->_cacheManager,
            $this->_productFactory,
            $this->_cookie,
            $this->_cookieManager,
            $this->_loggingHelper,
            $this->_dbHelper
        );
    }

    private function storeMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getBaseUrl'])
                ->getMock();
    }

    private function sessionMock()
    {
        return $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->setMethods(['getUser'])
                ->getMock();
    }

    public function testIsUserConnected()
    {
        $this->_dbHelper->method('getMetadataValue')->willReturnCallback(
            function () {
                $args = func_get_args();
                if ($args[0] == 'pinterest/token/expires_in') {
                    return 1000;
                }
            }
        );
        $this->_dbHelper->method('getUpdatedAt')->willReturn("now");
        $isUserConnected = $this->_pinterestHelper->isUserConnected();
        
        $this->assertTrue($isUserConnected);
    }

    public function testIsUserDisconnected()
    {
        $this->_dbHelper->method('getMetadataValue')->willReturnCallback(
            function () {
                $args = func_get_args();
                if ($args[0] == 'pinterest/token/expires_in') {
                    return 10;
                }
            }
        );
        $this->_dbHelper->method('getUpdatedAt')->willReturn("last monday");
        $isUserConnected = $this->_pinterestHelper->isUserConnected();
        
        $this->assertFalse($isUserConnected);
    }

    public function testGetRandomState()
    {
        $this->_dbHelper->expects($this->once())->method('saveMetadata');
        $state = $this->_pinterestHelper->getRandomState();
        $this->assertTrue(strlen($state) > 12);
    }

    public function testGetBaseUrls()
    {
        $storeMock = $this->storeMock();
        $storeMock->method('getBaseUrl')->willReturn("www.pinterest.com");
        $this->_storeManagerInterface->method("getStores")->willReturn([$storeMock]);
        $baseUrls = $this->_pinterestHelper->getBaseUrls();
        $this->assertEquals(["www.pinterest.com"], $baseUrls);
    }

    public function testGetLastAddedItemsToCart()
    {
        $this->_cart->method('getQuote')->willReturnCallback(function () {
            $quoteMock = $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getAllVisibleItems'])
                ->getMock();
            $quoteMock->method('getAllVisibleItems')->willReturnCallback(function () {
                $itemMock01 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock01->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
                $itemMock01->method('getUpdatedAt')->willReturn("001");

                $itemMock02 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock02->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
                $itemMock02->method('getUpdatedAt')->willReturn("002");
                return [$itemMock01, $itemMock02];
            });
            return $quoteMock;
        });
        
        $lastModifiedItems = $this->_pinterestHelper->getLastAddedItemsToCart();
        $this->assertEquals(1, count($lastModifiedItems));
    }

    public function testGetLastAddedItemsToCartReturnMultiple()
    {
        $this->_cart->method('getQuote')->willReturnCallback(function () {
            $quoteMock = $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getAllVisibleItems'])
                ->getMock();
            $quoteMock->method('getAllVisibleItems')->willReturnCallback(function () {
                $itemMock01 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock01->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
                $itemMock01->method('getUpdatedAt')->willReturn("002");

                $itemMock02 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock02->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
                $itemMock02->method('getUpdatedAt')->willReturn("002");
                return [$itemMock01, $itemMock02];
            });
            return $quoteMock;
        });
        
        $lastModifiedItems = $this->_pinterestHelper->getLastAddedItemsToCart();
        $this->assertEquals(2, count($lastModifiedItems));
    }

    public function testGetLastAddedItemsToCartFilterVirtualItemsInConfigurableProducts()
    {
        $this->_cart->method('getQuote')->willReturnCallback(function () {
            $quoteMock = $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getAllVisibleItems'])
                ->getMock();
            $quoteMock->method('getAllVisibleItems')->willReturnCallback(function () {
                $itemMock01 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock01->method('getProductType')->willReturn(Configurable::TYPE_CODE);
                $itemMock01->method('getUpdatedAt')->willReturn("002");

                $itemMock02 = $this->getMockBuilder(AbstractModel::class)
                    ->disableOriginalConstructor()
                    ->setMethods(['getProductType','getUpdatedAt'])
                    ->getMock();
                $itemMock02->method('getProductType')->willReturn("virtual");
                $itemMock02->method('getUpdatedAt')->willReturn("002");
                return [$itemMock01, $itemMock02];
            });
            return $quoteMock;
        });
        
        $lastModifiedItems = $this->_pinterestHelper->getLastAddedItemsToCart();
        $this->assertEquals(1, count($lastModifiedItems));
    }
}
