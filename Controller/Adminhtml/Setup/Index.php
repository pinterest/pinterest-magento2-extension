<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Controller\Adminhtml\Setup;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;

/**
 * Controller for the page to connect to Pinterest account
 */
class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param PinterestHelper $pinterestHelper
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        PinterestHelper $pinterestHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_pinterestHelper = $pinterestHelper;
    }

    /**
     * Called when we load the Index page
     */
    public function execute()
    {
        if ($this->_pinterestHelper->isUserConnected()) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('pinterestadmin/setup/app');
            return $resultRedirect;
        }
        
        // Load the page defined in view/adminhtml/layout/pinterestadmin_setup_index.xml
        return $this->resultPageFactory->create();
    }
}
