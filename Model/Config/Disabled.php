<?php

namespace Ecommpay\Payments\Model\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Disabled extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setData('readonly', 1);
        return $element->getElementHtml();
    }
}
