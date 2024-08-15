<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use DateTime;
use Ecommpay\Payments\Common\CallbackInfoManager;
use Ecommpay\Payments\Common\EcpCallbackDTO;
use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpSigner;
use Ecommpay\Payments\Common\Exception\EcpCallbackHandlerException;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Ecommpay\Payments\Common\Processors\EcpRefundProcessor;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;


class Index extends Action implements CsrfAwareActionInterface
{
    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var OrderPaymentRepositoryInterface */
    protected $paymentRepository;

    /** @var TransactionRepositoryInterface */
    protected $transactionRepository;

    /** @var InvoiceService */
    protected $invoiceService;

    /** @var CreditmemoRepositoryInterface */
    protected $creditMemoRepository;

    /** @var QuoteRepository */
    protected $quoteRepository;

    /** @var EcpSigner */
    protected $signer;

    /** @var PageFactory */
    protected $pageFactory;

    /** @var string|null */
    protected $currentOrderLink;

    /** @var EcpConfigHelper */
    protected $configHelper;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var CallbackInfoManager */
    protected $callbackInfoManager;

    /** @var OrderPaymentManager */
    protected $orderPaymentManager;

    /** @var RequestInterface */
    protected $request;

    public function __construct(
        Context                         $context,
        RequestInterface                $request,
        OrderRepositoryInterface        $orderRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        TransactionRepositoryInterface  $transactionRepository,
        CreditmemoRepositoryInterface   $creditmemoRepository,
        InvoiceService                  $invoiceService,
        PageFactory                     $pageFactory,
        JsonFactory                     $resultJsonFactory,
        QuoteRepository                 $quoteRepository,
        CallbackInfoManager             $callbackInfoManager,
        OrderPaymentManager             $orderPaymentManager,
        EcpSigner                       $ecpSigner,
        EcpConfigHelper                 $configHelper
    )
    {
        parent::__construct($context);
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $orderPaymentRepository;
        $this->transactionRepository = $transactionRepository;
        $this->creditMemoRepository = $creditmemoRepository;
        $this->invoiceService = $invoiceService;
        $this->pageFactory = $pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteRepository = $quoteRepository;
        $this->configHelper = $configHelper;
        $this->signer = $ecpSigner;
        $this->callbackInfoManager = $callbackInfoManager;
        $this->orderPaymentManager = $orderPaymentManager;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function execute(): Json
    {
        $body = $this->request->getContent();

        try {
            $callbackDto = EcpCallbackDTO::create($body);
        } catch (EcpCallbackHandlerException $e) {
            return $this->sendResultJson('Unknown operation identifier.', [$e->getMessage()], 400);
        }
        if (!$this->signer->checkSignature($callbackDto->getCallbackArray())) {
            return $this->sendResultJson('Signature invalid', [], 400);
        }

        $orderId = $this->orderPaymentManager->getOrderIdByPaymentId($callbackDto->getPaymentId());
        $callbackDto->setOrderId($orderId);

        try {
            if (!empty($callbackDto->getOrderId())) {
                $order = $this->orderRepository->get($callbackDto->getOrderId());
            } elseif (!empty($callbackDto->getPaymentId())) {
                $payment = $this->paymentRepository->get($callbackDto->getPaymentId());
                $order = $payment->getOrder();
            }
        } catch (NoSuchEntityException $e) {
            return $this->sendResultJson('Entity does not exist', [$e->getMessage()], 404);
        } catch (InputException $e) {
            return $this->sendResultJson('No input field is provided', [$e->getMessage()], 404);
        } catch (Exception $e) {
            return $this->sendResultJson('Error', [$e->getMessage()], 500);
        }

        $this->callbackInfoManager->updateCallbackInfo($order->getId(), $callbackDto);

        switch ($callbackDto->getOperationType()) {
            case EcpCallbackDTO::OPERATION_TYPE_SALE:
                return $this->processSaleCallback($callbackDto, $order);
            case EcpCallbackDTO::OPERATION_TYPE_AUTH:
                return $this->processAuthCallback($callbackDto, $order);
            case EcpCallbackDTO::OPERATION_TYPE_CAPTURE:
                return $this->processCaptureCallback($callbackDto, $order);
            case EcpCallbackDTO::OPERATION_TYPE_CANCEL:
                return $this->processCancelCallback($callbackDto, $order);
            case EcpCallbackDTO::OPERATION_TYPE_REFUND:
            case EcpCallbackDTO::OPERATION_TYPE_REVERSAL:
                return $this->processRefundCallback($callbackDto, $order);
            default:
                return $this->sendResultJson('This type of operation is not supported by Magento2.', [], 200);
        }
    }

    private function sendResultJson($data = 'Ok', array $errors = [], $responseCode = 200): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setHttpResponseCode($responseCode)->setData(['Message' => $data, 'Errors' => $errors]);
    }

