<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Pinterest\PinterestMagento2Extension\Helper\DbHelper;
use Pinterest\PinterestMagento2Extension\Logger\Logger;
use Pinterest\PinterestMagento2Extension\Model\MetadataFactory;
use Magento\Framework\Encryption\EncryptorInterface;

class DbHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DbHelper
     */
    protected $_dbHelper;
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

    public function setUp() : void
    {
        $this->_logger = $this->createMock(Logger::class);
        $this->_metadataFactory = $this->createMock(MetadataFactory::class);
        $this->_encryptor = $this->createMock(EncryptorInterface::class);
        $this->_dbHelper = new DbHelper(
            $this->_logger,
            $this->_metadataFactory,
            $this->_encryptor
        );
    }
    private function createRowWithValue($keyValueArray)
    {
        $metadataRow = $this->getMockBuilder(Metadata::class)
                  ->disableOriginalConstructor()
                  ->setMethods(['load', 'save', 'setData'])
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

    public function testSaveAndGetValue()
    {
        $mockKey = "test_key";
        $mockValue = "test_value";
        $this->_metadataFactory->method('create')->willReturn($this->createRowWithValue([
            $mockKey => $mockValue
        ]));
        $this->_dbHelper->saveMetadata($mockKey, $mockValue);
        $value = $this->_dbHelper->getMetadataValue($mockKey);
        $this->assertTrue($value == $mockValue);
    }
    
    public function testGetAccessTokenDefaultStore()
    {
        $encryptedToken = 'encryptedToken';
        $decryptedToken = 'decryptedToken';
        $this->_encryptor->method('decrypt')->with($encryptedToken)->willReturn($decryptedToken);
        $this->_metadataFactory->method('create')->willReturn($this->createRowWithValue([
            'pinterest/token/access_token' => $encryptedToken
        ]));
        $this->assertEquals($decryptedToken, $this->_dbHelper->getAccessToken());
    }

    public function testGetAccessTokenSpecificStore()
    {
        $store = 'store1';
        $encryptedToken = 'encryptedToken';
        $decryptedToken = 'decryptedToken';
        $this->_encryptor->method('decrypt')->with($encryptedToken)->willReturn($decryptedToken);
        $this->_metadataFactory->method('create')->willReturn($this->createRowWithValue([
            'pinterest/token/store1/access_token' => $encryptedToken
        ]));
        $this->assertEquals($decryptedToken, $this->_dbHelper->getAccessToken($store));
    }
}
