<?php

namespace Ecommpay\Payments\Block\Adminhtml\Order;

use Ecommpay\Payments\Common\CallbackInfoManager;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Registry;

class OrderView extends \Magento\Backend\Block\Template
{
    private $operationType = '';
    private $paymentId = '';
    private $paymentMethod = '';
    private $paymentStatus = '';
    private $orderId = '';
    private $registry;

    protected $_template = 'Ecommpay_Payments::order/order_view.phtml';

    public function __construct(
        Registry $registry,
        Context $context,
        CallbackInfoManager $callbackInfoManager,
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null,
        array $data = []
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
        $this->registry = $registry;
        $this->callbackInfoManager = $callbackInfoManager;
    }

    public function loadOrderData()
    {
        $orderId = $this->getOrderId();
        $callbackData = $this->callbackInfoManager->getCallBackInfoByOrderId($orderId);
        $this->orderId = $orderId;
        $this->operationType = $callbackData['operation_type'] ?? '';
        $this->paymentId = $callbackData['payment_id'] ?? '';
        $this->paymentMethod = $callbackData['payment_method'] ?? '';
        $this->paymentStatus = $callbackData['payment_status'] ?? '';
    }

    /**
     *
     * @return string */
    public function getOperationType(): string
    {
        return $this->operationType;
    }

    /**
     *
     * @return string */
    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    /**
     *
     * @return string */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     *
     * @return string */
    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    private function getOrderId()
    {
        $salesOrder = $this->registry->registry('sales_order');
        if ($salesOrder instanceof \Magento\Sales\Model\Order) {
            return $salesOrder->getEntityId();
        }
        $currentOrder = $this->registry->registry('current_order');
        if ($currentOrder instanceof \Magento\Sales\Model\Order) {
            return $currentOrder->getEntityId();
        }
        $order = $this->registry->registry('order');
        if ($order instanceof \Magento\Sales\Model\Order) {
            return $order->getEntityId();
        }
        return null;
    }
}
