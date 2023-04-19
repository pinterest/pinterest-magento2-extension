<?php
namespace Pinterest\PinterestMagento2Extension\Model\ResourceModel;

class Metadata extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('pinterest_integration_extension_metadata', 'metadata_key');
        $this->_isPkAutoIncrement = false;
    }
}
