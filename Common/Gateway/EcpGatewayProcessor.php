<?php

namespace Ecommpay\Payments\Common\Gateway;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\EcpSigner;
use Ecommpay\Payments\Common\Exception\EcpGateRequestException;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\HTTP\Client\Curl;

class EcpGatewayProcessor
{
    protected ClientInterface $httpClient;
    protected EcpSigner $signer;
    protected EcpConfigHelper $configHelper;
    protected OrderPaymentManager $orderPaymentManager;
    protected RequestInterface $request;
    protected Session $session;

    public function __construct(
        Curl $curl,
        EcpSigner $ecpSigner,
        OrderPaymentManager $orderPaymentManager,
        RequestInterface $request,
        Session $session,
        EcpConfigHelper $configHelper
    ) {

        $this->signer = $ecpSigner;
        $this->configHelper = $configHelper;
        $this->httpClient = $curl;
        $this->orderPaymentManager = $orderPaymentManager;
        $this->request = $request;
        $this->session = $session;
    }

    /**
     * @throws EcpGateRequestException
     */
    public function capture(int $orderId, float $amount, string $currency, string $endpoint): EcpGatewayResponse
    {
        $requestBody = $this->getRequestBody($orderId, $amount, $currency);
        $this->sendRequest($requestBody, $this->configHelper->getGateCaptureEndpoint($endpoint));
        return $this->handleResponse();
    }

    private function getRequestBody(
        int $orderId,
        ?float $amount = null,
        ?string $currency = null,
        bool $isRefund = false
    ): array {
        $requestBody = [
            'general' => [
                'project_id' => $this->configHelper->getProjectId(),
                'payment_id' => $this->orderPaymentManager->getPaymentIdByOrderId($orderId),
                'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            ],
            'interface_type' => $this->configHelper->getInterfaceTypeId()
        ];
        if ($amount && $currency) {
            $requestBody['payment']['amount'] = EcpConfigHelper::priceMultiplyByCurrencyCode($amount, $currency);
            $requestBody['payment']['currency'] = $currency;
        }
        if ($isRefund) {
            $requestBody['customer']['ip_address'] = $this->request->getServer('REMOTE_ADDR');
            $requestBody['payment']['description'] = 'User ' . $this->session->getUser()->getId() . ' creates refund';
        }
        $requestBody['general']['signature'] = $this->signer->getSignature($requestBody);
        return $requestBody;
    }

    private function sendRequest(array $requestBody, string $endpoint): void
    {
        $this->httpClient->addHeader('Content-Type', 'application/json');
        $this->httpClient->post($endpoint, json_encode($requestBody));
    }

    /**
     * @throws EcpGateRequestException
     */
    private function handleResponse(): EcpGatewayResponse
    {
        $responseBody = $this->httpClient->getBody();
        $responseJson = json_decode($responseBody, true);
        if ($responseJson === null) {
            throw new EcpGateRequestException('Malformed response.' . $responseBody);
        }
        return new EcpGatewayResponse($responseJson);
    }

    /**
     * @throws EcpGateRequestException
     */
    public function cancel(int $orderId, string $endpoint): EcpGatewayResponse
    {
        $requestBody = $this->getRequestBody($orderId);
        $this->sendRequest($requestBody, $this->configHelper->getGateCancelEndpoint($endpoint));
        return $this->handleResponse();
    }

    /**
     * @throws EcpGateRequestException
     */
    public function refund(int $orderId, float $amount, string $currency, string $endpoint): EcpGatewayResponse
    {
        $requestBody = $this->getRequestBody($orderId, $amount, $currency, true);
        $this->sendRequest($requestBody, $this->configHelper->getGateRefundEndpoint($endpoint));
        return $this->handleResponse();
    }
}
