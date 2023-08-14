<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\ResourceConnection;

class CallbackInfoManager
{
    private const TABLE_NAME = 'ecp_callback_info';
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface  */
    private $connection;
    private $tableName;
    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /**
         *
         * @var ResourceConnection $resource */
        $resource = $objectManager->get(ResourceConnection::class);
        $this->tableName = $resource->getTableName(self::TABLE_NAME);
        $this->connection = $resource->getConnection();
    }

    public function insert(
        int $orderId,
        string $operationType,
        string $paymentId,
        string $paymentMethod,
        string $paymentStatus,
        string $callbackMessage,
        string $operationId
    ) {
        $callbackInfo = [
            'order_id' => $orderId,
            'operation_type' => $operationType,
            'payment_id' => $paymentId,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'operation_id' => $operationId,
            'callback_message' => $callbackMessage,
        ];

        try {
            $select = $this->connection->select()->from(['callback_info' => $this->tableName])
                ->where('callback_info.payment_id = ?', $paymentId);

            $info = $this->connection->fetchRow($select);

            if (is_array($info)) {
                return $this->connection->update($this->tableName, $callbackInfo, ['payment_id = ?' => $paymentId]);
            }

            return $this->connection->insert($this->tableName, $callbackInfo);

        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCallBackInfoByOrderId($orderId)
    {
        $select = $this->connection->select()->from(['callback_info' => $this->tableName])
            ->where('callback_info.order_id = ?', $orderId);
        $callbackInfo =  $this->connection->fetchRow($select);
        return $callbackInfo;
    }

    public function createEntryFromArray($callbackData)
    {
        $callbackData = json_decode($callbackData, true);
    }
}
