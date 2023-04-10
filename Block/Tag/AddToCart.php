<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Block\Tag;

use Pinterest\PinterestBusinessConnectPlugin\Block\Adminhtml\Setup;

class AddToCart extends Setup
{
    /**
     * Returns axios url for add to cart event
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProductInfoUrl()
    {
        return sprintf("%spin/Tag/ProductInfoForAddToCart", $this->_pinterestHelper->getBaseUrl());
    }
}
