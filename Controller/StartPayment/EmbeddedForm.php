<?php

namespace Ecommpay\Payments\Controller\StartPayment;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Ecommpay\Payments\Common\RequestBuilder;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order;

class EmbeddedForm extends Action
{
    protected Session $checkoutSession;
    protected RequestBuilder $requestBuilder;
    protected Http $request;
    protected JsonFactory $resultJsonFactory;
    protected OrderPaymentManager $orderPaymentManager;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        RequestBuilder $requestBuilder,
        Http $http,
        JsonFactory $jsonFactory,
        OrderPaymentManager $orderPaymentManager
    ) {
        parent::__construct($context);
        $this->request = $http;
        $this->resultJsonFactory = $jsonFactory;
        $this->requestBuilder = $requestBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->orderPaymentManager = $orderPaymentManager;
    }

    /**
     * Initialize redirect to bank
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $action = ($this->request->getQuery('action', 'create'));
        switch ($action) {
            case 'create':
                $result = [
                    'success' => true,
                    'cardRedirectUrl' => $this->requestBuilder->getPaymentPageParamsForEmbeddedMode()
                ];
                break;
            case 'process':
                $result = $this->process();
                break;
            case 'checkCartAmount':
                $result = $this->checkCartAmount($this->request->getQuery('amount', '0'));
                break;
            default:
                $result = ['success' => false, 'error' => 'Invalid action'];
        }

        return $this->resultJsonFactory->create()->setData($result);
    }

    private function process(): array
    {
        $paymentId = $this->request->get('payment_id', null);
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$paymentId) {
            return ['success' => false, 'error' => 'Payment Id not given'];
        }
        if (!($order instanceof  Order)) {
            return ['success' => false, 'error' => 'Order not found in session'];
        }
        $this->orderPaymentManager->insert($order->getEntityId(), $paymentId);

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), 'The customer made a payment. Waiting for response from payment platform');
        $order->save();

        $billingInfo = $this->requestBuilder->getBillingDataFromOrder($order);
        $billingInfo = $this->requestBuilder->signer->unsetNullParams($billingInfo);

        return ['success' => true, 'data' => $billingInfo];
    }

    private function checkCartAmount($queryAmount): array
    {
        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
        $cartPaymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($grandTotal, $currencyCode);
        $cartPaymentAmount = (int)$cartPaymentAmount;
        $queryAmount = (int)$queryAmount;
        return ['success' => true, 'amountIsEqual' => ($cartPaymentAmount === $queryAmount)];
    }
}
