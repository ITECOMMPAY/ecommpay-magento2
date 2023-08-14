<?php

namespace Ecommpay\Payments\Model\Config;


class PluginVersion extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return \Ecommpay\Payments\Common\EcpConfigHelper::PLUGIN_VERSION;
    }
}