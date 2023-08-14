<?php

namespace Ecommpay\Payments\Model\Config;

class NewOrderStatus implements \Magento\Framework\Option\ArrayInterface
{

    public function toOptionArray()
    {
        return [
            ['value' => 'Pending', 'label' => __('Pending')],
        ];
    }
}