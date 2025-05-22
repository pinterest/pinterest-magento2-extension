<?php
use PHPUnit\Framework\TestCase;
use Pinterest\PinterestMagento2Extension\Block\Adminhtml\Setup;
use Pinterest\PinterestMagento2Extension\Helper\PluginErrorHelper;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\CustomerDataHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class SetupTest extends TestCase
{
    private $setup;
    private $pluginErrorHelperMock;
    private $pinterestHelperMock;
    private $customerDataHelperMock;
    private $eventManagerMock;
    private $registryMock;
    private $storeManagerMock;
    private $contextMock;
    protected function setUp(): void
    {
        $this->pluginErrorHelperMock = $this->createMock(PluginErrorHelper::class);
        $this->pinterestHelperMock = $this->createMock(PinterestHelper::class);
        $this->customerDataHelperMock = $this->createMock(CustomerDataHelper::class);
        $this->eventManagerMock = $this->createMock(EventManager::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->setup = new Setup(
            $this->contextMock,
            $this->pluginErrorHelperMock,
            $this->pinterestHelperMock,
            $this->eventManagerMock,
            $this->registryMock,
            $this->customerDataHelperMock,
            $this->storeManagerMock
        );
    }
    
    public function testGetHashedEmailId()
    {
        $email = 'test@example.com';
        $hashedEmail = 'hashedEmail';
        $this->customerDataHelperMock->method('getEmail')->willReturn($email);
        $this->customerDataHelperMock->method('hash')->with($email)->willReturn($hashedEmail);
        $this->assertEquals($hashedEmail, $this->setup->getHashedEmailId());
    }

    public function testGetCurrency()
    {
        $currency = 'USD';
        $this->pinterestHelperMock->method('getCurrency')->willReturn($currency);
        $this->assertEquals($currency, $this->setup->getCurrency());
    }

    public function testGetRedirectUrl()
    {
        $url = 'https://example.com/redirect';
        $this->pinterestHelperMock->method('getUrl')->with(PinterestHelper::REDIRECT_URI)->willReturn($url);
        $this->assertEquals($url, $this->setup->getRedirectUrl());
    }

    public function testIsUserConnected()
    {
        $this->pinterestHelperMock->method('isUserConnected')->willReturn(true);
        $this->assertTrue($this->setup->isUserConnected());
    }

    public function testIsTagEnabled()
    {
        $this->pinterestHelperMock->method('isUserConnected')->willReturn(true);
        $this->pinterestHelperMock->method('isConversionConfigEnabled')->willReturn(true);
        $this->assertTrue($this->setup->isTagEnabled());
    }
   
    public function testGetWebsites()
    {
        $websites = 'websitesData';
        $this->pinterestHelperMock->method('getStoresData')->willReturn($websites);
        $this->assertEquals($websites, $this->setup->getWebsites());
    }

    public function testGetConnectedStoreIds()
    {
        $connectedStores = '1';
        $metadata = [
        "storeName" => 'Store Name',
        "baseUrl" => 'https://store.com'
        ];
        $this->pinterestHelperMock->method('getMetadataValue')->with('pinterest/multisite/stores')->willReturn($connectedStores);
        $this->pinterestHelperMock->method('getPartnerMetadata')->willReturn($metadata);
        $this->pinterestHelperMock->method('getPinterestBaseUrl')->willReturn('pinterestBaseUrlValue');
        $this->pinterestHelperMock->method('getAccessToken')->willReturn('accessTokenValue');
        $this->pinterestHelperMock->method('getAdvertiserId')->willReturn('advertiserIdValue');
        $this->pinterestHelperMock->method('getMerchantId')->willReturn('merchantIdValue');
        $this->pinterestHelperMock->method('getTagId')->willReturn('tagIdValue');
        $this->pinterestHelperMock->method('getUrl')->willReturnMap([['pinterestadmin/Setup/Settings', 'settingsValue'], 
            ['pinterestadmin/Setup/DisconnectMultisite', 'disconnectURLValue'], ['pinterestadmin/Setup/Index', 'setupValue']]);
        $this->pluginErrorHelperMock->method('getAllStoredErrors')->willReturn(['error1', 'error2']);
        $this->pinterestHelperMock->method('getClientId')->willReturn('clientIdValue');
        $this->pinterestHelperMock->method('getExternalBusinessId')->willReturn('businessIdValue');
        $this->pinterestHelperMock->method('getUserLocale')->willReturn('localeValue');
        $expected = [
        [
            "pinterestBaseUrl" => 'pinterestBaseUrlValue',
            "iframeVersion" => PinterestHelper::IFRAME_VERSION,
            "accessToken" => 'accessTokenValue',
            "advertiserId" => 'advertiserIdValue',
            "merchantId" => 'merchantIdValue',
            "tagId" => 'tagIdValue',
            "disconnectURL" => 'disconnectURLValue',
            "errors" => ['error1', 'error2'],
            "partnerMetadata" => $metadata,
            "clientId" => 'clientIdValue',
            "businessId" => 'businessIdValue',
            "locale" => 'localeValue',
            "settingsURL" => 'settingsValue',
            "setupURL" => 'setupValue',
            "siteId" => '1',
            "siteName" => 'Store Name',
            "siteURL" => 'https://store.com'
        ],
        // Repeat for other store IDs if needed
        ];
        $this->assertEquals($expected, $this->setup->getConnectedStoreIds());
    }
}
