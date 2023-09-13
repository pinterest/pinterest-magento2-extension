<?php
namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml\System\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;

class LdpField extends Field
{
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @param Context $context
     * @param PinterestHelper $pinterestHelper
     */
    public function __construct(
        Context $context,
        PinterestHelper $pinterestHelper
    ) {
        $this->_pinterestHelper = $pinterestHelper;
        parent::__construct($context);
    }

    /**
     * Return Element Html
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if (!$this->_pinterestHelper->isConversionConfigEnabled()) {
            $element->setDisabled('disabled');
        }
        return $element->getElementHtml();
    }
}
