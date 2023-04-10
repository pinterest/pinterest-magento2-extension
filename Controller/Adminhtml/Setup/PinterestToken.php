<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Controller\Adminhtml\Setup;

use Magento\Security\Model\AdminSessionsManager;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Backend\App\Action;

class PinterestToken extends Action
{
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var EventManager
     */
    protected $_eventManager;

    /**
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager
    ) {
        parent::__construct($context);
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_request = $request;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_eventManager = $eventManager;
    }

    public function execute()
    {
        $this->_pinterestHelper->logInfo("Entered PinterestToken action");
        $result = $this->_resultJsonFactory->create();
        $admin_session = $this->_pinterestHelper
        ->createObject(AdminSessionsManager::class)
        ->getCurrentSession();
        if (!$admin_session && $admin_session->getStatus() != 1) {
            $e = new \Exception('Oops, this endpoint is for logged in admin and ajax only!');
            $this->_pinterestHelper->logException($e);
            throw $e;
        } else {
            $this->_pinterestHelper->logInfo("PinterestToken action - Admin login check success");
            $state = $this->_request->getParam('state');
            if (strlen($state) > 0 && $state != $this->_pinterestHelper->getMetadataValue("ui/state")) {
                $e = new \Exception("State didnt match with expected value");
                $this->_pinterestHelper->logException($e);
                throw $e;
            }
            $this->_pinterestHelper->logInfo("PinterestToken action - State check success");
            //Reset state once its success
            $this->_pinterestHelper->saveMetadata('ui/state', '');

            $token_data = json_decode(rawurldecode(base64_decode($this->_request->getParam('token_data'))), true);
            $this->_pinterestHelper->saveEncryptedMetadata(
                'pinterest/token/access_token',
                $token_data['access_token']
            );
            $this->_pinterestHelper->saveEncryptedMetadata(
                'pinterest/token/refresh_token',
                $token_data['refresh_token']
            );
            $this->_pinterestHelper->saveMetadata('pinterest/token/token_type', $token_data['token_type']);
            // expires_in and refresh_token_expires_in are the lifetime (in seconds) for access token and refresh token respectively
            $this->_pinterestHelper->saveMetadata('pinterest/token/expires_in', $token_data['expires_in']);
            $this->_pinterestHelper->saveMetadata(
                'pinterest/token/refresh_token_expires_in',
                $token_data['refresh_token_expires_in']
            );
            $this->_pinterestHelper->saveMetadata('pinterest/token/scope', $token_data['scope']);
    
            $info = json_decode(rawurldecode(base64_decode($this->_request->getParam('info'))), true);
            $this->_pinterestHelper->saveMetadata('pinterest/info/advertiser_id', $info['advertiser_id']);
            $this->_pinterestHelper->saveMetadata('pinterest/info/tag_id', $info['tag_id']);
            $this->_pinterestHelper->saveMetadata('pinterest/info/merchant_id', $info['merchant_id']);
            
            $this->_pinterestHelper->saveEncryptedMetadata('pinterest/info/client_hash', $info['clientHash']);
            
            $this->_pinterestHelper->logInfo("Successfully saved connection details to database");
            
            // Generate and store external business Id
            $businessId = $this->_pinterestHelper->generateExternalBusinessId($info['advertiser_id']);
            $this->_pinterestHelper->saveMetadata("pinterest/info/business_id", $businessId);
            $this->_pinterestHelper->logInfo(
                "External Business ID: " . $businessId . " successfully saved to database"
            );

            // Send metadata to Pinterest API...
            $this->_pinterestHelper->exchangeMetadata($info);

            // flush cache before claiming website
            $this->_pinterestHelper->logInfo("flush cache during connect");
            $this->_pinterestHelper->flushCache();

            // Website claiming
            $this->_eventManager->dispatch("pinterest_commereceintegrationextension_website_claiming");

            // Catalog feed creating
            $this->_eventManager->dispatch("pinterest_commereceintegrationextension_create_catalog_feeds");

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('pinterestadmin/setup/index');
            return $resultRedirect;
        }
    }
}
