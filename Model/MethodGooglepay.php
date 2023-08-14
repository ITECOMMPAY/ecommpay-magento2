<?php

namespace Ecommpay\Payments\Model;


class MethodGooglepay extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_GOOGLEPAY = 'ecommpay_googlepay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_GOOGLEPAY;

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

    protected $_canRefund = true;

    protected $_canRefundInvoicePartial = true;

    protected $_canAuthorize = true;

    protected $refundEndpoint = 'googlepay';
}