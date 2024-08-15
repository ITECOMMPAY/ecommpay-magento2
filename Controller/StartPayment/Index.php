<?php

namespace Ecommpay\Payments\Controller\StartPayment;

use Ecommpay\Payments\Common\RequestBuilder;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;

class Index extends Action
{
    protected Session $checkoutSession;
    protected RequestBuilder $requestBuilder;
    protected Http $request;
    protected JsonFactory $resultJsonFactory;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        RequestBuilder $requestBuilder,
        Http $http,
        JsonFactory $jsonFactory
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
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->checkoutSession->getLastRealOrder();

        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), 'The customer opened the payment page. Waiting for the customer to make the payment');
        $order->save();

        $paymentPageParams = $this->requestBuilder->getPaymentPageParams($order);

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'paymentPageParams' => $paymentPageParams
        ]);
    }
}
