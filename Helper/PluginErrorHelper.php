<?php

namespace Pinterest\PinterestMagento2Extension\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;

class PluginErrorHelper
{

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;
    /**
     * @var MetadataFactory
     */
    protected $_metadataFactory;

    /**
     *
     * @param PinterestHelper $pinterestHelper
     * @param MetadataFactory $metadataFactory
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        MetadataFactory $metadataFactory
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_metadataFactory = $metadataFactory;
    }

    /**
     * Store any type of error in database, where metadataValue is a JSON object
     *
     * @param string $dbPath - Path of where to save the error in DB
     * @param string $errorData - Info about the error
     * @param string $message - error message
     * @param string $integrationErrorId - Error id to be used by Pinterest
     * @param int $code
     */
    public function logAndSaveError($dbPath, $errorData, $message, $integrationErrorId, $code = 0)
    {
        $metadataValueArray = [
            'integration_error_id' => $integrationErrorId,
            'data' => $errorData
        ];

        $this->_pinterestHelper->saveMetadata($dbPath, json_encode($metadataValueArray), JSON_FORCE_OBJECT);

        /* TODO write a new function for logging */
        $this->_pinterestHelper->logError($dbPath);
        $this->_pinterestHelper->logError('Error data: '.implode(" ", $errorData));
        $this->_pinterestHelper->logError('Error message: '.$message);
    }

    /**
     * Clear an error from the database
     *
     * @param string $dbPath - Path where error is saved in db
     */
    public function clearError($dbPath)
    {
        if ($this->_pinterestHelper->getMetadataValue($dbPath) != null) {
            $this->_pinterestHelper->deleteMetadata($dbPath);
        }
    }

    /**
     * Get all errors stored in the database
     *
     * @return array error JSON objects with integration_error_id and data
     */
    public function getAllStoredErrors()
    {
        $errors = [];
        try {
            $collection = $this->_metadataFactory->create()->getCollection();
            foreach ($collection as $item) {
                $metadataKey = $item->getData('metadata_key');
                if ((strlen($metadataKey)) >= strlen("errors/")) {
                    if ((substr($metadataKey, 0, strlen("errors/"))) == "errors/") {
                        $errorObject = json_decode(
                            $this->_pinterestHelper->getMetadataValue($metadataKey)
                        );
                        array_push($errors, $errorObject);
                    }
                }
                
            }
        } catch (\Throwable $e) {
            $this->_pinterestHelper->logException($e);
        }
        return $errors;
    }
}
