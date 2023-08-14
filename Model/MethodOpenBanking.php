<?php

namespace Ecommpay\Payments\Model;


class MethodOpenBanking extends EcpAbstractMethod
{
    const PAYMENT_METHOD_NAME_OPEN_BANKING = 'ecommpay_open_banking';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_NAME_OPEN_BANKING;

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