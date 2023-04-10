<?php
declare(strict_types=1);

namespace Pinterest\PinterestBusinessConnectPlugin\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Io\File;
use Magento\Store\Model\StoreManagerInterface;

class SavedFile extends AbstractHelper
{
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

    public const DIRECTORY_NAME_PATH = "pinterest/catalogs/";
    
    /**
     * Default constructor
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param File $file
     * @param DirectoryList $dList
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        File $file,
        DirectoryList $dList
    ) {
        $this->_storeManager = $storeManager;
        $this->_dList = $dList;
        $this->_file = $file;
        parent::__construct($context);
    }

    /**
     *
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Get the export URL
     * @param string $baseUrl
     * @param string $locale
     * @return string
     */
    public function getExportUrl($baseUrl, $locale)
    {
        $fileName = $this->getXmlFileName();
        $directoryPath = $this->getDirectoryPath($baseUrl, $locale);
        return $baseUrl . $directoryPath . $fileName;
    }

    /**
     * @param string $baseUrl
     * @param string $locale
     * @param bool $checkAndCreateFolder
     */
    public function getFileSystemPath($baseUrl, $locale, $checkAndCreateFolder)
    {
        $fileName = $this->getXmlFileName();
        $directoryPath = $this->getDirectoryPath($baseUrl, $locale);
        $filesystem_prefix = $this->getFileSystemPrefix(DirectoryList::MEDIA);
        $absolute_path = $filesystem_prefix.$directoryPath.$fileName;

        if ($checkAndCreateFolder) {
            $this->_file->checkAndCreateFolder($filesystem_prefix.$directoryPath, 0755);
        }
        return $absolute_path;
    }

    /**
     * @param string $baseUrl
     * @param string $locale
     * @return string
     */
    public function getDirectoryPath($baseUrl, $locale)
    {
        $ext = $locale."_".substr(hash('sha256', $baseUrl), 0, 6);
        $ret = self::DIRECTORY_NAME_PATH.$ext;
        if (!str_ends_with($ret, "/")) {
            $ret .= "/";
        }
        return $ret;
    }

    /**
     * @param string $loc
     * @return string
     */
    public function getFileSystemPrefix($loc)
    {
        $ret = $this->_dList->getPath($loc);
        if (!str_ends_with($ret, "/")) {
            $ret .= "/";
        }
        return $ret;
    }

    /**
     * Get the xml file name
     * @return string
     */
    public function getXmlFileName()
    {
        return "catalog.xml";
    }

    /**
     * Delete all catalog files and subfolders
     *
     */
    public function deleteCatalogs()
    {
        $this->_file->rmdirRecursive(SavedFile::DIRECTORY_NAME_PATH);
    }
}
