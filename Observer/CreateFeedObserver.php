<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Observer;

use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\CatalogFeedClient;
use Pinterest\PinterestBusinessConnectPlugin\Helper\ProductExporter;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CreateFeedObserver implements ObserverInterface
{
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var CatalogFeedClient
     */
    protected $_catalogFeedClient;

    /**
     * @var ProductExporter
     */
    protected $_productExporter;

    /**
     * Create Feeds Observer constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param CatalogFeedClient $catalogFeedClient
     * @param ProductExporter $productExporter
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        CatalogFeedClient $catalogFeedClient,
        ProductExporter $productExporter
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_productExporter = $productExporter;
    }

    /**
     * Calls the Conversion API with the event data
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $success_count = $this->_productExporter->processExport();
        $this->_pinterestHelper->logInfo("Pinterest catalog generated {$success_count} feed(s).");
        $this->_catalogFeedClient->createAllFeeds();
    }
}
