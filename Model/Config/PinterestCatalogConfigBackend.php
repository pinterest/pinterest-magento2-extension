<?php
namespace Pinterest\PinterestMagento2Extension\Model\Config;

use \Magento\Framework\App\Config\Value;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\ExchangeMetadata;
use Pinterest\PinterestMagento2Extension\Helper\CatalogFeedClient;
use \Magento\Framework\Model\Context;
use \Magento\Framework\Registry;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Cache\TypeListInterface;
use \Magento\Framework\Model\ResourceModel\AbstractResource;
use \Magento\Framework\Data\Collection\AbstractDb;
use Pinterest\PinterestMagento2Extension\Helper\DisconnectHelper;

class PinterestCatalogConfigBackend extends \Magento\Framework\App\Config\Value
{

    /**
     * @var DisconnectHelper
     */
    protected $_disconnectHelper;

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
     *
     * @var CatalogFeedClient $catalogFeedClient
     */
    protected $_catalogFeedClient;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param PinterestHelper $pinterestHelper
     * @param ExchangeMetadata $exchangeMetadata
     * @param CatalogFeedClient $catalogFeedClient
     * @param DisconnectHelper $disconnectHelper
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
        CatalogFeedClient $catalogFeedClient,
        DisconnectHelper $disconnectHelper,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_exchangeMetadata = $exchangeMetadata;
        $this->_catalogFeedClient = $catalogFeedClient;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->_disconnectHelper = $disconnectHelper;
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
                if ($this->getValue() == "enabled") {
                    $this->_catalogFeedClient->createAllFeeds(false);
                } else {
                    $this->_disconnectHelper->deleteFeedsFromPinterest();
                }
            }
        }
        return parent::afterSave();
    }
}
