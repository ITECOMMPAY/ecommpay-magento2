<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class EcpConfigHelper
{
    const PLUGIN_VERSION = '1.1.0';
    const ECOMMPAY_GATE_PROTOCOL = 'https';
    const ECOMMPAY_GATE_HOST = 'api.ecommpay.com';
    const GATE_REFUND_ENDPOINT_FORMAT = '%s://%s/v2/payment/%s/refund';
    const ECOMMPAY_PP_HOST = 'paymentpage.ecommpay.com';
    const TEST_PROJECT_ID = 112;
    const TEST_SECRET_KEY = 'kHRhsQHHhOUHeD+rt4kgH7OZiwE=';
    const TEST_PREFIX = 'test_';
    const CMS_PREFIX = 'mag_';
    const INTERFACE_TYPE_ID = 13;

    const CONFIG_PATH_ENABLE_PLUGIN = 'payment/ecommpay_general/enable_plugin';
    const CONFIG_PATH_IS_TEST = 'payment/ecommpay_general/testmode';
    const CONFIG_PATH_SALT = 'payment/ecommpay_general/salt';
    const CONFIG_PATH_PROJECT_ID = 'payment/ecommpay_general/project_id';
    const CONFIG_PATH_DISPLAY_MODE = 'payment/ecommpay_card/display_mode';
    const CONFIG_PATH_PP_LANGUAGE = 'payment/ecommpay_general/pp_language';
    const CONFIG_PATH_ADDITIONAL_PARAMETERS = 'payment/ecommpay_general/additional_parameters';
    const CONFIG_PATH_FORCE_METHOD_FOR_MORE_METHODS = 'payment/ecommpay_more_methods/force_payment_method';

    private static $instance;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var EncryptorInterface */
    private $encryptor;

    /** @var string */
    private $storeScope;

    /** @var bool | null */
    private $isTestMode;

    /** @return EcpConfigHelper */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->encryptor = $objectManager->get(EncryptorInterface::class);
        $this->storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    }

    public function getPluginEnabled()
    {
        return (bool)($this->scopeConfig->getValue(self::CONFIG_PATH_ENABLE_PLUGIN, $this->storeScope));
    }

    public function getGateRefundEndpoint($endpoint)
    {
        $protocol = $this->getProtocol();
        $host = $this->getGateApiHost();
        return  sprintf(self::GATE_REFUND_ENDPOINT_FORMAT, $protocol, $host, $endpoint);
    }

    public function isTestMode()
    {
        if(is_null($this->isTestMode)) {
            $this->isTestMode = (bool)($this->scopeConfig->getValue(self::CONFIG_PATH_IS_TEST, $this->storeScope));
        }
        return $this->isTestMode;
    }

    public function getSecretKeyDecrypted()
    {
        if($this->isTestMode()) {
            return self::TEST_SECRET_KEY;
        }
        $saltEncrypted = $this->scopeConfig->getValue(self::CONFIG_PATH_SALT, $this->storeScope);
        return $this->encryptor->decrypt($saltEncrypted);
    }

    public function getProjectId()
    {
        if ($this->isTestMode()) {
            return self::TEST_PROJECT_ID;
        } else {
            return intval($this->scopeConfig->getValue(self::CONFIG_PATH_PROJECT_ID, $this->storeScope));
        }
    }

    public function getDisplayMode()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_DISPLAY_MODE, $this->storeScope);
    }

    public function getPPHost()
    {
        $PPHostFromEnv = getenv('PAYMENTPAGE_HOST');
        return is_string($PPHostFromEnv) ? $PPHostFromEnv : EcpConfigHelper::ECOMMPAY_PP_HOST;
    }

    public function getGateApiHost()
    {
        $gateHostFromEnv = getenv('ECOMMPAY_GATE_HOST');
        return is_string($gateHostFromEnv) ? $gateHostFromEnv : EcpConfigHelper::ECOMMPAY_GATE_HOST;
    }

    public function getProtocol()
    {
        $protocolFromEnv = getenv('ECOMMPAY_GATE_PROTOCOL');
        return is_string($protocolFromEnv) ? $protocolFromEnv : EcpConfigHelper::ECOMMPAY_GATE_PROTOCOL;
    }

    public function getMerchantCallbackUrl()
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $store = $storeManager->getStore();
        $baseUrl = $store->getBaseUrl();
        if ($this->getProtocol() === "http"){
            $baseUrl = str_replace("https", "http", $baseUrl);
        }
        return sprintf('%secommpay/endpayment/index', $baseUrl);
    }

    public function getInterfaceTypeId()
    {
        return [
            'id' => self::INTERFACE_TYPE_ID
        ];
    }

    public function getPPLanguage()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_PP_LANGUAGE, $this->storeScope);
    }

    public function getForceMethodForMoreMethods()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_FORCE_METHOD_FOR_MORE_METHODS, $this->storeScope);
    }

    public function getAdditionalParameters()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_ADDITIONAL_PARAMETERS, $this->storeScope);
    }

    public function getDescriptions()
    {
        $result = [];
        $methods = [
            'ecommpay_card',
            'ecommpay_applepay',
            'ecommpay_googlepay',
            'ecommpay_open_banking',
            'ecommpay_paypal',
            'ecommpay_sofort',
            'ecommpay_ideal',
            'ecommpay_klarna',
            'ecommpay_blik',
            'ecommpay_giropay',
            'ecommpay_more_methods'
        ];
        foreach ($methods as $method) {
            $description = null;
            $showDescriptionConfigPath = 'payment/' . $method . '/show_description';
            $descriptionConfigPath = 'payment/' . $method . '/description';
            $showDescription = $this->scopeConfig->getValue($showDescriptionConfigPath, $this->storeScope);
            if ($showDescription) {
                $description = $this->scopeConfig->getValue($descriptionConfigPath, $this->storeScope);
            }
            $result[$method] = $description;
        }
        return $result;
    }

    public static function priceMultiplyByCurrencyCode($price, $currencyCode)
    {
        $non_decimal_currencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        if(!in_array($currencyCode, $non_decimal_currencies)) {
            $price = (int) round($price * 100);
        } else {
            $price = (int) $price;
        }
        return $price;
    }
}