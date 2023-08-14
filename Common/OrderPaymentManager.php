<?php

namespace Ecommpay\Payments\Common;

class OrderPaymentManager
{
    const TABLE_NAME = 'ecp_order_payment';
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface  */
    private $connection;
    private $tableName;
    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\App\ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $this->tableName = $resource->getTableName(self::TABLE_NAME);
        $this->connection = $resource->getConnection();
    }

    /**
     * @param int $orderId
     * @param string $paymentId
     * @return int inserted rows count
     */
    public function insert(int $orderId, string $paymentId)
    {
        try {
            return $this->connection->insert($this->tableName, ['order_id' => $orderId, 'payment_id' => $paymentId]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOrderIdByPaymentId($paymentId)
    {
        $select = $this->connection->select()->from(['order_payment' => $this->tableName])
            ->where('order_payment.payment_id = ?', $paymentId);
        $orderPayment =  $this->connection->fetchRow($select);
        if (is_array($orderPayment) && !empty($orderPayment['order_id'])) {
            return $orderPayment['order_id'];
        } else {
            return null;
        }
    }

    public function getPaymentIdByOrderId($orderId)
    {
        $select = $this->connection->select()->from(['order_payment' => $this->tableName])
        ->where('order_payment.order_id = ?', $orderId);
        $orderPayment =  $this->connection->fetchRow($select);
        if (is_array($orderPayment) && !empty($orderPayment['payment_id'])) {
            return $orderPayment['payment_id'];
        } else {
            return null;
        }
    }
}