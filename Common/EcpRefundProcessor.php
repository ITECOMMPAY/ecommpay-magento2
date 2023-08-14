<?php

namespace Ecommpay\Payments\Common;

use Ecommpay\Payments\Signer;
use Exception;

class EcpRefundProcessor
{
    const ORDER_STATUS_PARTIAL_REFUND = 'partial_refund';
    const ORDER_STATUS_FULL_REFUND = 'full_refund';

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
        $paymentPrefix = $this->configHelper->getPaymentPrefix();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->configHelper->getGateRefundEndpoint($endpoint));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $post = [
            'general' => [
                'project_id' => $projectId,
                'payment_id' => $paymentPrefix
                    ? EcpOrderIdFormatter::addOrderPrefix($order->getEntityId(), $paymentPrefix)
                    : $order->getEntityId(),
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

    /**
     * @param string $rawData
     * @return EcpRefundResult
     * @throws Exception
     */
    public function processCallback($rawData)
    {
        $data = json_decode($rawData, true);
        if ($data === null) {
            throw new Exception('Malformed callback data.');
        }
        if (empty($data['operation']) || empty($data['operation']['type']) || !in_array($data['operation']['type'], ['refund', 'reversal'])) {
            throw new EcpOperationException('Invalid or missed operation type, expected "refund".');
        }
        if (!$this->signer->checkSignature($data)) {
            throw new Exception('Wrong data signature.');
        }
        if (empty($data['operation']['status'])) {
            throw new Exception('Empty "status" field in callback data.');
        }
        $status = $data['operation']['status'];
        if (!in_array($status, ['success', 'decline'])) {
            throw new Exception('Received status is not final.');
        }

        if (empty($data['payment']) || empty($data['payment']['id'])) {
            throw new Exception('Missed "payment.id" field in callback data.');
        }
        $orderId = EcpOrderIdFormatter::removeOrderPrefix($data['payment']['id'], $this->configHelper->getPaymentPrefix());
        if (empty($data['operation']['request_id'])) {
            throw new Exception('Empty "operation.request_id" field in callback data.');
        }
        $refundExternalId = $data['operation']['request_id'];

        $description = null;
        if (!empty($data['operation']['message'])) {
            $description = $data['operation']['message'];
        }

        return new EcpRefundResult($orderId, $refundExternalId, $status, $description);
    }
}
