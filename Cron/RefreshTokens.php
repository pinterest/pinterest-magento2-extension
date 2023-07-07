<?php

namespace Pinterest\PinterestMagento2Extension\Cron;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\TokensHelper;

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
    
    /**
     * Execute cron job
     */
    public function execute()
    {
        $this->_pinterestHelper->logInfo("Pinterest token refresh cron job started");
        if ($this->_pinterestHelper->isUserConnected()) {
            $this->_pinterestHelper->logInfo("Pinterest token refresh cron job calling API");
            $success = $this->_tokensHelper->refreshTokens();
            if ($success) {
                $this->_pinterestHelper->logInfo("Job to refresh Pinterest tokens successful.");
            } else {
                $this->_pinterestHelper->logError("Job to refresh Pinterest tokens failed.");
            }
        }
        $this->_pinterestHelper->logInfo("Pinterest token refresh cron job ended");
    }
}
