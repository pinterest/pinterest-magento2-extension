<?php

namespace Pinterest\PinterestMagento2Extension\Controller\Tag;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\EventIdGenerator;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use \Magento\Framework\App\Action\Action;

class ProductInfoForAddToCart extends Action
{
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * Product info for Add to Cart constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        Context $context,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_pinterestHelper = $pinterestHelper;
        $this->_resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Get info for add to cart event
     *
     * @param Array[Item] $last_product_items
     * @return array
     */
    private function getProductInfo($last_product_items)
    {
        $response_data = [];
        $line_items = [];
        $content_ids = [];

        for ($i = 0; $i < count($last_product_items); $i++) {
            $item = $last_product_items[$i];
            $product = $item->getProduct();

            $content_ids[] = $this->_pinterestHelper->getContentId($product);
            $line_items[] = [
                "product_id" => $this->_pinterestHelper->getContentId($product),
                "product_price" => $item->getPrice(),
                "product_name" => $product->getName(),
                "product_category" => $this->_pinterestHelper->getCategoryNamesFromIds($product->getCategoryIds())
            ];
        }
        $response_data["num_items"] = $this->_pinterestHelper->getCartNumItems();
        $response_data["value"] = $this->_pinterestHelper->getCartSubtotal();
        $response_data["currency"] = $this->_pinterestHelper->getCurrency();
        $response_data["line_items"] = array_values($line_items);
        $response_data["content_ids"] = array_values($content_ids);
        //TODO: content.quantity && content.item_price COIN-1838

        return $response_data;
    }

    /**
     * Send add to cart data to conversion API and dispatch event
     *
     * @return array
     */
    public function execute()
    {
        try {
            if (!$this->_pinterestHelper->isUserOptedOutOfTracking()) {
                $last_product_items = $this->_pinterestHelper->getLastAddedItemsToCart();
                if ($last_product_items) {
                    $response_data = $this->getProductInfo($last_product_items);
                    if (count($response_data) > 0) {
                        $event_id = EventIdGenerator::guidv4();
                        $response_data["event_id"] = $event_id;
                        $this->trackAddToCartEvent($event_id, $response_data);
                        // Send data back to Tag event sender
                        $result = $this->_resultJsonFactory->create();
                        $result->setData(array_filter($response_data));
                        return $result;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
    }

    /**
     * Dispatch add to cart event
     *
     * @param string $eventId
     * @param array $response_data
     * @return void
     */
    public function trackAddToCartEvent($eventId, $response_data)
    {
        $this->_eventManager->dispatch(
            "pinterest_commereceintegrationextension_add_to_cart_after",
            [
                "event_id" => $eventId,
                "event_name" => "add_to_cart",
                "custom_data" => [
                    "content_ids" => $response_data["content_ids"],
                    "num_items" => $response_data["num_items"],
                    "value" => $response_data["value"],
                    "currency" => $response_data["currency"],
                ],
            ]
        );
    }
}
