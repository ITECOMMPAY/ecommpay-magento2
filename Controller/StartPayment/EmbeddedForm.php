<?php

namespace Ecommpay\Payments\Controller\StartPayment;

use Ecommpay\Payments\Common\RequestBuilder;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;

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
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $this->resultJsonFactory = $objectManager->get('\Magento\Framework\Controller\Result\JsonFactory');
        $this->requestBuilder = new RequestBuilder();
    }

    /**
     * Initialize redirect to bank
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $redirectUrl = $this->requestBuilder->getEmbeddedModeRedirectUrl();

        if ($this->request->isAjax()) {
            return $this->resultJsonFactory->create()->setData([
                'success' => true,
                'cardRedirectUrl' => $redirectUrl
            ]);
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($redirectUrl);
        return $resultRedirect;
    }
}