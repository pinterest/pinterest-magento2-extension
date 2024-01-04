<?php

namespace Pinterest\PinterestMagento2Extension\Cron;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\TokensHelper;
use Magento\Framework\Event\ManagerInterface as EventManager;

class ClaimDomains
{
    /**
     * @var PinterestHelper $pinterestHelper
     */
    protected $_pinterestHelper;
    
    /**
     * @var EventManager
     */
    protected $_eventManager;

    /**
     * Constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        EventManager $eventManager
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_eventManager = $eventManager;
    }
    
    /**
     * Execute cron job
     */
    public function execute()
    {
        $this->_pinterestHelper->logInfo("Pinterest claim domains cron job started");
        if ($this->_pinterestHelper->isUserConnected()) {
            $this->_pinterestHelper->logInfo("User is logged in. Calling website claiming observer");
            $this->_eventManager->dispatch(
                "pinterest_commereceintegrationextension_website_claiming"
            );
        }
        $this->_pinterestHelper->logInfo("Pinterest claim domains cron job ended");
    }
}
