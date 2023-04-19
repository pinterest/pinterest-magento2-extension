<?php
namespace Pinterest\PinterestMagento2Extension\Logger;

use Magento\Framework\ObjectManagerInterface;

class Logger extends \Monolog\Logger
{
    /**
     * Logger constructor
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $handler = $objectManager->create(Handler::class);
        parent::__construct('Pinterest_PinterestMagento2Extension', [$handler]);
    }
}