    protected function processRefundCallback(EcpCallbackDTO $callbackDto, Order $order): Json
    {
        try {
            $creditmemo = $this->getCreditmemoByRequestId($order, $callbackDto->getRequestId());
        } catch (Exception $e) {
            return $this->sendResultJson('Unable to find a refund request.', [$e->getMessage()], 400);
        }

        $datetime = new DateTime();
        $now = $datetime->format('d.m.Y H:i:s');
        $creditmemoId = $creditmemo->getId();
        $refundStatuses = ['reversed', 'refunded', 'partially reversed', 'partially refunded'];
        if (in_array($callbackDto->getPaymentStatus(), $refundStatuses)) {
            $creditmemo->setState(Creditmemo::STATE_REFUNDED);
            $commentFormat = 'Money-back request #%s was successfully processed at %s';
            $creditmemo->addComment(sprintf($commentFormat, $creditmemoId, $now));
            if (in_array($callbackDto->getPaymentStatus(), ['reversed', 'refunded'])) {
                $order->setStatus(Order::STATE_CLOSED)->save();
            } else {
                $order->setStatus(Order::STATE_PROCESSING)->save();
            }
        } else {
            $creditmemo->setState(Creditmemo::STATE_CANCELED);
            $commentFormat = 'Money-back request #%s was declined at %s. Reason: %s';
            $creditmemo->addComment(sprintf($commentFormat, $creditmemoId, $now, $callbackDto->getMessage()));
        }
        try {
            $creditmemo->save();
        } catch (Exception $e) {
            return $this->sendResultJson("Couldn't save credit memo", [$e->getMessage()], 500);
        }

        $this->closeTransaction($order, Transaction::TYPE_REFUND);
        return $this->sendResultJson();
    }

    /**
     * @throws Exception
     */
    protected function processSaleCallback(EcpCallbackDTO $callbackDto, Order $order): Json
    {
         switch ($callbackDto->getOperationStatus()) {
            case EcpCallbackDTO::OPERATION_STATUS_SUCCESS:
                $this->completeSaleOrder($callbackDto, $order, true);
                break;
            case EcpCallbackDTO::OPERATION_STATUS_DECLINE:
            case EcpCallbackDTO::OPERATION_STATUS_ERROR:
                $this->completeSaleOrder($callbackDto, $order, false);
                break;
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_3DS_RESULT:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_REDIRECT_RESULT:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_CLARIFICATION:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_CUSTOMER_ACTION:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_MERCHANT_AUTH:
                $order->setStatus(Order::STATE_PENDING_PAYMENT);
                $order->addStatusToHistory($order->getStatus(), __('Awaiting customer action'));
                $order->save();
                break;
        }
        return $this->sendResultJson();
    }

    protected function processAuthCallback(EcpCallbackDTO $callbackDto, Order $order)
    {
        switch ($callbackDto->getOperationStatus()) {
            case EcpCallbackDTO::OPERATION_STATUS_SUCCESS:
                $order->setStatus('pending');
                $order->addStatusToHistory($order->getStatus(), __('Payment authorization is successful. Please make an invoice or cancel the order'));
                $this->createAuthTransaction($callbackDto, $order, false);
                break;
            case EcpCallbackDTO::OPERATION_STATUS_DECLINE:
            case EcpCallbackDTO::OPERATION_STATUS_ERROR:
                $order->setStatus(Order::STATE_CANCELED);
                $order->addStatusToHistory($order->getStatus(), __('Payment authorization declined. Reason: ' . $callbackDto->getMessage()));
                $this->createAuthTransaction($callbackDto, $order, true);
                break;
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_3DS_RESULT:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_REDIRECT_RESULT:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_CLARIFICATION:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_CUSTOMER_ACTION:
            case EcpCallbackDTO::OPERATION_STATUS_AWAITING_MERCHANT_AUTH:
                $order->setStatus(Order::STATE_PENDING_PAYMENT);
                $order->addStatusToHistory($order->getStatus(), __('Awaiting customer action'));
                break;
        }

        $order->save();
        return $this->sendResultJson();
    }

    protected function processCaptureCallback(EcpCallbackDTO $callbackDto, Order $order): Json
    {
        $invoice = $this->getInvoiceFromOrderByRequestId($order, $callbackDto->getRequestId());
        
        switch ($operation_status = $callbackDto->getOperationStatus()) {
            case EcpCallbackDTO::OPERATION_STATUS_SUCCESS:
                $order->setStatus(Order::STATE_PROCESSING);
                $order->setState(Order::STATE_PROCESSING);
                $invoice->setState(Invoice::STATE_PAID);
                $order->addStatusToHistory($order->getStatus(), __('Payment capture is successful'));
                $payment = $order->getPayment();
                $payment->pay($invoice);
                $order->setTotalPaid($order->getTotalPaid() + $payment->getAmountPaid());
                $order->setBaseTotalPaid($order->getBaseTotalPaid() + $payment->getAmountPaid());
                break;
            case EcpCallbackDTO::OPERATION_STATUS_DECLINE:
            case EcpCallbackDTO::OPERATION_STATUS_ERROR:
                $order->setStatus(Order::STATE_CANCELED);
                $invoice->setState(Invoice::STATE_CANCELED);
                $order->addStatusToHistory($order->getStatus(), __('Payment capture declined. Reason: ' . $callbackDto->getMessage()));
                break;
        }

        $invoice->save();
        $order->save();
        $this->closeTransaction($order, Transaction::TYPE_CAPTURE);
        return $this->sendResultJson();
    }

