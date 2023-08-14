<?php

namespace Ecommpay\Payments\Model;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var EcpConfigHelper */
    private $configHelper;

    public function __construct()
    {
        $this->configHelper = EcpConfigHelper::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $isTest = $this->configHelper->isTestMode();
        $displayMode = $this->configHelper->getDisplayMode();
        $description = null; //$this->scopeConfig->getValue(self::XML_DESCRIPTION, $storeScope);
        $paymentPageHost = $this->configHelper->getPPHost();
        $paymentPageProtocol = $this->configHelper->getProtocol();
        $merchantScriptIsLoaded = false;
        return [
            'ecommpay_settings' => compact(
                'isTest',
                'displayMode',
                'description',
                'paymentPageHost',
                'paymentPageProtocol',
                'merchantScriptIsLoaded'
            )
        ];
    }
}