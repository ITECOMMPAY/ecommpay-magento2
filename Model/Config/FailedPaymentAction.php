<?php

namespace Ecommpay\Payments\Model\Config;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Magento\Framework\Option\ArrayInterface;

class FailedPaymentAction implements ArrayInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => EcpConfigHelper::FAILED_PAYMENT_ACTION_CANCEL_ORDER, 'label' => __('Cancel the order')],
            ['value' => EcpConfigHelper::FAILED_PAYMENT_ACTION_DELETE_ORDER, 'label' => __('Delete the order')],
            ['value' => EcpConfigHelper::FAILED_PAYMENT_ACTION_DO_NOTHING, 'label' => __('Do nothing')],
        ];
    }
}
