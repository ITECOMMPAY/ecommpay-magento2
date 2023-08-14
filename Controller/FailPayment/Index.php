<?php

namespace Ecommpay\Payments\Controller\FailPayment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;


class Index extends Action
{
    /**
     * @var PageFactory
     */
    protected $pageFactory;

    /**
     * @param Context $context
     * @param CreateHostedCheckout $hostedCheckout
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory
    )
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->getLayout()->initMessages();
        $resultPage->getLayout()->getBlock('failpayment')->setOrderMessage(__('Your payment failed to be processed by ecommpay'));
        return $resultPage;
    }
}