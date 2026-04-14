<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\TokensHelper;
use Pinterest\PinterestMagento2Extension\Model\Metadata;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Pinterest\PinterestMagento2Extension\Constants\MetadataName;
use Magento\Framework\Model\AbstractModel;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHttpClient;

class TokensHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;
    /**
     * @var PinterestHttpClient $pinterestHttpClient
     */
    protected $_pinterestHttpClient;
    /**
     * @var TokensHelper $tokensHelper
     */
    protected $_tokensHelper;
    /**
     * @var MetadataFactory
     */
    protected $_metadataFactory;

    public function setUp() : void
    {
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_pinterestHttpClient = $this->createMock(PinterestHttpClient::class);
        $this->_metadataFactory = $this->createMock(MetadataFactory::class);
        $this->_tokensHelper = new TokensHelper(
            $this->_pinterestHelper,
            $this->_pinterestHttpClient
        );
    }

    private function saveRowMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['setData', 'save'])
                ->getMock();
    }

    public function testRefreshTokensCaseAPISuccess()
    {
        $mock = $this->saveRowMock();
        $this->_metadataFactory->method("create")->willReturn($mock);
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "access_token" => "12345abcdef",
            "token_type" => "Bearer",
            "expires_in" => "50",
            "scope" => "ads:read ads:write",
            "refresh_token" => "12345abcdef",
            "refresh_token_expires_in" => "100"
        ])));
        $success = $this->_tokensHelper->refreshTokens();
        $this->assertTrue($success);
    }
    public function testRefreshTokensCaseAPIFail()
    {
        $mock = $this->saveRowMock();
        $this->_metadataFactory->method("create")->willReturn($mock);
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "message" => "something other than an access token"
        ])));
        $success = $this->_tokensHelper->refreshTokens();
        $this->assertFalse($success);
    }

    public function testStoreRefreshTokenCaseSuccess()
    {
        $mockStoreId = '1';
        $prefix = MetadataName::PINTEREST_TOKEN_PREFIX . $mockStoreId . '/';
        $invocationCount = 0;                                                                                                                                                     
        $this->_pinterestHelper->expects($this->exactly(4))                                                                                                                                     
            ->method('saveMetadata')                                                                                                                                                            
            ->willReturnCallback(function ($key, $value) use (&$invocationCount, &$prefix) {                                                                                                              
                $invocationCount++;                                                                                                                                                             
                match ($invocationCount) {                                                                                                                                                      
                    1 => $this->assertEquals([$prefix . 'token_type', 'Bearer'], [$key, $value]),                                                                                         
                    2 => $this->assertEquals([$prefix . 'expires_in', '50'], [$key, $value]),                                                                                             
                    3 => $this->assertEquals([$prefix . 'scope', 'ads:read ads:write'], [$key, $value]),                                                                                  
                    4 => $this->assertEquals([$prefix . 'refresh_token_expires_in', '100'], [$key, $value]),                                                                              
                };                                                                                                                                                                              
            }); 

        $mock = $this->saveRowMock();
        $this->_metadataFactory->method("create")->willReturn($mock);
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "access_token" => "12345abcdef",
            "token_type" => "Bearer",
            "expires_in" => "50",
            "scope" => "ads:read ads:write",
            "refresh_token" => "12345abcdef",
            "refresh_token_expires_in" => "100"
        ])));
        $success = $this->_tokensHelper->refreshStoreToken($mockStoreId);
        $this->assertTrue($success);
    }

    public function testStoreRefreshTokenCaseFailure()
    {
        $mockStoreId = '1';
        $mock = $this->saveRowMock();
        $this->_metadataFactory->method("create")->willReturn($mock);
        $this->_pinterestHttpClient->method("post")->willReturn(json_decode(json_encode([
            "message" => "something other than an access token"
        ])));
        $success = $this->_tokensHelper->refreshStoreToken($mockStoreId);
        $this->assertFalse($success);
    }

}
