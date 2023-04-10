<?php
declare(strict_types=1);

namespace Pinterest\PinterestBusinessConnectPlugin\Helper;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SimpleXMLElement;
use Pinterest\PinterestBusinessConnectPlugin\Constants\IntegrationErrorId;
use Pinterest\PinterestBusinessConnectPlugin\Helper\LocaleList;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PluginErrorHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\SavedFile;
use Pinterest\PinterestBusinessConnectPlugin\Logger\Logger;

class ProductExporter
{
    /**
     * @var PluginErrorHelper
     */
    protected $pluginErrorHelper;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productLoader;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistryInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var SavedFile
     */
    private $savedFile;

    /**
     * @var array
     */
    private $productsData;

    /**
     * @var LocaleList
     */
    private $localelist;

    /**
     * @var int
     */
    private $lastProcessTime;

    /**
     * @var PinterestHelper
     */
    private $pinterestHelper;

    /**
     * @var Logger
     */
    private $appLogger;
    /**
     * @var string
     */
    private $absolute_path;
    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param SavedFile $savedFile
     * @param File $file
     * @param LocaleList $localelist
     * @param PinterestHelper $pinterestHelper
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $collectionFactory
     * @param StockRegistryInterface $stockRegistryInterface
     * @param StoreManagerInterface $storeManager
     * @param PluginErrorHelper $pluginErrorHelper
     * @param Logger $appLogger
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        SavedFile $savedFile,
        PinterestHelper $pinterestHelper,
        Logger $appLogger,
        LocaleList $localelist,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $collectionFactory,
        StockRegistryInterface $stockRegistryInterface,
        PluginErrorHelper $pluginErrorHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->savedFile = $savedFile;
        $this->pinterestHelper = $pinterestHelper;
        $this->localelist = $localelist;
        $this->productLoader = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->storeManager = $storeManager;
        $this->lastProcessTime = 0;
        $this->productsData = null;
        $this->appLogger = $appLogger;
        $this->pluginErrorHelper = $pluginErrorHelper;
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    public function processExport()
    {
        $data = [];
        $success = 0;
        $stores = $this->storeManager->getStores();
        $country_locales = $this->localelist->getListLocaleForAllStores();
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $baseUrl = $this->pinterestHelper->getMediaBaseUrlByStoreId($storeId) ?? "";
            $country_locale = $country_locales[$storeId];
            $key = "{$country_locale}\n{$baseUrl}";
            $content = $this->productsData ?? $this->prepareData($storeId);
            $data[$key] = array_merge($data[$key] ?? [], $content);
            $this->appLogger->info("Store{$store->getId()} processed,locale={$country_locale}");
        }
        
        if (!$this->savedFile->isEnabled()) {
            return true;
        }
        // sort by the size of value array, from big to small
        uasort($data, function ($v1, $v2) {
            return count($v2) - count($v1);
        });

