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
    /** @var Session */
    protected $checkoutSession;

    /** @var RequestBuilder */
    protected $requestBuilder;

    /** @var Http */
    protected $request;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /**
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->checkoutSession = $checkoutSession;
        $this->request = $objectManager->get(Http::class);
        $this->resultJsonFactory = $objectManager->get(JsonFactory::class);
        $this->requestBuilder = new RequestBuilder($this->request);
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

        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, true);
        $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), 'Order pending payment by ecommpay');
        $order->save();

        $paymentPageParams = $this->requestBuilder->getPaymentPageParams($order);

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'paymentPageParams' => $paymentPageParams
        ]);
    }
}
