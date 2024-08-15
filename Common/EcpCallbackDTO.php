<?php

namespace Ecommpay\Payments\Common;

use Ecommpay\Payments\Common\Exception\EcpCallbackHandlerException;

class EcpCallbackDTO
{
    public const
        OPERATION_STATUS_SUCCESS = 'success',
        OPERATION_STATUS_DECLINE = 'decline',
        OPERATION_STATUS_AWAITING_3DS_RESULT = 'awaiting 3ds result',
        OPERATION_STATUS_AWAITING_REDIRECT_RESULT = 'awaiting redirect result',
        OPERATION_STATUS_AWAITING_CLARIFICATION = 'awaiting clarification',
        OPERATION_STATUS_AWAITING_CUSTOMER_ACTION = 'awaiting customer action',
        OPERATION_STATUS_AWAITING_MERCHANT_AUTH = 'awaiting merchant auth',
        OPERATION_STATUS_PROCESSING = 'processing',
        OPERATION_STATUS_ERROR = 'error';

    /** Single-message purchase */
    public const OPERATION_TYPE_SALE = 'sale';

    /** Purchase again using previously registered recurring. */
    public const OPERATION_TYPE_RECURRING = 'recurring';

    /** Recurring update in payment system */
    public const OPERATION_TYPE_RECURRING_UPDATE = 'recurring update';

    /** Recurring cancel in payment system */
    public const OPERATION_TYPE_RECURRING_CANCEL = 'recurring cancel';

    /** First step of double-message purchase - hold */
    public const OPERATION_TYPE_AUTH = 'auth';

    /** Second step of double-message purchase - confirmation */
    public const OPERATION_TYPE_CAPTURE = 'capture';

    /** Void previously held double-message transaction */
    public const OPERATION_TYPE_CANCEL = 'cancel';

    /** Operation for account verification */
    public const ACCOUNT_VERIFICATION = 'account verification';

    /** Refund back purchase */
    public const OPERATION_TYPE_REFUND = 'refund';

    /** Refund back purchase within a working day */
    public const OPERATION_TYPE_REVERSAL = 'reversal';

    private const REFUND_OPERATION_TYPES = [self::OPERATION_TYPE_REFUND, self::OPERATION_TYPE_REVERSAL];

    private string $orderId;
    private ?string $paymentMethod;
    private string $paymentId;
    private string $paymentStatus;
    private int $paymentAmount;
    private string $paymentCurrency;
    private int $operationAmount;
    private int $operationInitialAmount;
    private ?int $operationId;
    private string $operationType;
    private string $operationStatus;
    private array $callbackArray;
    private string $requestId;
    private string $message = '';

    /**
     * @throws EcpCallbackHandlerException
     */
    public static function create(string $callbackJson): EcpCallbackDTO
    {
        $callbackArray = json_decode($callbackJson, true);

        if ($callbackArray === null) {
            throw new EcpCallbackHandlerException('Malformed callback data.');
        }

        if (empty($callbackArray['operation']['status'])) {
            throw new EcpCallbackHandlerException('Empty "status" field in callback data.');
        }

        if (empty($callbackArray['payment']) || empty($callbackArray['payment']['id'])) {
            throw new EcpCallbackHandlerException('Missed "payment.id" field in callback data.');
        }

        if (empty($callbackArray['operation']['request_id'])) {
            throw new EcpCallbackHandlerException('Empty "operation.request_id" field in callback data.');
        }

        $object = new self();
        $object->callbackArray = $callbackArray;
        $object->paymentId = $callbackArray['payment']['id'];
        $object->paymentMethod = $callbackArray['payment']['method'] ?? null;
        $object->operationType = $callbackArray['operation']['type'];
        $object->paymentStatus = $callbackArray['payment']['status'];
        if (!empty($callbackArray['payment']['sum'])) {
            $object->paymentAmount = $callbackArray['payment']['sum']['amount'];
            $object->paymentCurrency = $callbackArray['payment']['sum']['currency'];
        }
        if (!empty($callbackArray['operation']['sum'])) {
            $object->operationAmount = $callbackArray['operation']['sum']['amount'];
        }
        if (!empty($callbackArray['operation']['sum_initial'])) {
            $object->operationInitialAmount = $callbackArray['operation']['sum_initial']['amount'];
        }
        $object->operationId = $callbackArray['operation']['id'] ?? null;
        $object->operationStatus = $callbackArray['operation']['status'];
        $object->requestId = $callbackArray['operation']['request_id'];
        if (!empty($callbackArray['operation']['message'])) {
            $object->message = $callbackArray['operation']['message'];
        }
        return $object;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getPaymentAmount(): int
    {
        return $this->paymentAmount;
    }

    public function getPaymentCurrency(): string
    {
        return $this->paymentCurrency;
    }


    public function getOperationAmount(): int
    {
        return $this->operationAmount;
    }

    public function getOperationInitialAmount(): int
    {
        return $this->operationInitialAmount;
    }

    public function getOperationId(): ?int
    {
        return $this->operationId;
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCallbackArray(): array
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

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getOperationStatus(): string
    {
        return $this->operationStatus;
    }
}
