<?php

namespace Ecommpay\Payments\Common;

use Ecommpay\Payments\Signer;
use Exception;

class EcpRefundProcessor
{
    const REFUND_ID_CONTAINING_COMMENT = 'Refund request #%s was created';

    /** @var EcpSigner */
    private $signer;

    /** @var EcpConfigHelper */
    private $configHelper;

    public function __construct()
    {
        $this->signer = new EcpSigner();
        $this->configHelper = EcpConfigHelper::getInstance();
    }


    /**
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @return EcpRefundResult
     * @throws Exception
     */
    public function processRefund(\Magento\Sales\Model\Order $order, $amount, $currency, $endpoint)
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $authSession = $objectManager->get('\Magento\Backend\Model\Auth\Session');

        $projectId = $this->configHelper->getProjectId();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->configHelper->getGateRefundEndpoint($endpoint));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $orderPaymentManager = new OrderPaymentManager();
        $payment_id = $orderPaymentManager->getPaymentIdByOrderId($order->getEntityId());

        $post = [
            'general' => [
                'project_id' => $projectId,
                'payment_id' => $payment_id,
                'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            ],
            'customer' => [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
            ],
            'payment' => [
                'amount' => EcpConfigHelper::priceMultiplyByCurrencyCode($amount, $currency),
                'currency' => $currency,
                'description' => 'User ' . strval($authSession->getUser()->getId()) . ' create refund'
            ],
            'interface_type' => $this->configHelper->getInterfaceTypeId()
        ];

        $post['general']['signature'] = $this->signer->getSignature($post);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        $out = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($out, true);
        if ($data === null) {
            throw new Exception('Malformed response');
        }

        if ($httpStatus != 200) {
            return new EcpRefundResult($order->getEntityId(), null, $data['status'], $data['message']);
        }

        return new EcpRefundResult($order->getEntityId(), $data['request_id'], $data['status']);
    }
}
