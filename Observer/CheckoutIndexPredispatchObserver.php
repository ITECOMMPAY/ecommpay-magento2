<?php

namespace Ecommpay\Payments\Observer;

use Ecommpay\Payments\Common\CallbackInfoManager;
use Ecommpay\Payments\Common\EcpConfigHelper;
use Ecommpay\Payments\Common\OrderPaymentManager;
use Exception;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class CheckoutIndexPredispatchObserver implements ObserverInterface
{
    private RequestInterface $request;
    private ResponseInterface $response;
    private UrlInterface $urlBuilder;
    private Session $checkoutSession;
    private EcpConfigHelper $configHelper;
    private OrderRepositoryInterface $orderRepository;
    private OrderPaymentManager $orderPaymentManager;
    private CallbackInfoManager $callbackInfoManager;
    private ManagerInterface $messageManager;

    public function __construct(
        RequestInterface         $request,
        ResponseInterface        $response,
        UrlInterface             $urlBuilder,
        Session                  $checkoutSession,
        EcpConfigHelper          $configHelper,
        OrderRepositoryInterface $orderRepository,
        OrderPaymentManager      $orderPaymentManager,
        CallbackInfoManager      $callbackInfoManager,
        ManagerInterface         $messageManager
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->urlBuilder = $urlBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->configHelper = $configHelper;
        $this->orderRepository = $orderRepository;
        $this->orderPaymentManager = $orderPaymentManager;
        $this->callbackInfoManager = $callbackInfoManager;
        $this->messageManager = $messageManager;
    }

    /**
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        if (!$order = $this->orderPaymentManager->getLastRealSessionEcommpayOrder()) {
            return;
        }

        if (!$this->orderPaymentManager->isRequiredRestoreQuote($order)) {
            return;
        }

        $isDeclined = $this->request->getParam(EcpConfigHelper::DECLINED, false);
        if ($isDeclined) {
            return;
        }

        $this->checkoutSession->restoreQuote();
    }
}
