<?php
namespace Pinterest\PinterestMagento2Extension\Model\ResourceModel\Metadata;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Pinterest\PinterestMagento2Extension\Model\Metadata', 'Pinterest\PinterestMagento2Extension\Model\ResourceModel\Metadata');
    }
}
