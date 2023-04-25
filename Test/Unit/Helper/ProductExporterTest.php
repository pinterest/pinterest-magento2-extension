<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use SimpleXMLElement;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProductType;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\ProductExporter;
use Pinterest\PinterestMagento2Extension\Helper\LocaleList;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;
use Pinterest\PinterestMagento2Extension\Logger\Logger;

class ProductExporterTest extends \PHPUnit\Framework\TestCase
{
    protected $_categoryRepository;
    protected $_savedFile;
    protected $_pinterestHelper;
    protected $_localelist;
    protected $_productRepository;
    protected $_collectionFactory;
    protected $_stockRegistryInterface;
    protected $_storeManager;
    protected $_reflection;
    protected $_logger;
    protected $storeMock1;
    protected $storeMock2;
    protected $_pluginErrorHelper;
    protected $configurableProductType;

    public function setUp() : void
    {
        $this->_categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->_savedFile = $this->createMock(SavedFile::class);
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_localelist = $this->createMock(LocaleList::class);
        $this->_productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->_collectionFactory = $this->createMock(CollectionFactory::class);
        $this->_stockRegistryInterface = $this->createMock(StockRegistryInterface::class);
        $this->_storeManager = $this->createMock(StoreManagerInterface::class);
        $this->_logger = $this->createMock(Logger::class);
        $this->_pluginErrorHelper = $this->createMock(PluginErrorHelper::class);
        $this->configurableProductType = $this->createMock(ConfigurableProductType::class);

        $this->_productExporter = new ProductExporter(
            $this->_categoryRepository,
            $this->_savedFile,
            $this->_pinterestHelper,
            $this->_logger,
            $this->_localelist,
            $this->_productRepository,
            $this->_collectionFactory,
            $this->_stockRegistryInterface,
            $this->_pluginErrorHelper,
            $this->_storeManager,
            $this->configurableProductType
        );
        $this->_reflection = new \ReflectionClass($this->_productExporter);

        $this->storeMock1 = $this->createMock(StoreInterface::class);
        $this->storeMock2 = $this->createMock(StoreInterface::class);
        $this->storeMock1->method('getId')->willReturn(1);
        $this->storeMock2->method('getId')->willReturn(2);
    }

    private function setPrivateProperty($object, $name, $value)
    {
        $property = $this->_reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty($obj, $name)
    {
        $property = $this->_reflection->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    public function testSaveProcessExport()
    {
        $this->_storeManager->method('getStores')->willReturn([$this->storeMock1]);
        $this->_localelist->method('getListLocaleForAllStores')->willReturn([1 =>"US\nen_US"]);
        $this->_savedFile->method('isEnabled')->willReturn(true);
        $this->_savedFile->method('getFileSystemPath')->willReturn("/dev/null");
        $this->setPrivateProperty($this->_productExporter, "productsData", [""]);
        $this->assertEquals(1, $this->_productExporter->processExport());
        $this->assertEquals("/dev/null", $this->getPrivateProperty($this->_productExporter, "absolute_path"));
    }

    public function testMultiLocaleExport()
    {
        $this->_storeManager->method('getStores')->willReturn([$this->storeMock1, $this->storeMock2]);
        $this->_localelist->method('getListLocaleForAllStores')->willReturn([1=>"US\nen_US", 2=>"GB\nen_GB"]);
        $this->_pinterestHelper->method('getMediaBaseUrlByStoreId')->willReturn("https://abc.com/");
        $this->_savedFile->method('isEnabled')->willReturn(true);
        $this->_savedFile->method('getFileSystemPath')->willReturn("/dev/null");
        $this->setPrivateProperty($this->_productExporter, "productsData", [""]);
        $this->assertEquals(2, $this->_productExporter->processExport());
        $this->assertEquals("/dev/null", $this->getPrivateProperty($this->_productExporter, "absolute_path"));
    }

    public function testGetOutputUrls()
    {
        $this->_storeManager->method('getStores')->willReturn([$this->storeMock1, $this->storeMock2]);
        $this->_localelist->method('getListLocaleForAllStores')->willReturn([1=>"US\nen_US", 2=>"GB\nen_GB"]);
        $this->_pinterestHelper->method('getMediaBaseUrlByStoreId')->willReturn("https://abc.com/");
        $this->_savedFile->method('getExportUrl')->willReturn("url");
        $this->assertEquals(["url", "url"], $this->_productExporter->getOutputUrls());
    }
}
