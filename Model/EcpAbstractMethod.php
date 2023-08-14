<?php

namespace Ecommpay\Payments\Model;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpRefundProcessor;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;


abstract class EcpAbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $refundEndpoint = null;

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        $refundProcessor = new EcpRefundProcessor();
        /** @var Payment $payment */
        /** @var Creditmemo $creditMemo */
        $creditMemo = $payment->getCreditmemo();

        try {
            $currencyCode = $payment->getOrder()->getOrderCurrency()->getCode();
            $ecpRefundResult = $refundProcessor->processRefund(
                $payment->getOrder(),
                $amount,
                $currencyCode,
                $this->refundEndpoint
            );
        } catch (\Exception $e) {
            $creditMemo->addComment('Request was not correctly processed by gateway.');
            $creditMemo->setState(Creditmemo::STATE_CANCELED);
            return $this;
        }

        if ($ecpRefundResult->getRefundExternalId() === null) {
            $creditMemo->addComment('Request was declined by gateway.');
            $creditMemo->setState(Creditmemo::STATE_CANCELED);
            return $this;
        }

        $creditMemo->setState(Creditmemo::STATE_OPEN);

        $creditMemo->addComment(sprintf(EcpRefundProcessor::REFUND_ID_CONTAINING_COMMENT, $ecpRefundResult->getRefundExternalId()));

        return $this;
    }
}