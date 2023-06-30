<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;

class Search extends Setup
{
    /**
     * Get the query parameter used for search
     */
    public function getSearchQuery()
    {
        return htmlspecialchars(
            $this->getRequest()->getParam("q"),
            ENT_QUOTES,
            "UTF-8"
        );
    }
}
