<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpOperationException;
use Ecommpay\Payments\Common\EcpOrderIdFormatter;
use Ecommpay\Payments\Common\EcpRefundProcessor;
use Ecommpay\Payments\Common\EcpSigner;
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
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var EcpSigner
     */
    protected $signer;

    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @var string|null
     */
    protected $currentOrderLink;

    /**
     * @var EcpConfigHelper
     */
    protected $configHelper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

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

        $configIsTest = $this->configHelper->isTestMode();

        if (!empty($body)) {
            $this->processRefundCallback($body);
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->getLayout()->initMessages();

        if ($configIsTest && $this->getRequest()->getParam('test')) {
            return $this->checkTestOrder($resultPage);
        }

        if ($this->getRequest()->getParam('on_success')) {
            $message = __('Your payment is being processed by ecommpay');

            $orderId = $this->getRequest()->getParam('order_id');
            list($order, $_) = $this->getOrder($orderId);
            if ($order && $order->getStatus() === \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW) {
                $message = __('Payment successfully processed by ecommpay');
            }

            $resultPage->getLayout()->getBlock('endpayment')->setOrderMessage($message);
            $resultPage->getLayout()->getBlock('endpayment')->setOrderLink($this->currentOrderLink);
            return $resultPage;
        }

        return $this->checkRealOrder($resultPage, $body);
    }

    protected function processRefundCallback($data)
    {
        $refundProcessor = new EcpRefundProcessor();

        try {
            $ecpCallbackResult = $refundProcessor->processCallback($data);
        } catch (EcpOperationException $ex) {
            return; // not a refund operation, go back to order confirmation
        } catch (\Exception $e) {
            http_response_code(400);
            die($e->getMessage());
        }

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
        if ($ecpCallbackResult->isSuccess()) {
            $cm->setState(Creditmemo::STATE_REFUNDED);
            $cm->addComment('Money-back request #' . $refundId . ' was successfully processed at ' . $now->format('d.m.Y H:i:s'));
            $order->setStatus(EcpRefundProcessor::ORDER_STATUS_PARTIAL_REFUND)->save();
            if (in_array(json_decode($data, true)['payment']['status'], ['reversed', 'refunded'])) {
                $order->setStatus(EcpRefundProcessor::ORDER_STATUS_FULL_REFUND)->save();
            } else {
                $order->setStatus(EcpRefundProcessor::ORDER_STATUS_PARTIAL_REFUND)->save();
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

    protected function checkTestOrder($resultPage) {
        $orderId = $this->getRequest()->getParam('order_id');
        $paymentStatus = $this->getRequest()->getParam('status');

        list ($order, $message) = $this->getOrder($orderId);
        if (!$order) {
            $resultPage->getLayout()->getBlock('endpayment')->setOrderMessage($message);
            return $resultPage;
        }

        $message = __('Payment failed to be processed by ecommpay');

        if ($paymentStatus === 'success') {
            $message = __('Payment successfully processed by ecommpay');
            $paymentId = time();
            $this->updateOrderStatus($order, $message, $paymentId);
        }

        $resultPage->getLayout()->getBlock('endpayment')->setOrderMessage($message);
        $resultPage->getLayout()->getBlock('endpayment')->setOrderLink($this->currentOrderLink);
        return $resultPage;
    }

    protected function checkRealOrder($resultPage, $body)
    {
        $bodyData = json_decode($body, true);

        $message = __('Signature failed verification');
        if (is_array($bodyData) && $this->signer->checkSignature($bodyData)) {
            $orderId = $bodyData['payment']['id'];
            $orderId = EcpOrderIdFormatter::removeOrderPrefix($orderId, EcpConfigHelper::CMS_PREFIX);

            $paymentStatus = $bodyData['payment']['status'];

            list ($order, $message) = $this->getOrder($orderId);
            if (!$order) {
                $resultPage->getLayout()->getBlock('endpayment')->setOrderMessage($message);
                return $resultPage;
            }

            $message = __('Payment failed to be processed by ecommpay');

            if ($paymentStatus === 'success') {
                $paymentId = $bodyData['operation']['id'];

                $message = __('Payment successfully processed by ecommpay');
                $this->updateOrderStatus($order, $message, $paymentId);
            } else {
                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();
            }
        }
        $resultPage->getLayout()->getBlock('endpayment')->setOrderMessage($message);
        return $resultPage;
    }

    /**
     * @param $orderId
     * @return array
     */
    protected function getOrder($orderId)
    {
        $message = '';
        $order = null;

        try {
            $order = $this->orderRepository->get($orderId);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerSession = $objectManager->get('Magento\Customer\Model\Session');
            if($customerSession->isLoggedIn()) {
                $this->currentOrderLink = '/sales/order/view/order_id/' . $orderId . '/';
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $message = __('Order does not exist');
        } catch (\Magento\Framework\Exception\InputException $e) {
            $message = __('Order does not exist, no order_id is provided');
        }
        return [$order, $message];
    }

    /**
     * @param Order $order
     * @param string $message
     * @param int $paymentId
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateOrderStatus($order, $message, $paymentId)
    {
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $order->addStatusToHistory($order->getStatus(), $message);
        //$order->save();

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->setTransactionId($paymentId);
        $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
        $invoice->save();

        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->pay($invoice);

        $order->setTotalPaid($order->getTotalPaid() + $payment->getAmountPaid());
        $order->setBaseTotalPaid($order->getBaseTotalPaid() + $payment->getAmountPaid());
        $order->save();
    }
}