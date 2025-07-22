<?php

namespace Ecommpay\Payments\Model;

use Ecommpay\Payments\Block\Info\BaseInfoBlock as EcommpayInfoBlock;
use Ecommpay\Payments\Common\Gateway\EcpGatewayProcessor;
use Ecommpay\Payments\Common\EcpConfigHelper;
use Exception;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Payment\Block\Form as MagentoForm;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;


class EcpAbstractMethod extends Adapter
{
    private const PAYMENT_STATUS_AWAITING_CAPTURE = 'awaiting capture';
    private const ADDITIONAL_INFO_PAYMENT_STATUS = 'payment_status';

    protected ?string $methodEndpoint;
    protected EcpConfigHelper $config;
    protected EcpGatewayProcessor $gatewayProcessor;
    protected TransactionRepositoryInterface $transactionRepository;

    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        string $code,
        EcpConfigHelper $config,
        EcpGatewayProcessor $gatewayProcessor,
        TransactionRepositoryInterface $transactionRepository,
        ?CommandPoolInterface $commandPool = null,
        ?ValidatorPoolInterface $validatorPool = null
    )
    {
        $this->config = $config;
        $this->gatewayProcessor = $gatewayProcessor;
        $this->transactionRepository = $transactionRepository;
        $this->methodEndpoint = $this->config->getMethodEndPoint($code);

        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            MagentoForm::class,
            EcommpayInfoBlock::class,
            $commandPool,
            $validatorPool
        );
    }

    public function getEndpoint(): ?string
    {
        return $this->methodEndpoint;
    }

    public function cancel(InfoInterface $payment): EcpAbstractMethod
    {
        return $this->processCancelFlow($payment);
    }

    public function void(InfoInterface $payment): EcpAbstractMethod
    {
        return $this->processCancelFlow($payment);
    }

    private function processCancelFlow(InfoInterface $payment): EcpAbstractMethod
    {
        $paymentStatus = $payment->getAdditionalInformation(self::ADDITIONAL_INFO_PAYMENT_STATUS);

        if ($paymentStatus === self::PAYMENT_STATUS_AWAITING_CAPTURE) {
            throw new LocalizedException(__('This payment cannot be canceled.'));
        }

        $order = $payment->getOrder();

        try {
            $gatewayResponse = $this->gatewayProcessor->cancel($order->getEntityId(), $this->getEndpoint());
        } catch (Exception $e) {
            return $this->declineCancelOperation($order, $e->getMessage());
        }
        
        if (!$gatewayResponse->isSuccess()) {
            return $this->declineCancelOperation($order, $gatewayResponse->getMessage());
        }

        $this->createTransaction($order, Transaction::TYPE_VOID, $gatewayResponse->getRequestId());
        return $this;
    }

    private function declineCancelOperation(Order $order, string $message): EcpAbstractMethod
    {
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus(Order::STATE_CANCELED);
        $order->addStatusToHistory(
            $order->getStatus(), 
            __('Request was not correctly processed by gateway. ' . $message)
        );
        return $this;
    }

    public function canCapture(): bool
    {
        if (!parent::canCapture()) {
            return false;
        }
        return $this->getInfoInstance()->getOrder()->getStatus() === 'pending';     
    }

    public function capture(InfoInterface $payment, $amount): EcpAbstractMethod
    {
        if (!$this->canCapture()) {
            throw new LocalizedException(__('This payment cannot be captured.'));
        }

        $order = $payment->getOrder();
        $currencyCode = $order->getOrderCurrency()->getCode();

        try {
            $gatewayResponse = $this->gatewayProcessor->capture($order->getEntityId(), $amount, $currencyCode, $this->getEndpoint());
        } catch (Exception $e) {
            $order->addStatusToHistory(
                $order->getStatus(), 
                __('Request was not correctly processed by gateway. ' . $e->getMessage())
            );
            return $this;
        }

        if (!$gatewayResponse->isSuccess()) {
            $order->addStatusToHistory(
                $order->getStatus(), 
                __('Request was declined by gateway. ' . $gatewayResponse->getMessage())
            );
            return $this;
        }

        $this->createTransaction($order, Transaction::TYPE_CAPTURE, $gatewayResponse->getRequestId());
        return $this;
    }

    public function refund(InfoInterface $payment, $amount): EcpAbstractMethod
    {
        if (!$this->canRefund()) {
            throw new LocalizedException(__('The refund action is not available.'));
        }

        $creditMemo = $payment->getCreditmemo();
        $order = $payment->getOrder();
        $currencyCode = $order->getOrderCurrency()->getCode();

        try {
            $gatewayResponse = $this->gatewayProcessor->refund($order->getEntityId(), $amount, $currencyCode, $this->getEndpoint());
        } catch (Exception $e) {
            $creditMemo->addComment('Request was not correctly processed by gateway. ' . $e->getMessage());
            $creditMemo->setState(Creditmemo::STATE_CANCELED);
            return $this;
        }

        if (!$gatewayResponse->isSuccess()) {
            $creditMemo->addComment('Request was declined by gateway.');
            $creditMemo->setState(Creditmemo::STATE_CANCELED);
            return $this;
        }

        $creditMemo->setState(Creditmemo::STATE_OPEN);
        $this->createTransaction($order, Transaction::TYPE_REFUND, $gatewayResponse->getRequestId());
        return $this;
    }

    protected function createTransaction(Order $order, string $transactionType, string $requestId): void
    {
        $payment = $order->getPayment();
        $payment->setIsTransactionPending(true);
        $payment->setIsTransactionClosed(false);
        $payment->setTransactionId($requestId);
        $payment->setLastTransId($requestId);
        $payment->setShouldCloseParentTransaction(1);

        $parentTransactionId = null;

        if ($transactionType === Transaction::TYPE_REFUND){
            $parentTransactionId = $this->getCaptureTransactionId($order);
        } else {
            $parentTransactionId = $payment->getAuthorizationTransaction()->getTxnId();
        }

        $payment->setParentTransactionId($parentTransactionId);
        $payment->addTransaction($transactionType);
        $payment->save();
    }

    protected function getCaptureTransactionId(Order $order): string
    {
        $captureTransaction = $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_CAPTURE,
            $order->getPayment()->getId(),
            $order->getId()
        );
        return $captureTransaction->getTxnId();
    }
}
