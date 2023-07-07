<?php

namespace Pinterest\PinterestMagento2Extension\Controller\Adminhtml\Ajax;

use Exception;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Cron\Catalog;

class RegenerateFeeds extends \Magento\Backend\App\Action
{
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var PinterestHelper $pinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var Catalog $catalog
     */
    protected $_catalog;

    /**
     * Defaut constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param PinterestHelper $pinterestHelper
     * @param Catalog $catalog
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PinterestHelper $pinterestHelper,
        Catalog $catalog
    ) {
        parent::__construct($context);
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_catalog = $catalog;
    }

    /**
     * Kick off the catalog generation cron job
     */
    public function execute()
    {
        $this->_pinterestHelper->logInfo("Pinterest catalog cron kicked off manually");
        $response = $this->_catalog->execute();
        $result = $this->_resultJsonFactory->create();
        $result->setData($response);
        return $result;
    }
}
