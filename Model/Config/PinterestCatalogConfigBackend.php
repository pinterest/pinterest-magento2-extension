<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use \Magento\Framework\App\Config\Value;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\ExchangeMetadata;
use \Magento\Framework\Model\Context;
use \Magento\Framework\Registry;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Cache\TypeListInterface;
use \Magento\Framework\Model\ResourceModel\AbstractResource;
use \Magento\Framework\Data\Collection\AbstractDb;

class PinterestCatalogConfigBackend extends \Magento\Framework\App\Config\Value
{
    /**
     *
     * @var ExchangeMetadata $exchangeMetadata
     */
    protected $_exchangeMetadata;
    
    /**
     *
     * @var PinterestHelper $pinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param PinterestHelper $pinterestHelper
     * @param ExchangeMetadata $exchangeMetadata
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        PinterestHelper $pinterestHelper,
        ExchangeMetadata $exchangeMetadata,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_exchangeMetadata = $exchangeMetadata;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Update Pinterest metadata when catalog flag is changed
     * @return $this
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->_pinterestHelper->logInfo("Catalog flag changed in config from ". $this->getOldValue(). " to ". $this->getValue());
            if ($this->_pinterestHelper->isUserConnected()) {
                $this->_exchangeMetadata->patchMetadata();
            }

        }
        return parent::afterSave();
    }
}
