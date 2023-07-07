<?php
namespace Pinterest\PinterestMagento2Extension\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class RegenerateCatalogFeeds extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Pinterest_PinterestMagento2Extension::regenerate_feed.phtml';

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return Ajax Url
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('pinterestadmin/Ajax/RegenerateFeeds');
    }

    /**
     * Return Element Html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(Button::class);
        return $button->setData(['id' => 'pin_catalog_sync_btn', 'label' => __('Regenerate Catalog')])
            ->toHtml();
    }
}
