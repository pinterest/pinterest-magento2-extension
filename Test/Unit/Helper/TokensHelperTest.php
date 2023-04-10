<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Test\Unit\Helper;

use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;
use Pinterest\PinterestBusinessConnectPlugin\Helper\TokensHelper;
use Pinterest\PinterestBusinessConnectPlugin\Model\Metadata;
use Pinterest\PinterestBusinessConnectPlugin\Model\MetadataFactory;
use Magento\Framework\Model\AbstractModel;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHttpClient;

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

    private function createRowWithValue($keyValueArray)
    {
        $metadataRow = $this->getMockBuilder(Metadata::class)
                  ->disableOriginalConstructor()
                  ->setMethods(['load'])
                  ->getMock();
        if ($keyValueArray == null) {
            $metadataRow->method('load')->willReturn(null);
        } else {
            $metadataRow->method('load')->willReturnCallback(function ($metadataKey) use ($keyValueArray) {
                $value = $keyValueArray[$metadataKey];
                $abstractMock = $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['getData'])
                ->getMock();
                $abstractMock->method('getData')->willReturn($value);
                return $abstractMock;
            });
        }
        return $metadataRow;
    }

    private function saveRowMock()
    {
        return $this->getMockBuilder(AbstractModel::class)
                ->disableOriginalConstructor()
                ->setMethods(['setData', 'save'])
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
}
