<?php

namespace Ecommpay\Payments\Common;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Registry;
use Magento\Checkout\Model\Session;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderPaymentManager
{
    private const TABLE_NAME = 'ecp_order_payment';
    private const ECOMMPAY_PREFIX = 'ecommpay_';
    private const RESTORE_CART_STATUSES = [
        'pending',
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_CANCELED
    ];

    private AdapterInterface $connection;
    private CallbackInfoManager $callbackInfoManager;
    private OrderRepositoryInterface $orderRepository;
    private Registry $registry;
    private Session $checkoutSession;
    private string $resourceTableName;

    public function __construct(
        CallbackInfoManager $callbackInfoManager,
        OrderRepositoryInterface $orderRepository,
        Registry $registry,
        ResourceConnection $resource,
        Session $checkoutSession
    ) {
        $this->resourceTableName = $resource->getTableName(self::TABLE_NAME);
        $this->checkoutSession = $checkoutSession;
        $this->connection = $resource->getConnection();
        $this->orderRepository = $orderRepository;
        $this->callbackInfoManager = $callbackInfoManager;
        $this->registry = $registry;
    }

    public function insert(int $orderId, string $paymentId): ?int
    {
        try {
            return $this->connection->insert($this->resourceTableName, ['order_id' => $orderId, 'payment_id' => $paymentId]);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getOrderIdByPaymentId($paymentId)
    {
        $select = $this->connection->select()->from(['order_payment' => $this->resourceTableName])
            ->where('order_payment.payment_id = ?', $paymentId);
        $orderPayment = $this->connection->fetchRow($select);
        if (is_array($orderPayment) && !empty($orderPayment['order_id'])) {
            return $orderPayment['order_id'];
        } else {
            return null;
        }
    }

    public function getPaymentIdByOrderId($orderId)
    {
        $select = $this->connection->select()->from(['order_payment' => $this->resourceTableName])
            ->where('order_payment.order_id = ?', $orderId);
        $orderPayment = $this->connection->fetchRow($select);
        if (is_array($orderPayment) && !empty($orderPayment['payment_id'])) {
            return $orderPayment['payment_id'];
        } else {
            return null;
        }
    }

    public function deleteOrderAndRelatedData(int $orderId): void
    {
        $this->deleteByOrderId($orderId);
        $this->callbackInfoManager->deleteByOrderId($orderId);
        $order = $this->orderRepository->get($orderId);
        $this->registry->register('isSecureArea', true);
        $this->orderRepository->delete($order);
        $this->registry->unregister('isSecureArea');
    }

    public function deleteByOrderId($orderId)
    {
        $this->connection->delete($this->resourceTableName, ['order_id' => $orderId]);
    }

    public function getLastRealSessionEcommpayOrder(): ?Order
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId()) {
            return null;
        }
        if (!$payment = $order->getPayment()) {
            return null;
        }
        return strpos($payment->getMethod(), $this::ECOMMPAY_PREFIX) !== false ? $order : null;
    }

    public function isRequiredRestoreQuote(Order $order): bool
    {
        $orderStatus = $order->getStatus();
        return in_array($orderStatus, $this::RESTORE_CART_STATUSES, true);
    }
}
