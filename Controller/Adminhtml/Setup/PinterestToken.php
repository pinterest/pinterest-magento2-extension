<?php

namespace Pinterest\PinterestMagento2Extension\Controller\Adminhtml\Setup;

use Magento\Security\Model\AdminSessionsManager;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Pinterest\PinterestMagento2Extension\Helper\ConfigHelper;
use Pinterest\PinterestMagento2Extension\Helper\ExchangeMetadata;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\LoggingHelper;
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
     * @var ExchangeMetadata
     */
    protected $_exchangeMetadata;

    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var EventManager
     */
    protected $_eventManager;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var LoggingHelper
     */
    protected $_loggingHelper;

    /**
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param ExchangeMetadata $exchangeMetadata
     * @param PinterestHelper $pinterestHelper
     * @param EventManager $eventManager
     * @param ConfigHelper $configHelper
     * @param LoggingHelper $loggingHelper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        ExchangeMetadata $exchangeMetadata,
        PinterestHelper $pinterestHelper,
        EventManager $eventManager,
        ConfigHelper $configHelper,
        LoggingHelper $loggingHelper
    ) {
        parent::__construct($context);
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_request = $request;
        $this->_exchangeMetadata = $exchangeMetadata;
        $this->_pinterestHelper = $pinterestHelper;
        $this->_eventManager = $eventManager;
        $this->_configHelper = $configHelper;
        $this->_loggingHelper = $loggingHelper;
    }

    /**
     * Return a redirect to welcome page with error param
     */
    private function createErrorRedirect()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = ['error' => 'ERROR_CONNECT_BLOCKING'];
        $resultRedirect->setPath('pinterestadmin/setup/index', ['_query' => $params]);
        return $resultRedirect;
    }

    /**
     * Main function which executes when controller is called
     */
    public function execute()
    {
        $this->_pinterestHelper->logInfo("Entered PinterestToken action");
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

                return $this->createErrorRedirect();
            }
            $this->_pinterestHelper->logInfo("PinterestToken action - State check success");

            try {
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
                $this->_pinterestHelper->logInfo("PinterestToken action - advertiser_id = ".$info['advertiser_id']);
                if ($info['tag_id']) {
                    $this->_pinterestHelper->saveMetadata('pinterest/info/tag_id', $info['tag_id']);
                }
                $this->_pinterestHelper->logInfo("PinterestToken action - tag_id = ".$info['tag_id']);
                $this->_pinterestHelper->saveMetadata('pinterest/info/merchant_id', $info['merchant_id']);
                $this->_pinterestHelper->logInfo("PinterestToken action - merchant_id = ".$info['merchant_id']);
                $this->_pinterestHelper->saveEncryptedMetadata('pinterest/info/client_hash', $info['clientHash']);

                // Generate and store external business Id
                $businessId = $this->_pinterestHelper->generateExternalBusinessId($info['advertiser_id']);
                $this->_pinterestHelper->saveMetadata("pinterest/info/business_id", $businessId);
                $this->_pinterestHelper->logInfo(
                    "External Business ID: " . $businessId
                );
                
                $this->_pinterestHelper->logInfo("Successfully saved connection details to database");
            } catch (\Exception $e) {
                $this->_pinterestHelper->logInfo("Failure saving plugin metadata.");
                return $this->createErrorRedirect();
            }

            // save feature flags to config
            try {
                $this->_configHelper->saveFeatureFlags($info['feature_flags']);
            } catch (\Exception $e) {
                $this->_pinterestHelper->logInfo("Failure saving feature flags:");
                $this->_pinterestHelper->logException($e);
                return $this->createErrorRedirect();
            }

            // Send metadata to Pinterest API...
            $this->_exchangeMetadata->postMetadata();
            $this->_pinterestHelper->logInfo("PinterestToken action - POST metadata");

            // flush cache before claiming website
            $this->_pinterestHelper->logInfo("flush cache during connect");
            $this->_pinterestHelper->flushCache();
            $this->_pinterestHelper->logInfo("cache flush completed");

            // Website claiming
            $this->_pinterestHelper->saveMetadata('pinterest/website_claiming/meta_tag', null);
            $this->_eventManager->dispatch("pinterest_commereceintegrationextension_website_claiming");

            $featureFlags = $info['feature_flags'];
            if ($featureFlags['catalog'] === true) {
                // Catalog feed - create if numbers of products < 5000 to avoid a time out. Run cron job for larger size
                $productsCount = $this->_pinterestHelper->getProductCountInAllStores();
                $this->_pinterestHelper->logInfo("PinterestToken action - products count = ".$productsCount);
                if ($productsCount < 5000) {
                    $this->_pinterestHelper->logInfo("PinterestToken action - dispatching create catalog feeds event.");
                    $this->_eventManager->dispatch("pinterest_commereceintegrationextension_create_catalog_feeds");
                }
            }

            $this->_loggingHelper->flushCache();

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('pinterestadmin/setup/index');
            return $resultRedirect;
        }
    }
}
