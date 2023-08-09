<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;
use \Magento\Framework\Escaper;

class Search extends Setup
{
    /**
     * Get the query parameter used for search
     */
    public function getSearchQuery()
    {
        $escaper = new \Magento\Framework\Escaper;
        return $escaper -> escapeHtml(
            $this->getRequest()->getParam("q"),
            ENT_QUOTES,
            "UTF-8"
        );
    }
}
