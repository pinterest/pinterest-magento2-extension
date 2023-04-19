<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;

class PageVisit extends Setup
{

    /**
     * Dispatch the page visit event with the required data
     *
     * @param string $eventId
     * @param array $productDetails
     * @param string $currency
     */
    public function trackPageVisitEvent($eventId, $productDetails, $currency)
    {
        $this->_eventManager->dispatch("pinterest_commereceintegrationextension_page_visit_after", [
            "event_id" => $eventId,
            "event_name" => "page_visit",
            "custom_data" => [
                "content_ids" => [$productDetails["product_id"]],
                "contents" => [[
                    "item_price" => (string) ($productDetails["product_price"])
                ]],
                "currency" => $currency,
            ],
        ]);
    }

    /**
     * Get all the product details required for conversions for the product the customer is viewing
     */
    public function getProductDetails()
    {
        $product = $this->_registry->registry('current_product');
        if ($product) {
            return [
                "product_id" => $product->getId(),
                "product_price" => $product->getPrice(),
                "product_name" => $product->getName(),
                "product_category" => $this->_pinterestHelper->getCategoryNamesFromIds($product->getCategoryIds()),
            ];
        }
        return [];
    }
}
