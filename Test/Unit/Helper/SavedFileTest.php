<?php

namespace Pinterest\PinterestMagento2Extension\Test\Unit\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Io\File;
use Magento\Store\Model\StoreManagerInterface;
use Pinterest\PinterestMagento2Extension\Helper\SavedFile;

class SavedFileTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Context
     */
    protected $_context;

    /**
     * @var DirectoryList
     */
    protected $_dList;

    /**
     * @var File
     */
    protected $_file;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var SavedFile
     */
    protected $_savedFile;

    public function setUp() : void
    {
        $this->_context = $this->createMock(Context::class);
        $this->_storeManager = $this->createMock(StoreManagerInterface::class);
        $this->_dList = $this->createMock(DirectoryList::class);
        $this->_file = $this->createMock(File::class);
        $this->_savedFile = new SavedFile(
            $this->_context,
            $this->_storeManager,
            $this->_file,
            $this->_dList
        );
    }

    public function testDeleteCatalogs()
    {
        $this->_dList->method('getPath')->willReturn('/');
        $this->_file->expects($this->once())->method('cd')->with('/pinterest/catalogs/');
        $this->_file->method('ls')->willReturn([
            ['text' => '1_en_US_abcd'],
            ['text' => '2_en_US_asdf'],
            ['text' => '11_es_MX_aaaa']
        ]);
        $this->_file->expects($this->once())->method('rmdir')->with('1_en_US_abcd', true);
        $this->_savedFile->deleteCatalogs("1");
    }
}