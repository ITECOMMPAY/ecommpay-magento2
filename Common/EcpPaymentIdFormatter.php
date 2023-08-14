<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\RequestInterface;

class EcpPaymentIdFormatter
{
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     *
     * @param string $paymentId
     * @param string $prefix
     * @return string
     */
    public function addPaymentPrefix($paymentId, $prefix)
    {
        return $prefix . $this->request->getServer('SERVER_NAME') . '_' . $paymentId;
    }
}
