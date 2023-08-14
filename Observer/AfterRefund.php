<?php

namespace Ecommpay\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Ecommpay\Payments\Model\MethodCard as EcommpayPaymentMethod;

class AfterRefund implements ObserverInterface
{

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        die('Refund after action!');
        $eventData = $observer->getData();
        /** @var Payment $payment */
        $payment = $eventData['payment'];
        /** @var Creditmemo $creditMemo */
        $creditMemo = $eventData['creditMemo'];

        $paymentMethod = $payment->getMethodInstance();
        if (!$paymentMethod instanceof EcommpayPaymentMethod) {
            return;
        }

        if ($payment->getOrder()->getTotalRefunded() < $payment->getOrder()->getGrandTotal()) {
            $order = $payment->getOrder();
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
            $order->save();
            return;
        }
    }
}
