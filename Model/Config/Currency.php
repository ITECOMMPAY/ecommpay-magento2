<?php

namespace Ecommpay\Payments\Model\Config;

use Magento\Framework\Option\ArrayInterface;

class Currency implements ArrayInterface
{
    /**
     *
     * @return array */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('default (by store settings)')],
            ['value' => 'USD', 'label' => __('USD')],
            ['value' => 'EUR', 'label' => __('EUR')],
            ['value' => 'RUB', 'label' => __('RUB')],
        ];
    }
}
