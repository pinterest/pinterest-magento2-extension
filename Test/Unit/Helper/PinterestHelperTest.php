<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
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
use Magento\Catalog\Model\ProductFactory;

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
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

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
            $this->_productFactory
        );
    }

    private function createRowWithValue($keyValueArray)
    {
        $metadataRow = $this->getMockBuilder(Metadata::class)
                  ->disableOriginalConstructor()
                  ->setMethods(['load'])
                  ->getMock();
        if ($keyValueArray == null) {
            $metadataRow->method('load')->willReturn(null);
        } else {
            $metadataRow->method('load')->willReturnCallback(function ($metadataKey) use ($keyValueArray) {
                $value = $keyValueArray[$metadataKey];
                $abstractMock = $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getData'])
                ->getMock();
                $abstractMock->method('getData')->willReturn($value);
                return $abstractMock;
            });
        }
        return $metadataRow;
    }

    private function saveRowMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['setData', 'save'])
                ->getMock();
    }

    private function storeMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getBaseUrl'])
                ->getMock();
    }

    private function userMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getEmail'])
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
        $this->_metadataFactory->method('create')->willReturn($this->createRowWithValue([
            'pinterest/token/expires_in' => 1000,
            'pinterest/token/access_token' => "now",
        ]));
        
        $isUserConnected = $this->_pinterestHelper->isUserConnected();
        
        $this->assertTrue($isUserConnected);
    }

    public function testIsUserDisconnected()
    {
        $this->_metadataFactory->method('create')->willReturn($this->createRowWithValue([
            'pinterest/token/expires_in' => 10,
            'pinterest/token/access_token' => "last monday",
        ]));
        
        $isUserConnected = $this->_pinterestHelper->isUserConnected();
        
        $this->assertFalse($isUserConnected);
    }

    public function testRandomState()
    {
        $mock = $this->saveRowMock();
        $mock->expects($this->once())->method('save');
        $mock->expects($this->once())->method('setData')->willReturnCallback(function ($value) {
            $this->assertEquals($value['metadata_key'], 'ui/state');
            $this->assertTrue(strlen($value['metadata_value']) > 12);
        });
        $this->_metadataFactory->method('create')->willReturn($mock);

        $this->_pinterestHelper->getRandomState();
    }

    public function testGetBaseUrls()
    {
        $storeMock = $this->storeMock();
        $storeMock->method('getBaseUrl')->willReturn("www.pinterest.com");
        $this->_storeManagerInterface->method("getStores")->willReturn([$storeMock]);
        $baseUrls = $this->_pinterestHelper->getBaseUrls();
        $this->assertEquals(["www.pinterest.com"], $baseUrls);
    }
}
