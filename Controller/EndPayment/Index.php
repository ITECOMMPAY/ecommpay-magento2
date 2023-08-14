<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use Ecommpay\Payments\Common\CallbackInfoManager;
use Ecommpay\Payments\Common\EcpCallbackDTO;
use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpRefundProcessor;
use Ecommpay\Payments\Common\EcpRefundResult;
use Ecommpay\Payments\Common\EcpSigner;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
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
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param InvoiceService $invoiceService
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        InvoiceService $invoiceService,
        PageFactory $pageFactory,
        JsonFactory $resultJsonFactory
    )
    {
        parent::__construct($context);
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

    public function execute()
    {
        $body = file_get_contents('php://input');

        try {
            $callbackDto = EcpCallbackDTO::create($body);
            if (!$this->signer->checkSignature($callbackDto->getCallbackArray())) {
                throw new \Exception('Wrong callback data signature.');
            }
        } catch (\Exception $e) {
            http_response_code(400);
            die($e->getMessage());
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

        $resource = $this->_objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var \Magento\Framework\App\ResourceConnection $resource */
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('sales_creditmemo_comment');

        $comment = sprintf(EcpRefundProcessor::REFUND_ID_CONTAINING_COMMENT, $ecpCallbackResult->getRefundExternalId());

        $sql = 'SELECT entity_id, parent_id FROM ' . $tableName . ' WHERE comment = :comment';
        $queryParams = ['comment' => $comment];

        $result = $connection->fetchRow($sql, $queryParams);
        if (!$result) {
            http_response_code(400);
            die('Unknown operation identifier.');
        }
        try {
            /** @var Creditmemo $cm */
            $cm = $this->creditmemoRepository->get($result['parent_id']);
            $order = $cm->getOrder();
        } catch (\Exception $e) {
            http_response_code(400);
            die('Unable to find a refund request.');
        }

        if (!$connection->delete($tableName, 'entity_id = ' . $result['entity_id'])) {
            http_response_code(400);
            die('Unable to process callback.');
        }

        $now = new \DateTime();
        $refundId = $cm->getId();
        if (in_array($callbackDto->getPaymentStatus(), ['reversed', 'refunded', 'partially reversed', 'partially refunded'])) {
            $cm->setState(Creditmemo::STATE_REFUNDED);
            $cm->addComment('Money-back request #' . $refundId . ' was successfully processed at ' . $now->format('d.m.Y H:i:s'));
            if (in_array($callbackDto->getPaymentStatus(), ['reversed', 'refunded'])) {
                $order->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED)->save();
            } else {
                $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)->save();
            }
        } else {
            $cm->setState(Creditmemo::STATE_CANCELED);
            $cm->addComment('Money-back request #' . $refundId . ' was declined at ' . $now->format('d.m.Y H:i:s') . '. Reason: ' . $ecpCallbackResult->getDescription());
        }
        try {
            $cm->save();
        } catch (\Exception $e) {
            http_response_code(400);
            die('Unable to process callback.');
        }

        http_response_code(200);
        die();
    }


    /**
     * @param Order $order
     * @param string $message
     * @param int $paymentId
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
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

        /** @var Order\Payment $payment */
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
            http_response_code(200);
            die('Order does not exist');
        } catch (\Magento\Framework\Exception\InputException $e) {
            http_response_code(200);
            die('Order does not exist, no order_id is provided');
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
        http_response_code(200);
        die('OK');
    }
}