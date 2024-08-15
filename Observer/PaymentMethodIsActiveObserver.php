<?php

namespace Ecommpay\Payments\Observer;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;

class PaymentMethodIsActiveObserver implements ObserverInterface
{   
    private EcpConfigHelper $configHelper;

    public function __construct(EcpConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $methodInstance = $event->getMethodInstance();
        $result = $event->getResult();

        if ($this->shouldHidePaymentMethod($methodInstance)) {
            $result->setData('is_available', false);
        }
    }

    private function shouldHidePaymentMethod(MethodInterface $methodInstance): bool
    {
        return $this->configHelper->getPaymentActionType() === EcpConfigHelper::AUTHORIZE_TYPE
            && !in_array($methodInstance->getCode(), EcpConfigHelper::AUTHORIZE_ONLY_PAYMENT_METHODS, true);
    }
}
