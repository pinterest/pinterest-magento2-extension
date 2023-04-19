<?php

namespace Pinterest\PinterestMagento2Extension\Observer;

use Pinterest\PinterestMagento2Extension\Helper\ConversionEventHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ConversionsApiObserver implements ObserverInterface
{
    /**
     * @var ConversionEventHelper
     */
    protected $_conversionEventHelper;

    /**
     * Conversion API Observer constructor
     *
     * @param ConversionEventHelper $conversionEventHelper
     */
    public function __construct(
        ConversionEventHelper $conversionEventHelper
    ) {
        $this->_conversionEventHelper = $conversionEventHelper;
    }

    /**
     * Calls the Conversion API with the event data
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $this->_conversionEventHelper->processConversionEvent(
            $observer->getData("event_id"),
            $observer->getData("event_name"),
            $observer->getData("custom_data"),
        );
    }
}
