<?php

namespace Ecommpay\Payments\Common\Gateway;

class EcpGatewayResponse
{
    private const STATUS_SUCCESS = 'success';

    protected string $status;
    protected ?string $request_id;
    protected ?string $message;

    public function __construct(array $responseJsonArray)
    {
        $this->status = $responseJsonArray['status'];
        $this->request_id = $responseJsonArray['request_id'] ?? null;
        $this->message = $responseJsonArray['message'] ?? null;
    }

    public function isSuccess(): bool
    {
        return strtolower($this->status) === self::STATUS_SUCCESS;
    }

    public function getRequestId(): ?string 
    {
        return $this->request_id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
