<?php
namespace Pinterest\PinterestMagento2Extension\Model;

class Metadata extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'pinterest_integration_extension_metadata';

   /**
    * (non-PHPdoc)
    * will be called whenever a model is instantiated
    * @see \Magento\Framework\Model\AbstractModel::_construct()
    */
    protected function _construct()
    {
        $this->_init('Pinterest\PinterestMagento2Extension\Model\ResourceModel\Metadata');
    }

    /**
     * required cache clear after database operation
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getMetadataKey()];
    }
}
