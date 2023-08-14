<?php

namespace Ecommpay\Payments\Model\Config;

class Callback implements \Magento\Config\Model\Config\CommentInterface
{
    /**
     * @inheritdoc
     */
    public function getCommentText($elementValue)
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();

        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $store = $storeManager->getStore();

        $baseUrl = $store->getBaseUrl();
        $fullUrl = sprintf('%secommpay/endpayment/index', $baseUrl);

        $message = __('You should provide callback endpoint to ECommPay helpdesk. It is required to get information about payment\'s status:');
        return sprintf('%s %s', $message, $fullUrl);
    }
}
