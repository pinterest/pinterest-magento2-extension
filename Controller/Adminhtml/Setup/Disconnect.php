<?php

namespace Pinterest\PinterestMagento2Extension\Controller\Adminhtml\Setup;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Pinterest\PinterestMagento2Extension\Helper\DisconnectHelper;

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
