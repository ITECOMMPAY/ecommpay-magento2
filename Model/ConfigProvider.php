<?php

namespace Ecommpay\Payments\Model;

use Ecommpay\Payments\Common\EcpConfigHelper;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private EcpConfigHelper $configHelper;

    public function __construct(EcpConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        $pluginEnabled = $this->configHelper->getPluginEnabled();
        $isTest = $this->configHelper->isTestMode();
        $displayMode = $this->configHelper->getDisplayMode();
        $paymentPageHost = $this->configHelper->getPPHost();
        $paymentPageProtocol = $this->configHelper->getProtocol();
        $descriptions = $this->configHelper->getDescriptions();
        $merchantScriptIsLoaded = false;
        return [
            'ecommpay_settings' => compact(
                'pluginEnabled',
                'isTest',
                'displayMode',
                'descriptions',
                'paymentPageHost',
                'paymentPageProtocol',
                'merchantScriptIsLoaded'
            )
        ];
    }
}
