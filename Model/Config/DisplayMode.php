<?php

namespace Ecommpay\Payments\Model\Config;

use Magento\Framework\Option\ArrayInterface;

class DisplayMode implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'redirect', 'label' => __('Redirect')],
            ['value' => 'popup', 'label' => __('Popup')],
            ['value' => 'embedded', 'label' => __('Embedded')],
        ];
    }
}
