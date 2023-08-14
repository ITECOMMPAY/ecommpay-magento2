<?php

namespace Ecommpay\Payments\Model\Config;

class DisplayMode implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'redirect', 'label' => __('Redirect')],
            ['value' => 'popup', 'label' => __('Popup')],
            ['value' => 'embedded', 'label' => __('Embedded')],
        ];
    }
}