<?php

namespace Ecommpay\Payments\Model\Config;

class Currency implements \Magento\Framework\Option\ArrayInterface
{
    /**
     *
     * @return array */
    public function toOptionArray()
    {
        return [
            ['value' => 'default', 'label' => __('default (by store settings)')],
            ['value' => 'USD', 'label' => __('USD')],
            ['value' => 'EUR', 'label' => __('EUR')],
            ['value' => 'RUB', 'label' => __('RUB')],
        ];
    }
}
