<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml\System;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Config\Model\Config\CommentInterface;

class LDPComment extends AbstractBlock implements CommentInterface
{
    public function getCommentText($elementValue)
    {
        $url = "https://developers.pinterest.com/docs/conversions/limitedprocessing/";
        return __("Enable the LDP flag to comply with obligations under CCPA and CPRA when using Pinterest's ad service. <a href='%1' target='_blank'>Learn more</a>", $url);
    }
}
