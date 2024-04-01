<?php

namespace Pinterest\PinterestMagento2Extension\Observer;

use Pinterest\PinterestMagento2Extension\Constants\IntegrationErrorId;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

class WebsiteClaimingObserver implements ObserverInterface
{
    /**
     * @var PluginErrorHelper
     */
    protected $_pluginErrorHelper;
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var PinterestHttpClient
     */
    protected $_pinterestHttpClient;

    /**
     * Website Claiming Observer constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param PinterestHttpClient $pinterestHttpClient
     * @param PluginErrorHelper $pluginErrorHelper
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        PinterestHttpClient $pinterestHttpClient,
        PluginErrorHelper $pluginErrorHelper
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_pinterestHttpClient = $pinterestHttpClient;
        $this->_pluginErrorHelper = $pluginErrorHelper;
    }

    /**
     * Call the website claiming API
     *
     * Call the API to get the HTML meta tag we can
     * use for website claiming
     *
     * If the API is not successful, we save the error to the metadata
     *
     * @return true if the call is successful
     */
    public function getWebsiteClaimingMetaTag()
    {
        $this->_pinterestHelper->logInfo("Attemping to get the website claiming html tag");
        try {
            $this->_pinterestHelper->resetApiErrorState("errors/website_claiming/meta_tag");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("user_account/websites/verification");
            $response = $this->_pinterestHttpClient->get($url, $this->_pinterestHelper->getAccessToken());
            if (isset($response->metatag) && $response->metatag != null) {
                /*
                 * Since we are implementing website claiming via HTML tag, we are only saving
                 * the meta tag details
                */
                $this->_pinterestHelper->logInfo("Succesfully generated metatag for website claiming");
                $this->_pinterestHelper->saveMetadata("pinterest/website_claiming/meta_tag", $response->metatag);
                return true;
            } else {
                $this->_pinterestHelper->logError("Unable to generate meta tag details");
                $this->_pinterestHelper->logAndSaveAPIErrors($response, "errors/website_claiming/meta_tag");
            }
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Get all the existing websites that have been successfully claimed by the user
     *
     * @return array
     */
    public function getExistingClaimedWebsites()
    {
        $this->_pinterestHelper->logInfo("Attemping to all existing claimed websites from pinterest");
        try {
            $this->_pinterestHelper->resetApiErrorState("errors/website_claiming/existing_websites/");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("user_account/websites");
            $response = $this->_pinterestHttpClient->get($url, $this->_pinterestHelper->getAccessToken());
            if (isset($response->items) && $response->items != null) {
                $existingClaimedWebsites = [];
                foreach ($response->items as $item) {
                    if ($item && isset($item->status) && $item->status == "verified") {
                        $existingClaimedWebsites[] = isset($item->website) ? $item->website : "";
                    }
                }
                return $existingClaimedWebsites;
            } else {
                $this->_pinterestHelper->logError("Failed to get existing claimed websites");
                $this->_pinterestHelper->logAndSaveAPIErrors($response, "errors/website_claiming/existing_websites/");
            }
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return [];
    }

    /**
     * Gets the list of all the unclaimed base URLs associated with the magento account
     */
    public function getWebsitesToClaim()
    {
        $base_urls = $this->_pinterestHelper->getBaseUrls();
        return array_diff(array_unique($base_urls), $this->getExistingClaimedWebsites());
    }

    /**
     * Call the website claiming API
     *
     * If the API is not successful, we save the error to the metadata
     *
     * @param string $website
     * @return true if the call is successful
     */
    public function claimWebsiteOnPinterest($website)
    {
        $this->_pinterestHelper->logInfo("Attemping to claim website ($website) on pinterest");
        try {
            $this->_pluginErrorHelper->clearError("errors/website_claiming/{$website}");
            $url = $this->_pinterestHttpClient->getV5ApiEndpoint("user_account/websites");
            $params = [
                "website" => $website,
                "verification_method" => "METATAG",
            ];
            $response = $this->_pinterestHttpClient->post($url, $params, $this->_pinterestHelper->getAccessToken());
            if (isset($response->status) && $response->status != null) {
                /*
                 If status = "success", then the website is claimed successfully
                 If status = "already_verified_by_user", then the website was claimed previously
                 In both case, the website is claimed as expected
                */
                $this->_pinterestHelper->logInfo("Succesfully claimed website ($website) on Pinterest");
                return true;
            } else {
                /*
                 Common error codes:
                 Code 71: The website has been claimed by another user
                 Code 29: You are not permitted to access that resource
                 Code 75: Website is invalid or does not exist
                 */
                $this->_pinterestHelper->logError("Website claiming failed for URL ($website)");
                $errorData = ['website' => $website, 'errorCode' => $response->code];
                $this->_pluginErrorHelper->logAndSaveError(
                    "errors/website_claiming/{$website}",
                    $errorData,
                    $response->message,
                    IntegrationErrorId::ERROR_DOMAIN_CLAIMING
                );
            }
        } catch (\Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
        return false;
    }

    /**
     * Run the steps required to claim a website successfully
     *
     * 1) Call the Pinterest API to the HTML meta tag that needs to be inserted
     * 2) Insert the HTML Meta Tag (see view/layout/default_head_blocks.xml)
     * 3) Call the website claming API on pinterest to try to claim the website
     *
     * There are two ways to do website claiming. One is via HTML Tag
     * and second is the HTML file. Currently, we are implementing the HTML
     * Tag
     *
     * @param Observer $observer
     * @return true if the call is successful
     */
    public function execute(Observer $observer)
    {
        $this->_pinterestHelper->logInfo("Attemping to claim website");
        try {
            $success = $this->getWebsiteClaimingMetaTag();
            /**
             * Only if we were able to successfully get the website claiming html tag
             * will we try to call to API to claim the website
             */
            if ($success) {
                $websitesToClaim = $this->getWebsitesToClaim();
                $success = true;
                foreach ($websitesToClaim as $website) {
                    $success &= $this->claimWebsiteOnPinterest($website);
                }
                return $success;
            }
        } catch (\Exception $e) {
            $this->logException($e);
        }
        return false;
    }
}
