<?php

namespace Pinterest\PinterestBusinessConnectPlugin\Cron;

use Pinterest\PinterestBusinessConnectPlugin\Helper\ProductExporter;
use Pinterest\PinterestBusinessConnectPlugin\Helper\CatalogFeedClient;
use Pinterest\PinterestBusinessConnectPlugin\Helper\PinterestHelper;

use Psr\Log\LoggerInterface;

/**
 *
 *
To manually run this task:
  bin/magento cron:run --group pinterest

The task schedule is to be set in etc/crontab.xml. Format of the cron tab:
 * * * * * * command to be executed
| | | | |
| | | | +----- Day of week (0 - 7) (Sunday=0 or 7)
| | | +------- Month (1 - 12)
| | +--------- Day of month (1 - 31)
| +----------- Hour (0 - 23)
+------------- Minute (0 - 59)

A field may be an asterisk (*), which always stands for ``first-last''.

Ranges of numbers are allowed.  Ranges are two numbers separated with a hyphen.  The specified range is inclusive.  For example,
8-11 for an ``hours'' entry specifies execution at hours 8, 9, 10 and 11.

Lists are allowed.  A list is a set of numbers (or ranges) separated by commas.  Examples: ``1,2,5,9'', ``0-4,8-12''.

Step values can be used in conjunction with ranges.  Following a range with ``/<number>'' specifies skips of the number's value
through the range.  For example, ``0-23/2'' can be used in the hours field to specify command execution every other hour (the
alternative in the V7 standard is ``0,2,4,6,8,10,12,14,16,18,20,22'').  Steps are also permitted after an asterisk, so if you want
to say ``every two hours'', just use ``* /2''.

 *
 *
 *
 */

class Catalog
{

    /**
     * @var ProductExporter
     */
    private $_productExporter;

    /**
     * @var CatalogFeedClient
     */
    private $_catalogFeedClient;

    /**
     * @param PinterestHelper $pinterestHelper
     */
    private $_pinterestHelper;

    /**
     * Constructor
     *
     * @param PinterestHelper $pinterestHelper
     * @param CatalogFeedClient $catalogFeedClient
     * @param ProductExporter $productExporter
     */
    public function __construct(
        PinterestHelper $pinterestHelper,
        CatalogFeedClient $catalogFeedClient,
        ProductExporter $productExporter
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        $this->_catalogFeedClient = $catalogFeedClient;
        $this->_productExporter = $productExporter;
    }

    /**
     * Execute the exporting task
     *
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        try {
            if ($this->_pinterestHelper->isUserConnected()) {
                $success_count = $this->_productExporter->processExport();
                $this->_pinterestHelper->logInfo("Pinterest catalog generated {$success_count} feed(s).");
                $this->_catalogFeedClient->createAllFeeds(false);
            }
        } catch (Exception $e) {
            $this->_pinterestHelper->logException($e);
        }
    }
}