    protected function processCancelCallback(EcpCallbackDTO $callbackDto, Order $order): Json
    {
        switch ($operation_status = $callbackDto->getOperationStatus()) {
            case EcpCallbackDTO::OPERATION_STATUS_SUCCESS:
                $order->setStatus(Order::STATE_CANCELED);
                $order->setState(Order::STATE_CANCELED);
                $order->addStatusToHistory($order->getStatus(), __('Payment successfully declined'));
                break;
            case EcpCallbackDTO::OPERATION_STATUS_DECLINE:
            case EcpCallbackDTO::OPERATION_STATUS_ERROR:
                $order->setStatus(Order::STATE_CANCELED);
                $order->setState(Order::STATE_CANCELED);
                $order->addStatusToHistory($order->getStatus(), __('Payment cancellation failed. Payment authorization will be automatically declined within a few days'));
                break;
        }

        $order->save();
        $this->closeTransaction($order, Transaction::TYPE_VOID);
        return $this->sendResultJson();
    }

    protected function completeSaleOrder(EcpCallbackDTO $callbackDto, Order $order, bool $isSuccess): void
    {
        $payment = $order->getPayment();
        $payment->setIsTransactionPending(false);
        $payment->setIsTransactionClosed(true);
        $payment->setTransactionId($callbackDto->getRequestId());
        $payment->setLastTransId($callbackDto->getRequestId());
        $payment->setShouldCloseParentTransaction(1);
        $payment->addTransaction(Transaction::TYPE_CAPTURE);
        
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->setTransactionId($callbackDto->getOperationId());

        if ($isSuccess) {
            $order->setStatus(Order::STATE_PROCESSING);
            $order->setState(Order::STATE_PROCESSING);
            $invoice->setState(Invoice::STATE_PAID);
            $order->addStatusToHistory($order->getStatus(), __('Payment successfully processed by ecommpay'));

            $payment->pay($invoice);
            $order->setTotalPaid($order->getTotalPaid() + $payment->getAmountPaid());
            $order->setBaseTotalPaid($order->getBaseTotalPaid() + $payment->getAmountPaid());
        } else {
            $order->setStatus(Order::STATE_CANCELED);
            $invoice->setState(Invoice::STATE_CANCELED);
            $message = __('Operation ' . $callbackDto->getOperationType() .
                ' decline by ecommpay. Reason: ' . $callbackDto->getMessage());
            $order->addStatusToHistory($order->getStatus(), $message);
        }
        $invoice->save();
        $order->save();
    }

    protected function createAuthTransaction(EcpCallbackDTO $callbackDto, Order $order, bool $IsTransactionClosed): void
    {
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $payment_method_code = $quote->getPayment()->getMethod();

        $payment = $order->getPayment();
        $payment->setMethod($payment_method_code);
        $payment->setIsTransactionPending(false);
        $payment->setIsTransactionClosed($IsTransactionClosed);
        $payment->setTransactionId($callbackDto->getRequestId());
        $payment->setLastTransId($callbackDto->getRequestId());
        $payment->setShouldCloseParentTransaction(1);
        $payment->addTransaction(Transaction::TYPE_AUTH);
        $payment->save();
    }

    protected function closeTransaction(Order $order, string $transactionType): void
    {
        $transaction = $this->transactionRepository->getByTransactionType(
            $transactionType,
            $order->getPayment()->getId(),
            $order->getId()
        );
        $transaction->close();
        $transaction->save();
    }

    protected function getInvoiceFromOrderByRequestId(Order $order, string $requestId): Invoice
    {
        foreach ($order->getInvoiceCollection() as $invoice)
        {
            if ($invoice->getTransactionId() === $requestId) {
                return $invoice;
            }
        }
        throw new Exception(sprintf(
            'Order %s doesn\'t contain an invoice with a request id %s',
            $order->getId(), $requestId
        ));
    }

    protected function getCreditmemoByRequestId(Order $order, string $requestId): Creditmemo
    {
        foreach ($order->getCreditmemosCollection() as $creditmemo)
        {
            if ($creditmemo->getTransactionId() === $requestId) {
                return $creditmemo;
            }
        }
        throw new Exception(sprintf(
            'Order %s doesn\'t contain an creditmemo with a request id %s',
            $order->getId(), $requestId
        ));
    }
}
