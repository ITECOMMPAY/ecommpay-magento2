<?php

namespace Ecommpay\Payments\Common;

use Ecommpay\Payments\Signer;
use Ecommpay\Payments\Common\Exception\EcpGateRequestException;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\Client\Curl;

class EcpRefundProcessor
{
    public const REFUND_ID_CONTAINING_COMMENT = 'Refund request #%s was created';

    /** @var EcpSigner */
    private $signer;

    /** @var EcpConfigHelper */
    private $configHelper;

    /** @var Curl */
    private $curl;

    public function __construct()
    {
        $this->signer = new EcpSigner();
        $this->configHelper = EcpConfigHelper::getInstance();
        $this->curl = new Curl();
    }

    /**
     *
     * @param \Magento\Sales\Model\Order $order
     * @param float $amount
     * @param string $currency
     * @param string $endpoint
     * @return EcpRefundResult
     * @throws \Exception
     */
    public function processRefund(\Magento\Sales\Model\Order $order, $amount, $currency, $endpoint)
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $authSession = $objectManager->get(Session::class);
        $request = $objectManager->get(RequestInterface::class);
        $orderPaymentManager = new OrderPaymentManager();

        $post = [
            'general' => [
                'project_id' => $this->configHelper->getProjectId(),
                'payment_id' => $orderPaymentManager->getPaymentIdByOrderId($order->getEntityId()),
                'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            ],
            'customer' => [
                'ip_address' => $request->getServer('REMOTE_ADDR'),
            ],
            'payment' => [
                'amount' => EcpConfigHelper::priceMultiplyByCurrencyCode($amount, $currency),
                'currency' => $currency,
                'description' => 'User ' . (string)($authSession->getUser()->getId()) . ' create refund'
            ],
            'interface_type' => $this->configHelper->getInterfaceTypeId()
        ];

        $post['general']['signature'] = $this->signer->getSignature($post);

        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->configHelper->getGateRefundEndpoint($endpoint), json_encode($post));
        $response = $this->curl->getBody();
        $httpStatus = $this->curl->getStatus();

        $data = json_decode($response, true);
        if ($data === null) {
            throw new EcpGateRequestException('Malformed response.' . $response);
        }

        if ($httpStatus != 200) {
            return new EcpRefundResult($order->getEntityId(), null, $data['status'], $data['message']);
        }

        return new EcpRefundResult($order->getEntityId(), $data['request_id'], $data['status']);
    }
}
