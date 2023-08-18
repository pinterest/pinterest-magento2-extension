<?php

namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml\System;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Config\Model\Config\CommentInterface;

class LDPComment extends AbstractBlock implements CommentInterface
{
    public function getCommentText($elementValue)
    {
        $url = "https://developers.pinterest.com/docs/conversions/limitedprocessing/";
        return __("To help comply with the California Privacy Rights Act, Pinterest can act as a service provider to limit how we use certain data to help you comply with user privacy settings. <a href='%1'>Learn more</a>", $url);
    }
}
