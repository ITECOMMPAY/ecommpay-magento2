<?php

namespace Ecommpay\Payments\Controller\StartPayment;

use Ecommpay\Payments\Common\RequestBuilder;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\Order;

class Index extends Action
{
    protected Session $checkoutSession;
    protected RequestBuilder $requestBuilder;
    protected Http $request;
    protected JsonFactory $resultJsonFactory;

    public function __construct(
        Context        $context,
        Session        $checkoutSession,
        RequestBuilder $requestBuilder,
        Http           $http,
        JsonFactory    $jsonFactory
    ) {
        parent::__construct($context);
        $this->request = $http;
        $this->resultJsonFactory = $jsonFactory;
        $this->requestBuilder = $requestBuilder;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Initialize redirect to bank
     *
     * @return ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), 'The customer opened the payment page. Waiting for the customer to make the payment');
        $order->save();

        $paymentPageParams = $this->requestBuilder->getPaymentPageParams($order);

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'paymentPageParams' => $paymentPageParams
        ]);
    }
}
