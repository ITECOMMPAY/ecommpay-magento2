<?php

namespace Ecommpay\Payments\Controller\EndPayment;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Ecommpay\Payments\Common\CallbackInfoManager;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class RestoreCart extends Action
{
    private Session $checkoutSession;
    private EcpConfigHelper $configHelper;
    private OrderManagementInterface $orderManagement;
    private OrderPaymentManager $orderPaymentManager;
    private OrderRepositoryInterface $orderRepository;
    private JsonFactory $resultJsonFactory;
    private CallbackInfoManager $callbackInfoManager;

    public function __construct(
        Context                  $context,
        Session                  $checkoutSession,
        EcpConfigHelper          $configHelper,
        JsonFactory              $resultJsonFactory,
        OrderManagementInterface $orderManagement,
        OrderPaymentManager      $orderPaymentManager,
        OrderRepositoryInterface $orderRepository,
        CallbackInfoManager      $callbackInfoManager
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
        $this->orderManagement = $orderManagement;
        $this->orderPaymentManager = $orderPaymentManager;
        $this->orderRepository = $orderRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->callbackInfoManager = $callbackInfoManager;
    }

    /**
     * Get redirect parameters
     *
     * @return Redirect | Json
     */
    public function execute()
    {
        if (!$order = $this->orderPaymentManager->getLastRealSessionEcommpayOrder()) {
            return $this->buildRedirectToHomePage();
        }

        if (!$this->orderPaymentManager->isRequiredRestoreQuote($order)) {
            return $this->buildRedirectToHomePage();
        }

        $this->deleteOrder($order);
        $this->checkoutSession->restoreQuote();

        if ($this->isEmbeddedModeRequested()) {
            return $this->addMessageForEmbeddedModeAndBuildResponse();
        }
        return $this->buildRedirectForNonEmbeddedMode();
    }

    private function deleteOrder(Order $order): void
    {
        $failedPaymentAction = $this->configHelper->getFailedPaymentAction();
        if ($failedPaymentAction !== EcpConfigHelper::FAILED_PAYMENT_ACTION_DELETE_ORDER) {
            return;
        }

        $this->orderPaymentManager->deleteOrderAndRelatedData($order->getId());
    }

    private function isEmbeddedModeRequested(): bool
    {
        $request = $this->getRequest();
        return $request->isXmlHttpRequest();
    }

    private function addMessageForEmbeddedModeAndBuildResponse(): Json
    {
        $message = __('Payment was declined. You can try another payment method.');
        $this->messageManager->addErrorMessage($message);
        return $this->resultJsonFactory->create()->setData(['result' => 'OK']);
    }

    private function buildRedirectForNonEmbeddedMode(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($this->configHelper->getReturnChekoutUrl(true));
        return $resultRedirect;
    }

    private function buildRedirectToHomePage(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('/');
        return $resultRedirect;
    }
}
