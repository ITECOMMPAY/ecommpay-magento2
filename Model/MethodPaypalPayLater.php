<?php

namespace Ecommpay\Payments\Model;

use Ecommpay\Payments\Block\Info\BaseInfoBlock;

class MethodPaypalPayLater extends EcpAbstractMethod
{
    private const PAYMENT_METHOD_NAME_PAYPAL_PAY_LATER = 'ecommpay_paypal_paylater';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_PAYPAL_PAY_LATER;

    /**
     * @var string
     */
    protected $_infoBlockType = BaseInfoBlock::class;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;

    protected $_canCapture = false;

    protected $_canRefund = true;

    protected $_canRefundInvoicePartial = true;

    protected $_canAuthorize = false;

    protected $refundEndpoint = 'wallet/paypal';
}
