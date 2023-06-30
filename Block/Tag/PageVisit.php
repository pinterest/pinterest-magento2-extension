<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;

class PageVisit extends Setup
{
    /**
     * Get all the product details required for conversions for the product the customer is viewing
     */
    public function getProductDetails()
    {
        $product = $this->_registry->registry('current_product');
        if ($product) {
            return [
                "product_id" => $product->getId(),
                "product_price" => $this->_pinterestHelper->getProductPrice($product),
                "product_name" => $product->getName(),
                "product_category" => $this->_pinterestHelper->getCategoryNamesFromIds($product->getCategoryIds()),
            ];
        }
        return [];
    }
}
