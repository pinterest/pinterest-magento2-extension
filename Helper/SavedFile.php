<?php
declare(strict_types=1);

namespace Pinterest\PinterestMagento2Extension\Helper;

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
     * Returns if is enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Get the export URL
     *
     * @param string $baseUrl
     * @param string $locale
     * @return string
     */
    public function getExportUrl($baseUrl, $locale, $storeId = null)
    {
        $fileName = $this->getXmlFileName();
        $directoryPath = $this->getDirectoryPath($baseUrl, $locale, $storeId);
        return $baseUrl . $directoryPath . $fileName;
    }

    /**
     * Gets absolute file system path.
     *
     * @param string $baseUrl
     * @param string $locale
     * @param bool $checkAndCreateFolder
     */
    public function getFileSystemPath($baseUrl, $locale, $checkAndCreateFolder, $storeId = null)
    {
        $fileName = $this->getXmlFileName();
        $directoryPath = $this->getDirectoryPath($baseUrl, $locale, $storeId);
        $filesystem_prefix = $this->getFileSystemPrefix(DirectoryList::MEDIA);
        $absolute_path = $filesystem_prefix.$directoryPath.$fileName;

        if ($checkAndCreateFolder) {
            $this->_file->checkAndCreateFolder($filesystem_prefix.$directoryPath, 0755);
        }
        return $absolute_path;
    }

    /**
     * Gets directory path.
     *
     * @param string $baseUrl
     * @param string $locale
     * @return string
     */
    public function getDirectoryPath($baseUrl, $locale, $storeId = null)
    {
        $ext = ($storeId != null ? "{$storeId}_" : "").$locale."_".substr(hash('sha256', $baseUrl), 0, 6);
        $ret = self::DIRECTORY_NAME_PATH.$ext;
        if (!str_ends_with($ret, "/")) {
            $ret .= "/";
        }
        return $ret;
    }

    /**
     * Gets file system prefix.
     *
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
     *
     * @return string
     */
    public function getXmlFileName()
    {
        return "catalog.xml";
    }

    /**
     * Delete all catalog files and subfolders for a store. 
     * If the storeId is not provided, it deletes all the catalogs directory
     */
    public function deleteCatalogs($storeId = null)
    {
        if($storeId == null){
            $filesystem_prefix = $this->getFileSystemPrefix(DirectoryList::MEDIA);
            $this->_file->rmdirRecursive($filesystem_prefix.SavedFile::DIRECTORY_NAME_PATH);
        } else {
            $directoryPath = $this->getFileSystemPrefix(DirectoryList::MEDIA).SavedFile::DIRECTORY_NAME_PATH;
            $this->_file->cd($directoryPath);
            foreach($this->_file->ls(File::GREP_DIRS) as $catalogDir) {
                $dirName = $catalogDir['text'];
                if(preg_match("/^".$storeId."_.+/", $dirName)){
                    $this->_file->rmdir($dirName, true);
                }
            }
        }
    }
}
