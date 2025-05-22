<?php
namespace Pinterest\PinterestMagento2Extension\Controller\Adminhtml\Setup;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Magento\Framework\App\Request\DataPersistorInterface;

class Settings extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    protected $dataPersistor;

    protected $request;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        DataPersistorInterface $dataPersistor,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->dataPersistor = $dataPersistor;
        $this->request = $request;
    }

    public function execute()
    {
        $store = $this->_request->getParam('storeId');
        $this->dataPersistor->set('storeId', $store);
        return $this->resultPageFactory->create();
    }
}