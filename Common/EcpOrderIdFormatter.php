<?php

namespace Ecommpay\Payments\Common;

class EcpOrderIdFormatter
{
    /**
     * @param string $orderId
     * @param string $prefix
     * @return string
     */
    public static function addOrderPrefix($orderId, $prefix)
    {
        return $prefix . '&' . $_SERVER['SERVER_NAME'] . '&' . $orderId;
    }

    /**
     * @param string $orderId
     * @param string $prefix
     * @return mixed
     */
    public static function removeOrderPrefix($orderId, $prefix)
    {
        return preg_replace('/^' . $prefix . '&' . preg_quote($_SERVER['SERVER_NAME']) . '&/', '', $orderId);
    }
}