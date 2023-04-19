<?php
namespace Pinterest\PinterestMagento2Extension\Setup;

use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Pinterest\PinterestMagento2Extension\Helper\DisconnectHelper;

class Uninstall implements UninstallInterface
{

    /**
     * @var DisconnectHelper
     */
    protected $_disconnectHelper;

    /**
     * Uninstall constructor
     *
     * @param DisconnectHelper $disconnectHelper
     */
    public function __construct(
        DisconnectHelper $disconnectHelper
    ) {
        $this->_disconnectHelper = $disconnectHelper;
    }

    /**
     * Uninstall script run when module is deleted
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->_disconnectHelper->disconnectAndCleanup();
        $setup->getConnection()->dropTable("pinterest_integration_extension_metadata");
        $setup->endSetup();
    }
}
