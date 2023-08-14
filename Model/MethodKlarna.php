<?php

namespace Ecommpay\Payments\Model;


class MethodKlarna extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_KLARNA = 'ecommpay_klarna';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_KLARNA;

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

    protected $refundEndpoint = 'bank-transfer/klarna';
}