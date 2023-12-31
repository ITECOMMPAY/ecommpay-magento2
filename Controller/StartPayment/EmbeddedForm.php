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
        $this->request = $objectManager->get(Http::class);
        $this->resultJsonFactory = $objectManager->get(JsonFactory::class);
        $this->requestBuilder = new RequestBuilder($this->request);
        $this->checkoutSession = $checkoutSession;
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
        $orderPaymentManager = new OrderPaymentManager();
        $paymentId = $this->request->get('payment_id', null);
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$paymentId) {
            return ['success' => false, 'error' => 'Payment Id not given'];
        }
        if (!($order instanceof  Order)) {
            return ['success' => false, 'error' => 'Order not found in session'];
        }
        $orderPaymentManager->insert($order->getEntityId(), $paymentId);
        
        $order->setState(Order::STATE_PENDING_PAYMENT, true);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), 'Order pending payment by ecommpay');
        $order->save();
        
        $billingInfo = $this->requestBuilder->getBillingDataFromOrder($order);
        $billingInfo = $this->requestBuilder->signer->unsetNullParams($billingInfo);
        
        return ['success' => true, 'data' => $billingInfo];
    }

    private function checkCartAmount($queryAmount): array
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $checkoutSession = $objectManager->get(Session::class);
        $grandTotal = $checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $checkoutSession->getQuote()->getQuoteCurrencyCode();
        $cartPaymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($grandTotal, $currencyCode);
        $cartPaymentAmount = (int)$cartPaymentAmount;
        $queryAmount = (int)$queryAmount;
        return ['success' => true, 'amountIsEqual' => ($cartPaymentAmount === $queryAmount)];
    }
}
