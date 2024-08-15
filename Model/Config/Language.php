<?php

namespace Ecommpay\Payments\Model\Config;

use Ecommpay\Payments\Common\EcpConfigHelper;

class Language implements \Magento\Framework\Option\ArrayInterface
{
    /**
     *
     * @return array */
    public function toOptionArray(): array
    {
        return [
            ['value' => EcpConfigHelper::PP_LANGUAGE_DEFAULT, 'label' => __('Auto')],
            ['value' => 'en', 'label' => __('English')],
            ['value' => 'fr', 'label' => __('France')],
            ['value' => 'it', 'label' => __('Italian')],
            ['value' => 'de', 'label' => __('Germany')],
            ['value' => 'es', 'label' => __('Spanish')],
            ['value' => 'zh', 'label' => __('Chinese')],
            ['value' => 'ru', 'label' => __('Russian')],
        ];
    }
}
