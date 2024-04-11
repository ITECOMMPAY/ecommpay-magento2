<?php

namespace Ecommpay\Payments\Model;


class MethodBancontact extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_BANCONTACT = 'ecommpay_bancontact';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_BANCONTACT;

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

    protected $_canAuthorize = false;

    protected $refundEndpoint = 'bancontact';
}
