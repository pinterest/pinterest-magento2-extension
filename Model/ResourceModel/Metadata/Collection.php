<?php
namespace Pinterest\PinterestBusinessConnectPlugin\Model\ResourceModel\Metadata;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Pinterest\PinterestBusinessConnectPlugin\Model\Metadata', 'Pinterest\PinterestBusinessConnectPlugin\Model\ResourceModel\Metadata');
    }
}
