<?php

namespace Pinterest\PinterestMagento2Extension\Observer;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use Pinterest\PinterestMagento2Extension\Helper\ProductExporter;
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
        if (filter_var($this->_pinterestHelper
                ->getConfig("disable_create_feeds_on_connection"), FILTER_VALIDATE_BOOLEAN)
            || ! $this->_pinterestHelper->isUserConnected()) {
            return;
        }

        $success_count = $this->_productExporter->processExport();
        $this->_pinterestHelper->logInfo("Pinterest catalog generated {$success_count} feed(s).");
        
        $this->_catalogFeedClient->createAllFeeds();
    }
}
