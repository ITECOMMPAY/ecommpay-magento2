<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use Ecommpay\Payments\Common\CallbackInfoManager;
use Ecommpay\Payments\Common\EcpCallbackDTO;
use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpRefundProcessor;
use Ecommpay\Payments\Common\EcpRefundResult;
use Ecommpay\Payments\Common\EcpSigner;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Ecommpay\Payments\Common\Exception\EcpCallbackHandlerException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Creditmemo;

class Index extends Action implements CsrfAwareActionInterface
{
    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var InvoiceService */
    protected $invoiceService;

    /** @var CreditmemoRepositoryInterface */
    protected $creditmemoRepository;

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

    /**
     *
     * @param Context $context
     * @param RequestInterface $request
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param InvoiceService $invoiceService
     * @param PageFactory $pageFactory
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        InvoiceService $invoiceService,
        PageFactory $pageFactory,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->invoiceService = $invoiceService;
        $this->pageFactory = $pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configHelper = EcpConfigHelper::getInstance();
        $this->signer = new EcpSigner();
        $this->callbackInfoManager = new CallbackInfoManager();
        $this->orderPaymentManager = new OrderPaymentManager();
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function sendResultJson($data = 'Ok', array $errors = [], $responseCode = 200)
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setHttpResponseCode($responseCode);
        return $resultJson->setData(['Message' => $data, 'Errors' => $errors]);
    }

    public function execute()
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
        if ($callbackDto->isSale()) {
            return $this->processSaleCallback($callbackDto);
        }
        if ($callbackDto->isRefundCallback()) {
            $this->processRefundCallback($callbackDto);
        }
    }

    protected function processRefundCallback(EcpCallbackDTO $callbackDto)
    {
        $ecpCallbackResult = new EcpRefundResult(
            $callbackDto->getOrderId(),
            $callbackDto->getRequestId(),
            $callbackDto->getOperationStatus(),
            $callbackDto->getMessage()
        );

        $resource = $this->_objectManager->get(ResourceConnection::class);
        /** @var \Magento\Framework\App\ResourceConnection $resource */
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_creditmemo_comment');
        $select = $connection->select()->from(
            ['scc' => $tableName],
            ['scc.entity_id', 'scc.parent_id']
        )->where('scc.comment = :comment');
        $comment = sprintf(EcpRefundProcessor::REFUND_ID_CONTAINING_COMMENT, $ecpCallbackResult->getRefundExternalId());
        $queryParams = ['comment' => $comment];
        $result = $connection->fetchRow($select, $queryParams);

        if (!$result) {
            return $this->sendResultJson('Unknown operation identifier.', [$result], 400);
        }
        try {
            /** @var Creditmemo $cm */
            $cm = $this->creditmemoRepository->get($result['parent_id']);
            $order = $cm->getOrder();
        } catch (\Exception $e) {
            return $this->sendResultJson('Unable to find a refund request.', [$e->getMessage()], 400);
        }

        if (!$connection->delete($tableName, 'entity_id = ' . $result['entity_id'])) {
            return $this->sendResultJson('Unable to process callback.', [], 400);
        }

        $datatime = new \DateTime();
        $now = $datatime->format('d.m.Y H:i:s');
        $refundId = $cm->getId();
        $refundStatuses = ['reversed', 'refunded', 'partially reversed', 'partially refunded'];
        if (in_array($callbackDto->getPaymentStatus(), $refundStatuses)) {
            $cm->setState(Creditmemo::STATE_REFUNDED);
            $commentFormat = 'Money-back request #%s was successfully processed at %s';
            $cm->addComment(sprintf($commentFormat, $refundId, $now));
            if (in_array($callbackDto->getPaymentStatus(), ['reversed', 'refunded'])) {
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED)->save();
            } else {
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)->save();
            }
        } else {
            $cm->setState(Creditmemo::STATE_CANCELED);
            $commentFormat = 'Money-back request #%s was declined at %s. Reason: %s';
            $cm->addComment(sprintf($commentFormat, $refundId, $now, $ecpCallbackResult->getDescription()));
        }
        try {
            $cm->save();
        } catch (\Exception $e) {
            return $this->sendResultJson('Coudn`t save credit memo', [$e->getMessage()], 500);
        }

        return $this->sendResultJson();
    }

    /**
     *
     * @param Order $order
     * @param string $message
     * @param string $operationId
     * @throws \Exception
     */
    protected function updateOrderStatus($order, $message, $operationId)
    {
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->addStatusToHistory($order->getStatus(), $message);

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->setTransactionId($operationId);
        $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
        $invoice->save();

        /**
         *
         * @var Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->pay($invoice);
        $order->setTotalPaid($order->getTotalPaid() + $payment->getAmountPaid());
        $order->setBaseTotalPaid($order->getBaseTotalPaid() + $payment->getAmountPaid());

        $order->save();
    }

    protected function processSaleCallback(EcpCallbackDTO $callbackDto)
    {
        $orderId = $callbackDto->getOrderId();

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return $this->sendResultJson('Order does not exist', [$e->getMessage()], 404);
        } catch (\Magento\Framework\Exception\InputException $e) {
            return $this->sendResultJson('Order does not exist, no order_id is provided', [$e->getMessage()], 404);
        }

        switch ($callbackDto->getPaymentStatus()) {
            case 'decline':
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $message = __('Operation ' . $callbackDto->getOperationType() .
                    ' decline by ecommpay. Reason: ' . $callbackDto->getMessage());
                $order->addStatusToHistory($order->getStatus(), $message);
                break;
            case 'success':
                $message = __('Payment successfully processed by ecommpay');
                $this->updateOrderStatus($order, $message, $callbackDto->getOperationId());
                break;
            case 'external error':
            case 'internal error':
            case 'awaiting customer':
            case 'expired':
                $message = __('Operation ' . $callbackDto->getOperationType() .
                    ' failed to be processed by ecommpay. Reason: ' . $callbackDto->getMessage());
                $order->addStatusToHistory($order->getStatus(), $message);
                break;
        }
        $order->save();

        $callbackInfoManager = new CallbackInfoManager();
        $callbackInfoManager->insert(
            $orderId,
            $callbackDto->getOperationType(),
            $callbackDto->getPaymentId(),
            $callbackDto->getPaymentMethod(),
            $callbackDto->getPaymentStatus(),
            $callbackDto->getMessage(),
            $callbackDto->getOperationId()
        );
        return $this->sendResultJson();
    }
}
