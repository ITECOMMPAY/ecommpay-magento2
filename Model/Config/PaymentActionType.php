<?php

namespace Ecommpay\Payments\Model\Config;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Magento\Framework\Option\ArrayInterface;

class PaymentActionType implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => EcpConfigHelper::AUTHORIZE_TYPE, 'label' => __('Authorize')],
            ['value' => EcpConfigHelper::AUTHORIZE_AND_CAPTURE_TYPE, 'label' => __('Authorize and Capture')],
        ];
    }
}