<?php

namespace Ecommpay\Payments\Model;


class MethodMultibanco extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_MULTIBANCO = 'ecommpay_multibanco';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_MULTIBANCO;

    /**
     * @var string
     */
    protected $_infoBlockType = 'Ecommpay\Payments\Block\Info\BaseInfoBlock';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    protected $_canCapture = true;

    protected $_canRefund = false;

    protected $_canRefundInvoicePartial = false;

    protected $_canAuthorize = false;
}
