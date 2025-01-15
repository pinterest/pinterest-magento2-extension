<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;

class ViewCategory extends Setup
{
    /**
     * @var PricingHelper
     */
    protected $_pricingHelper;

    /**
     * Block to handle data requirements for the Checkout conversions event
     *
     * @param Context $context
     * @param PluginErrorHelper $pluginErrorHelper
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     * @param Registry $registry
     * @param CustomerDataHelper $customerDataHelper
     * @param PricingHelper $pricingHelper
     */
    public function __construct(
        Context $context,
        PluginErrorHelper $pluginErrorHelper,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        Registry $registry,
        CustomerDataHelper $customerDataHelper,
        PricingHelper $pricingHelper
    ) {
        parent::__construct(
            $context,
            $pluginErrorHelper,
            $pinterestHelper,
            $eventManager,
            $registry,
            $customerDataHelper
        );
        $this->_pricingHelper = $pricingHelper;
    }

    /**
     * Get all the product details required for conversions for the product the customer is viewing
     *
     * @return string|null
     */
    public function getCategory()
    {
        $category = $this->_registry->registry('current_category');
        if ($category) {
            return addslashes($category->getName());
        } else {
            return null;
        }
    }

    /**
     * Return the loaded products on the page
     *
     * Returns null if there is an exception
     *
     * @return collection
     */
    private function getLoadedProducts()
    {
        $products = null;
        try {
            $products = $this->getLayout()->getBlock('category.products.list')->getLoadedProductCollection();
        } catch (\Exception $e) {
            $this->_pinterestHelper->logError("Couldn't load category products");
            $this->_pinterestHelper->logException($e);
        }
        return $products;
    }

    /**
     * Get details of the products displayed on the view category page
     *
     * @return array
     */
    public function getAllProductDetails()
    {
        $line_items = [];
        $contents = [];
        $category = $this->getCategory();
        $productCollection = $this->getLoadedProducts();
        if (!is_null($productCollection)) {
            foreach ($productCollection as $product) {
                $price = $this->_pinterestHelper->getProductPrice($product);
                $line_items[] = [
                    "product_id" => $this->_pinterestHelper->getContentId($product),
                    "product_price" => $price,
                    "product_name" => $product->getName(),
                    "product_category" => $category,
                ];
                $contents[] = [
                    "item_price" => (string) $price,
                ];
            }
            return [
                "content_ids" => array_values($productCollection->getAllIds()),
                "contents" => array_values($contents),
                "line_tems" => array_values($line_items),
                "category" => $category,
            ];
        }
        return [];
    }
}
