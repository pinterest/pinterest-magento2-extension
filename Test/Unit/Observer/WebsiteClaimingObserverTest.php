<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Observer;

use Pinterest\PinterestMagento2Extension\Observer\WebsiteClaimingObserver;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;
use PHPUnit\Framework\TestCase;

use Magento\Framework\Event\Observer;

class WebsiteClaimingObserverTest extends TestCase
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
     * @var WebsiteClaimingObserver
     */
    protected $_websiteClaimingObserver;

    /**
     * Used to set the values before running a test
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_pinterestHttpClient = $this->createMock(PinterestHttpClient::class);
        $this->_pluginErrorHelper = $this->createMock(PluginErrorHelper::class);
        $this->_websiteClaimingObserver = new WebsiteClaimingObserver(
            $this->_pinterestHelper,
            $this->_pinterestHttpClient,
            $this->_pluginErrorHelper
        );
    }

    public function testGetHTMLTagReturnsTrueIfAPIisSuccessful()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "metatag" => "<meta name=\"p:domain_verify\" content=\"12345\" />"
        ])));
        $success = $this->_websiteClaimingObserver->getWebsiteClaimingMetaTag();
        $this->assertTrue($success);
    }

    public function testGetHTMLTagReturnsFalseIfAPIisUnsuccessful()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "code" => "1",
            "message" => "Authentication Error",
        ])));
        $success = $this->_websiteClaimingObserver->getWebsiteClaimingMetaTag();
        $this->assertFalse($success);
    }

    public function testClaimWebsiteTrueIfAPIisSuccessful()
    {
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "status" => "Website Claimed!"
        ])));
        $success = $this->_websiteClaimingObserver->claimWebsiteOnPinterest("www.pinterest.com");
        $this->assertTrue($success);
    }

    public function testClaimWensiteFalseIfAPIisUnsuccessful()
    {
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "code" => "1",
            "message" => "Authentication Error",
        ])));
        $success = $this->_websiteClaimingObserver->claimWebsiteOnPinterest("www.pinterest.com");
        $this->assertFalse($success);
    }

    public function testGetExistingWebsitesWithErrorResponse()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "code" => "1",
            "message" => "Authentication Error",
        ])));
        $response = $this->_websiteClaimingObserver->getExistingClaimedWebsites();
        $this->assertEquals([], $response);
    }

    public function testGetExistingWebsitesWithEmptyResponse()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "items" => []
        ])));
        $response = $this->_websiteClaimingObserver->getExistingClaimedWebsites();
        $this->assertEquals([], $response);
    }

    public function testGetExistingWebsitesWithVerifiedSingleWebsiteResponse()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "items" => [
                [
                    "status" => "verified",
                    "website" => "www.pinterest.com",
                ]
            ]
        ])));
        $response = $this->_websiteClaimingObserver->getExistingClaimedWebsites();
        $this->assertEquals(["www.pinterest.com"], $response);
    }

    public function testGetExistingWebsitesWithVerifiedMultiWebsiteResponse()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "items" => [
                [
                    "status" => "verified",
                    "website" => "www.pinterest.com",
                ],
                [
                    "status" => "verified",
                    "website" => "www.developer.pinterest.com",
                ]
            ]
        ])));
        $response = $this->_websiteClaimingObserver->getExistingClaimedWebsites();
        $this->assertEquals(["www.pinterest.com", "www.developer.pinterest.com"], $response);
    }

    public function testGetExistingWebsitesWithNonVerifiedResponse()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "items" => [
                [
                    "status" => "verified",
                    "website" => "www.pinterest.com",
                ],
                [
                    "status" => "",
                    "website" => "www.developer.pinterest.com",
                ]
            ]
        ])));
        $response = $this->_websiteClaimingObserver->getExistingClaimedWebsites();
        $this->assertEquals(["www.pinterest.com"], $response);
    }

    public function testGetWebsitesToClaimWithSingleBaseURLs()
    {
        $this->_pinterestHelper->method("getBaseUrls")->willReturn(["www.pinterest.com"]);
        $response = $this->_websiteClaimingObserver->getWebsitesToClaim();
        $this->assertEquals(["www.pinterest.com"], $response);
    }

    public function testGetWebsitesToClaimWithDuplicateBaseURLs()
    {
        $this->_pinterestHelper->method("getBaseUrls")->willReturn(["www.pinterest.com", "www.pinterest.com"]);
        $response = $this->_websiteClaimingObserver->getWebsitesToClaim();
        $this->assertEquals(["www.pinterest.com"], $response);
    }

    public function testGetWebsitesToClaimWithMultipleBaseURLS()
    {
        $this->_pinterestHelper->method("getBaseUrls")->willReturn(["www.pinterest.com", "www.dev.pinterest.com"]);
        $response = $this->_websiteClaimingObserver->getWebsitesToClaim();
        $this->assertEquals(["www.pinterest.com", "www.dev.pinterest.com"], $response);
    }

    public function testGetWebsitesToClaimWithPreviouslyVerifiedURL()
    {
        $this->_pinterestHttpClient->method("get")->willReturn(json_decode(json_encode([
            "items" => [                [
                "status" => "verified",
                "website" => "www.pinterest.com",
            ]]
        ])));
        $this->_pinterestHelper->method("getBaseUrls")->willReturn(["www.pinterest.com", "www.dev.pinterest.com"]);
        $response = $this->_websiteClaimingObserver->getWebsitesToClaim();
        $this->assertEquals(["www.dev.pinterest.com"], array_values($response));
    }
}
