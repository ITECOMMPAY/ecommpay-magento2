<?php

namespace Ecommpay\Payments\Model;


class MethodMoreMethods extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_MORE_METHODS = 'ecommpay_more_methods';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_MORE_METHODS;

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

    protected $_canAuthorize = true;

    protected $refundEndpoint = null;
}