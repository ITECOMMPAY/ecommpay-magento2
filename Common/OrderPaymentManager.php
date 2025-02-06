<?php

namespace Ecommpay\Payments\Common;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class OrderPaymentManager
{
    private const TABLE_NAME = 'ecp_order_payment';

    private AdapterInterface $connection;
    private string $resourceTableName;

    public function __construct(ResourceConnection $resource)
    {
        $this->resourceTableName = $resource->getTableName(self::TABLE_NAME);
        $this->connection = $resource->getConnection();
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

    public function deleteByOrderId($orderId)
    {
        $this->connection->delete($this->resourceTableName, ['order_id' => $orderId]);
    }
}
