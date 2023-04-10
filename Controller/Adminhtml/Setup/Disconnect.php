<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Controller\Adminhtml\Setup;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Pinterest\PinterestBusinessConnectPlugin\Helper\DisconnectHelper;

/**
 * Controller for the main Pinterest App page
 */
class Disconnect extends Action
{

    /**
     * @var DisconnectHelper
     */
    protected $_disconnectHelper;

    /**
     * @param Context $context
     * @param DisconnectHelper $disconnectHelper
     */
    public function __construct(
        Context $context,
        DisconnectHelper $disconnectHelper
    ) {
        parent::__construct($context);
        $this->_disconnectHelper = $disconnectHelper;
    }

    /**
     * Execute the Disconnect flow
     *
     * @return bool successful
     */
    public function execute()
    {
        return $this->_disconnectHelper->disconnectAndCleanup();
    }
}
