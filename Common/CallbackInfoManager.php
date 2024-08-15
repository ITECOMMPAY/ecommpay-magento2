<?php

namespace Ecommpay\Payments\Common;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Ecommpay\Payments\Common\EcpCallbackDTO;

class CallbackInfoManager
{
    private const TABLE_NAME = 'ecp_callback_info';

    private AdapterInterface $connection;
    private string $resourceTableName;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceTableName = $resourceConnection->getTableName(self::TABLE_NAME);
        $this->connection = $resourceConnection->getConnection();
    }

    private function getCallbackInfoFromCallbackDTO(int $orderId, EcpCallbackDTO $callbackDto): array
    {
        return [
            'order_id' => $orderId,
            'operation_type' => $callbackDto->getOperationType(),
            'payment_id' => $callbackDto->getPaymentId(),
            'payment_method' => $callbackDto->getPaymentMethod() ?? '',
            'payment_status' => $callbackDto->getPaymentStatus(),
            'operation_id' => $callbackDto->getOperationId() ?? '',
            'callback_message' => $callbackDto->getMessage(),
        ];
    }

    public function updateCallbackInfo(int $orderId, EcpCallbackDTO $callbackDto): void
    {
        $callbackInfo = $this->getCallbackInfoFromCallbackDTO($orderId, $callbackDto);
        try {
            if (empty($this->getCallBackInfoByOrderId($orderId))) {
                $this->insert($callbackInfo);
            } else {
                $this->update($callbackInfo);
            }
        } catch (Exception $e) {
        }
    }

    public function getCallBackInfoByOrderId(int $orderId): array
    {
        $select = $this->connection->select()->from(['callback_info' => $this->resourceTableName])
            ->where('callback_info.order_id = ?', $orderId);
        if (!$record = $this->connection->fetchRow($select)) {
            return [];
        }
        return $record;
    }

    private function insert(array $callbackInfo): void
    {
        $this->connection->insert($this->resourceTableName, $callbackInfo);
    }

    private function update(array $callbackInfo): void 
    {
        $this->connection->update($this->resourceTableName, $callbackInfo, ['order_id = ?' => $callbackInfo['order_id']]);
    }
}
