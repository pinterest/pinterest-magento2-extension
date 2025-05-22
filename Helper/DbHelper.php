<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Pinterest\PinterestMagento2Extension\Constants\MetadataName;

class DbHelper
{
    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var MetadataFactory
     */
    protected $_metadataFactory;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @param Logger $logger
     * @param MetadataFactory $metadataFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Logger $logger,
        MetadataFactory $metadataFactory,
        EncryptorInterface $encryptor
    ) {
        $this->_logger = $logger;
        $this->_metadataFactory = $metadataFactory;
        $this->_encryptor = $encryptor;
    }

    /**
     * Log exception to file (do not cache)
     *
     * @param \Exception $e
     */
    protected function logException($e)
    {
        $this->_logger->error($e->getMessage());
        $this->_logger->error($e->getTraceAsString());
    }

    /**
     * Used to save non encrypted data from the db
     *
     * @param string $metadataKey
     * @param mixed $metadataValue
     */
    public function saveMetadata($metadataKey, $metadataValue)
    {
        try {
            $metadataRow = $this->_metadataFactory->create();
            $metadataRow->setData([
                'metadata_key' => $metadataKey,
                'metadata_value' => $metadataValue
            ]);
            $metadataRow->save();
        } catch (\Throwable $e) {
            $this->_logger->info("In exception of saveMetadata ". $e->getMessage());
            $this->logException($e);
        }
    }

    /**
     * Used to save encrypted data from the db
     *
     * @param string $metadataKey
     * @param mixed $metadataValue
     */
    public function saveEncryptedMetadata($metadataKey, $metadataValue)
    {
        $this->saveMetadata($metadataKey, $this->_encryptor->encrypt($metadataValue));
    }

    /**
     * Used to get non encrypted data from the db
     *
     * @param string $metadataKey
     */
    public function getMetadataValue($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
        } catch (\Throwable $e) {
            $this->logException($e);
            return null;
        }
        return $metadataRow ? $metadataRow->getData('metadata_value') : null;
    }

    /**
     * Used to get timestamp of last row update from the db
     *
     * @param string $metadataKey
     */
    public function getUpdatedAt($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
        } catch (\Throwable $e) {
            $this->logException($e);
            return null;
        }
        return $metadataRow ? $metadataRow->getData('updated_at') : null;
    }

    /**
     * Delete the data associated with the metadata key
     *
     * @param string $metadataKey
     */
    public function deleteMetadata($metadataKey)
    {
        try {
            $metadataRow = $this->_metadataFactory->create()->load($metadataKey);
            $metadataRow->delete();
        } catch (\Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * Delete metadata for a specific store
     *
     * @param string $storeId
     */
    public function deleteAllMetadataForStore($storeId)
    {
        try {
            $collection = $this->_metadataFactory->create()->getCollection();
            $collection->addFieldToFilter('metadata_key', [['like' => MetadataName::PINTEREST_INFO_PREFIX . $storeId . '%'], 
                ['like' => MetadataName::PINTEREST_TOKEN_PREFIX . $storeId . '%'],
                ['eq' => 'pinterest/website_claiming/meta_tag/' . $storeId],
                ['eq' => 'pinterest/info/feed_ids/' . $storeId]]);
            foreach ($collection as $item) {
                $item->delete();
            }
            $this->_logger->info("Successfully deleted connection details from database");
            return true;
        } catch (\Throwable $e) {
            $this->logException($e);
            return false;
        }
    }

    /**
     * Delete all the metadata values
     */
    public function deleteAllMetadata()
    {
        try {
            $collection = $this->_metadataFactory->create()->getCollection();
            foreach ($collection as $item) {
                $item->delete();
            }
            $this->_logger->info("Successfully deleted connection details from database");
            return true;
        } catch (\Throwable $e) {
            $this->logException($e);
            return false;
        }
    }

    /**
     * Used to get encrypted data from the db
     *
     * @param string $metadataKey
     */
    public function getEncryptedMetadata($metadataKey)
    {
        return $this->_encryptor->decrypt($this->getMetadataValue($metadataKey));
    }

    /**
     * Get access token from metadata
     *
     * @return string\null
     */
    public function getAccessToken($store = null)
    {
        $pinterest_token_prefix = MetadataName::PINTEREST_TOKEN_PREFIX;

        if ($store != null) {
            $pinterest_token_prefix =  $pinterest_token_prefix . $store . '/';
        }

        return $this->getEncryptedMetadata($pinterest_token_prefix . "access_token");
    }
}