        foreach ($data as $key => $content) {
            $success += $this->saveXml($key, $content) ? 1 : 0;
        }
        return $success;
    }

    /**
     * @return string
     */
    public function getOutputUrls()
    {
        $stores = $this->storeManager->getStores();
        $country_locales = $this->localelist->getListLocaleForAllStores();
        $urls = [];
        $keys = [];
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $baseUrl = $this->pinterestHelper->getMediaBaseUrlByStoreId($storeId) ?? "";
            $locale = explode("\n", $country_locales[$storeId])[1];
            $key = $baseUrl.$locale;
            if (! in_array($key, $keys)) {
                $urls[] = $this->savedFile->getExportUrl($baseUrl, $locale);
                $keys[] = $key;
            }
        }
        return $urls;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    private function prepareData($storeId)
    {
        $content = [];
        $counter = 0;
        $collection = $this->collectionFactory->create();

        // use type_id to filter out variants items here. variants will be through a different function.
        $collection->setStoreId($storeId)
                   ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                   ->addAttributeToFilter('type_id', ProductType::TYPE_SIMPLE)
                   ->addFieldToFilter([['attribute'=>'visibility',
                                                  'neq'=>Visibility::VISIBILITY_NOT_VISIBLE]])
                   ->addUrlRewrite()
                   ->addAttributeToSelect('*');
        $products = $collection->getItems();
        foreach ($products as $product) {
            $productValues = [
                "xmlns:g:id" => $this->getSkuId($product),
                "item_group_id" => $this->getItemGroupId($product),
                "title" => $this->getProductName($product),
                "description" => $this->getProductDescription($product),
                "link" => $this->getProductUrl($product),
                "xmlns:g:image_link" => $this->getProductImageUrl($storeId, $product),
                "xmlns:g:price" => $this->getProductPrice($product),
                "xmlns:g:product_type" => $this->getProductCategories($product),
                "xmlns:g:availability" => $this->getProductAvailability($product),
            ];
            if ($productValues["link"] == null) {
                continue;
            }
            if ($product->getSpecialPrice() != 0 && $product->getSpecialPrice() < $product->getPrice()) {
                $productValues["sale_price"] = $this->getProductSalePrice($product);
            }
            $content["item" . $counter] = $productValues;
            $counter++;
        }
        return $content;
    }

    /**
     * @param $product
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductCategories($product)
    {
        $categoryIds = $product->getCategoryIds();
        $categoryPath = "";
        $longest_chain = [];
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            $chain = [];
            foreach ($category->getParentIds() as $parentId) {
                if ($parentId == Category::TREE_ROOT_ID) {
                    continue;
                }
                $chain []= $this->categoryRepository->get($parentId)->getName();
            }
            $chain []= $category->getName();
            if (count($chain) > count($longest_chain)) {
                $longest_chain = $chain;
            }
        }

        return implode(" > ", $longest_chain);
    }

    /**
     * @return void
     */
    private function saveXml($key, $content)
    {
        if (is_null($key)) {
            return false;
        }
        $pair = explode("\n", $key);
        $country = $pair[0];
        $locale = $pair[1];
        $baseUrl = $pair[2];
        $this->pluginErrorHelper->clearError("errors/catalog_export/{$locale}");

        $this->absolute_path = $this->savedFile->getFileSystemPath($baseUrl, $locale, true);
        $this->appLogger->info("Country={$country},Locale={$locale},XML size=" . count($content));
        $this->appLogger->info("SaveTo=" . $this->absolute_path);
        $this->appLogger->info("URL=" . $this->savedFile->getExportUrl($baseUrl, $locale));
        $xml = new SimpleXMLElement(
            '<rss xmlns:g="http://base.google.com/ns/1.0" />'
        );
        $xml->addAttribute('version', '2.0');
        $xml->addChild('channel');
        $this->arrayToXml($content, $xml);
        $success = $xml->asXML($this->absolute_path);
        if (!$success) {
            $this->appLogger->error("export failed with " . $this->absolute_path);
            $this->pluginErrorHelper->logAndSaveError(
                "errors/catalog_export/{$locale}",
                [],
                "save to {$this->absolute_path} failed",
                IntegrationErrorId::ERROR_CATALOG_EXPORT_SAVE
            );
        }
        $this->lastProcessTime = time();
        return $success;
    }

    /**
     * @param $data
     * @param $xml
     *
     * @return void
     */
    private function arrayToXml($data, &$xml)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xml->channel->addChild("item");
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

    /**
     * @param $product
     *
     * @return string
     */
    private function getSkuId($product)
    {
        return $product->getSku();
    }

    /**
     * @param $product
     *
     * @return string
     */
    private function getItemGroupId($product)
    {
        return "magento_".$product->getSku();
    }

    /**
     * @param $product
     *
     * @return mixed
     */
    private function getProductName($product)
    {
        return $product->getName();
    }

    /**
     * @param $product
     *
     * @return mixed
     */
    private function getProductDescription($product)
    {
        return $product->getDescription();
    }

    /**
     * @param $product
     *
     * @return mixed
     */
    private function getProductUrl($product)
    {
        $websites = $product->getWebsiteIds();
        if (!$websites || count($websites) == 0) {
            return null;
        }
        return $product->getProductUrl();
    }

    /**
     * @param $product
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductImageUrl($storeId, $product)
    {
        return $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . 'catalog/product' . $product->getImage();
    }

    /**
     * @param $product
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductPrice($product)
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode() . number_format(
            (float)$product->getPrice(),
            2,
            '.',
            ''
        );
    }

    /**
     * @param $product
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function getProductSalePrice($product)
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode() . number_format(
            (float)$product->getSpecialPrice(),
            2,
            '.',
            ''
        );
    }

    /**
     * @param $product
     *
     * @return string
     */
    private function getProductAvailability($product)
    {
        if ($this->stockRegistryInterface->getStockItem($product->getId())->getIsInStock()) {
            return "in stock";
        } else {
            return "out of stock";
        }
    }
}