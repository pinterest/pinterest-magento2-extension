<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class Checkout extends Setup
{

    /**
     * @var Session
     */
    protected $_session;

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
     * @param Session $session
     * @param PricingHelper $pricingHelper
     * @param StoreManagerInterface $storeManager
     *
     */
    public function __construct(
        Context $context,
        PluginErrorHelper $pluginErrorHelper,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        Registry $registry,
        CustomerDataHelper $customerDataHelper,
        Session $session,
        PricingHelper $pricingHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct(
            $context,
            $pluginErrorHelper,
            $pinterestHelper,
            $eventManager,
            $registry,
            $customerDataHelper,
            $storeManager
        );
        $this->_session = $session;
        $this->_pricingHelper = $pricingHelper;
    }

    /**
     * Dispatch the checkout event with the required data
     *
     * @param string $eventId
     * @param array $productDetails
     * @param string $currency
     * @param int $storeId
     */
    public function trackCheckoutEvent($eventId, $productDetails, $currency, $storeId = null)
    {
        $this->_eventManager->dispatch("pinterest_commereceintegrationextension_checkout_after", [
            "event_id" => $eventId,
            "event_name" => "checkout",
            "custom_data" => [
                "currency" => $currency,
                "value" => (string) $productDetails["value"],
                "content_ids" => $productDetails["content_ids"],
                "contents" => $productDetails["contents"],
                "num_items" => $productDetails["num_items"],
                "order_id" => $productDetails["order_id"]
            ],
            "store_id" => $storeId
        ]);
    }

    /**
     * Returns the product details of all the products in the order
     *
     * @return array
     */
    public function getProductDetails()
    {
        $productIds = [];
        $contents = [];
        $numItems = 0;
        $subTotal = 0;
        $lineItems = [];
        $orderId = null;
        $order = $this->_session->getLastRealOrder();
        if ($order) {
            $subTotal = $this->_pricingHelper->currency($order->getSubtotal(), false, false);
            $items = $order->getItemsCollection();
            $numItems = count($items);
            $orderId = $order->getId();
            foreach ($items as $item) {
                $product = $item->getProduct();
                $productIds[] = $this->_pinterestHelper->getContentId($product);
                $price = $this->_pricingHelper->currency($product->getPrice(), false, false);
                $contents[] = [
                    "quantity" => (int) $item->getQtyOrdered(),
                    "item_price" => (string) $price,
                ];
                $lineItems[] = [
                    "product_category" => $this->_pinterestHelper->getCategoryNamesFromIds($product->getCategoryIds()),
                    "product_id" => $this->_pinterestHelper->getContentId($product),
                    "product_quantity" => (int) $item->getQtyOrdered(),
                    "product_price" => (string) $price,
                    "product_name" => $product->getName(),
                ];
            }
        }
        return [
            "content_ids" => array_values($productIds),
            "contents" => array_values($contents),
            "num_items" => $numItems,
            "value" => $subTotal,
            "line_tems" => array_values($lineItems),
            "order_id" => $orderId,
        ];
    }
}
