<?php

namespace Ecommpay\Payments\Common;

use Exception;

class EcpCallbackDTO
{
    /** Single-message purchase */
    const OPERATION_TYPE_SALE = 'sale';

    /** Purchase again using previously registered recurring. */
    const OPERATION_TYPE_RECURRING = 'recurring';

    /** Recurring update in payment system */
    const OPERATION_TYPE_RECURRING_UPDATE = 'recurring update';

    /** Recurring cancel in payment system */
    const OPERATION_TYPE_RECURRING_CANCEL = 'recurring cancel';

    /** First step of double-message purchase - hold */
    const OPERATION_TYPE_AUTH = 'auth';

    /** Second step of double-message purchase - confirmation */
    const OPERATION_TYPE_CAPTURE = 'capture';

    /** Void previously held double-message transaction */
    const OPERATION_TYPE_CANCEL = 'cancel';

    /** Operation for account verification */
    const ACCOUNT_VERIFICATION = 'account verification';

    /** Refund back purchase */
    const OPERATION_TYPE_REFUND  = 'refund';


    const OPERATION_TYPE_REVERSAL  = 'reversal';

    const REFUND_OPERATION_TYPES = [self::OPERATION_TYPE_REFUND, self::OPERATION_TYPE_REVERSAL];
    private $orderId;
    private $paymentMethod;
    private $paymentId;
    private $paymentStatus;
    private $operationId;
    private $operationType;
    private $operationStatus;
    private $callbackArray;
    private $requestId;
    private $message = '';

    /**
     * @throws Exception
     */
    public static function create($callbackJson): EcpCallbackDTO
    {
        $callbackArray = json_decode($callbackJson, true);

        if ($callbackArray === null) {
            throw new Exception('Malformed callback data.');
        }

        if (empty($callbackArray['operation']['status'])) {
            throw new Exception('Empty "status" field in callback data.');
        }

        $status = $callbackArray['operation']['status'];
        // TODO а нам точно надо отклонять все остальные статусы?
        /*if (!in_array($status, ['success', 'decline'])) {
            throw new Exception('Received status is not final.');
        }*/

        if (empty($callbackArray['payment']) || empty($callbackArray['payment']['id'])) {
            throw new Exception('Missed "payment.id" field in callback data.');
        }

        if (empty($callbackArray['operation']['request_id'])) {
            throw new Exception('Empty "operation.request_id" field in callback data.');
        }

        $paymentId = $callbackArray['payment']['id'];
        $paymentMethod = $callbackArray['payment']['method'];
        $operationType = $callbackArray['operation']['type'];//['operation']['status']
        $paymentStatus = $callbackArray['payment']['status'];
        $operationId = $callbackArray['operation']['id'];
        $operationStatus = $callbackArray['operation']['status'];
        $requestId = $callbackArray['operation']['request_id'];

        $object = new self();
        $object->callbackArray = $callbackArray;
        $object->paymentId = $paymentId;
        $object->paymentMethod = $paymentMethod;
        $object->operationType = $operationType;
        $object->paymentStatus = $paymentStatus;
        $object->operationId = $operationId;
        $object->operationStatus = $operationStatus;
        $object->requestId = $requestId;
        if (!empty($callbackArray['operation']['message'])) {
            $object->message = $callbackArray['operation']['message'];
        }
        return $object;
    }

    private function __construct()
    {
    }

    /** @return string */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /** @return int */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /** @param int $orderId */
    public function setOrderId($orderId): void
    {
        $this->orderId = $orderId;
    }

    /** @return string */
    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    /** @return string */
    public function getOperationId()
    {
        return $this->operationId;
    }

    /** @return string */
    public function getOperationType()
    {
        return $this->operationType;
    }

    /** @return string */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /** @return string */
    public function getMessage()
    {
        return $this->message;
    }

    /** @return array */
    public function getCallbackArray()
    {
        return $this->callbackArray;
    }

    public function isRefundCallback(): bool
    {
        return !empty($this->operationType) && in_array($this->operationType, self::REFUND_OPERATION_TYPES);
    }

    public function isSale(): bool
    {
        return $this->operationType === self::OPERATION_TYPE_SALE;
    }

    /** @return string */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /** @return string */
    public function getOperationStatus()
    {
        return $this->operationStatus;
    }
}