<?php

namespace Ecommpay\Payments\Model\Config;

class Language implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'default', 'label' => __('Auto')],
            ['value' => 'en', 'label' => __('English')],
            ['value' => 'fr', 'label' => __('France')],
            ['value' => 'it', 'label' => __('Italian')],
            ['value' => 'de', 'label' => __('Germany')],
            ['value' => 'de', 'label' => __('Germany')],
            ['value' => 'es', 'label' => __('Spanish')],
            ['value' => 'zh', 'label' => __('Chinese')],
            ['value' => 'ru', 'label' => __('Russian')],
        ];
    }
}