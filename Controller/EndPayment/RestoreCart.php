<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Ecommpay\Payments\Common\CallbackInfoManager;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Exception;
use Magento\Framework\Controller\Result\Redirect;

class RestoreCart extends Action
{
    private Session $checkoutSession;
    private EcpConfigHelper $configHelper;
    private OrderManagementInterface $orderManagement;
    private OrderPaymentManager $orderPaymentManager;
    private Registry $registry;
    private Order $order;
    private CallbackInfoManager $callbackInfoManager;

    private const CART_URL = 'checkout/cart';

    public function __construct(
        Context $context,
        Session $checkoutSession,
        EcpConfigHelper $configHelper,
        OrderManagementInterface $orderManagement,
        OrderPaymentManager $orderPaymentManager,
        Registry $registry,
        Order $order,
        CallbackInfoManager $callbackInfoManager
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
        $this->orderManagement = $orderManagement;
        $this->orderPaymentManager = $orderPaymentManager;
        $this->registry = $registry;
        $this->order = $order;
        $this->callbackInfoManager = $callbackInfoManager;
    }

    /**
     * @return Redirect
     * @throws Exception
     */
    public function execute(): Redirect
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();

        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($lastRealOrder->getPayment()) {
            $failedPaymentAction = $this->configHelper->getFailedPaymentAction();

            if ($failedPaymentAction === EcpConfigHelper::FAILED_PAYMENT_ACTION_DELETE_ORDER) {
                $this->deleteOrder($lastRealOrder->getId());
            }

            $this->checkoutSession->restoreQuote();

            if (!$isAjax) {
                $this->messageManager->addErrorMessage('Payment was declined. You can try another payment method.');
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath(self::CART_URL);
        return $resultRedirect;
    }

    /**
     * @param $orderId
     * @return void
     * @throws Exception
     */
    private function deleteOrder($orderId): void
    {
        $this->orderPaymentManager->deleteByOrderId($orderId);
        $this->callbackInfoManager->deleteByOrderId($orderId);
        $order = $this->order->load($orderId);
        $this->registry->register('isSecureArea', true);
        $order->delete();
        $this->registry->unregister('isSecureArea');
    }
}
