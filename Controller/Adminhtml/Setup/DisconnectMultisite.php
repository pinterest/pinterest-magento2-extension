<?php

namespace Pinterest\PinterestMagento2Extension\Controller\Adminhtml\Setup;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Pinterest\PinterestMagento2Extension\Helper\DisconnectHelper;

/**
 * Controller for the main Pinterest App page
 */
class DisconnectMultisite extends Action
{

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;
    
    /**
     * @var DisconnectHelper
     */
    protected $_disconnectHelper;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param Context $context
     * @param DisconnectHelper $disconnectHelper
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        DisconnectHelper $disconnectHelper,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->_disconnectHelper = $disconnectHelper;
        $this->request = $request;
    }

    /**
     * Execute the Disconnect flow
     *
     * @return bool successful
     */
    public function execute()
    {
        $store = $this->_request->getParam('storeId');
        return $this->_disconnectHelper->disconnectAndCleanup($store);
    }
}
