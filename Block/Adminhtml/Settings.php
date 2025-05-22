<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Store\Model\StoreManagerInterface;


class Settings extends Setup
{
    protected $_dataPersistor;
    
    public function __construct(
        Context $context,
        PluginErrorHelper $pluginErrorHelper,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        Registry $registry,
        CustomerDataHelper $customerDataHelper,
        StoreManagerInterface $storeManager,
        DataPersistorInterface $dataPersistor,
        array $data = []
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
        $this->_dataPersistor = $dataPersistor;
    }

    /**
     * Returns the storeId stored in dataPersistor
     *
     * @return number
     */
    public function getStoreId()
    {
        return $this->_dataPersistor->get('storeId');
    }

}

