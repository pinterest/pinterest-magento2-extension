<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Cron;

use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\TokensHelper;

class RefreshTokens
{
    /**
     * @var PinterestHelper $pinterestHelper
     */
    protected $_pinterestHelper;
    
    /**
     * @var TokensHelper $tokensHelper
     */
    protected $_tokensHelper;

    /**
     * Constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param TokensHelper $tokensHelper
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        TokensHelper $tokensHelper
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_tokensHelper = $tokensHelper;
    }
    public function execute()
    {
        if ($this->_pinterestHelper->isUserConnected()) {
            $success = $this->_tokensHelper->refreshTokens();
            if ($success) {
                $this->_pinterestHelper->logInfo("Job to refresh Pinterest tokens successful.");
            } else {
                $this->_pinterestHelper->logError("Job to refresh Pinterest tokens failed.");
            }
        }
    }
}
