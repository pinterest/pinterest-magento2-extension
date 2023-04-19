<?php

namespace Pinterest\PinterestMagento2Extension\Block\Tag;

use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;

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
