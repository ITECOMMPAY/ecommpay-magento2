<?php

namespace Ecommpay\Payments\Common;

class EcpPaymentIdFormatter
{
    /**
     * @param string $orderId
     * @param string $prefix
     * @return string
     */
    public static function addPaymentPrefix($paymentId, $prefix)
    {
        return $prefix . $_SERVER['SERVER_NAME'] . '_' . $paymentId;
    }
}