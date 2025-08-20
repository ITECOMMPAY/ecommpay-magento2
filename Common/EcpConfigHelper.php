<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class EcpConfigHelper
{
    public const PLUGIN_VERSION = '2.2.1';
    public const AUTHORIZE_TYPE = 'authorize';
    public const AUTHORIZE_AND_CAPTURE_TYPE = 'authorize_capture';
    public const TEST_PREFIX = 'test_';
    public const CMS_PREFIX = 'mag_';
    public const PP_LANGUAGE_DEFAULT = 'default';
    public const DECLINED = 'declined';

    private const DEFAULT_THEME_CHECKOUT_PATH = 'checkout/';
    private const PAYMENT_FRAGMENT = 'payment';
    private const ECOMMPAY_GATE_PROTOCOL = 'https';
    private const ECOMMPAY_GATE_HOST = 'api.ecommpay.com';
    private const GATE_CAPTURE_ENDPOINT_FORMAT = '%s://%s/v2/payment/%s/capture';
    private const GATE_CANCEL_ENDPOINT_FORMAT = '%s://%s/v2/payment/%s/cancel';
    private const GATE_REFUND_ENDPOINT_FORMAT = '%s://%s/v2/payment/%s/refund';
    private const ECOMMPAY_PP_HOST = 'paymentpage.ecommpay.com';
    private const TEST_PROJECT_ID = 112;
    private const TEST_SECRET_KEY = 'kHRhsQHHhOUHeD+rt4kgH7OZiwE=';
    private const INTERFACE_TYPE_ID = 13;
    private const CONFIG_PATH_ENABLE_PLUGIN = 'payment/ecommpay_general/enable_plugin';
    private const CONFIG_PATH_IS_TEST = 'payment/ecommpay_general/testmode';
    private const CONFIG_PATH_SALT = 'payment/ecommpay_general/salt';
    private const CONFIG_PATH_PROJECT_ID = 'payment/ecommpay_general/project_id';
    private const CONFIG_PATH_DISPLAY_MODE = 'payment/ecommpay_card/display_mode';
    private const CONFIG_PATH_PP_LANGUAGE = 'payment/ecommpay_general/pp_language';
    private const CONFIG_PATH_ADDITIONAL_PARAMETERS = 'payment/ecommpay_general/additional_parameters';
    private const CONFIG_PATH_FORCE_METHOD_FOR_MORE_METHODS = 'payment/ecommpay_more_methods/force_payment_method';
    private const CONFIG_PATH_PAYMENT_ACTION_TYPE = 'payment/ecommpay_general/payment_action_type';
    private const CONFIG_PATH_FAILED_PAYMENT_ACTION = 'payment/ecommpay_general/failed_payment_action';

    public const AUTHORIZE_ONLY_PAYMENT_METHODS = [
        'ecommpay_card',
        'ecommpay_applepay',
        'ecommpay_googlepay',
    ];

    public const
        FAILED_PAYMENT_ACTION_CANCEL_ORDER = 'cancel_order',
        FAILED_PAYMENT_ACTION_DELETE_ORDER = 'delete_order',
        FAILED_PAYMENT_ACTION_DO_NOTHING = 'do_nothing';

    public const ACTIONS_TO_CANCEL_ORDER = [
        self::FAILED_PAYMENT_ACTION_CANCEL_ORDER,
        self::FAILED_PAYMENT_ACTION_DELETE_ORDER,
    ];

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;
    private string $storeScope;
    private StoreManagerInterface $storeManagerInterface;
    private UrlInterface $urlBuilder;

    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManagerInterface,
        EncryptorInterface $encryptorInterface,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfigInterface;
        $this->encryptor = $encryptorInterface;
        $this->storeScope = ScopeInterface::SCOPE_STORE;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->urlBuilder = $urlBuilder;
    }

    public static function priceMultiplyByCurrencyCode($price, $currencyCode): int
    {
        $non_decimal_currencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG',
            'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        if (!in_array($currencyCode, $non_decimal_currencies, true)) {
            return (int)round($price * 100);
        }
        return (int)round($price);
    }

    public function getPluginEnabled(): bool
    {
        return (bool)($this->scopeConfig->getValue(self::CONFIG_PATH_ENABLE_PLUGIN, $this->storeScope));
    }

    public function getGateCancelEndpoint($endpoint): string
    {
        $protocol = $this->getProtocol();
        $host = $this->getGateApiHost();
        return sprintf(self::GATE_CANCEL_ENDPOINT_FORMAT, $protocol, $host, $endpoint);
    }

    public function getProtocol(): string
    {
        return getenv('ECOMMPAY_GATE_PROTOCOL') ?: EcpConfigHelper::ECOMMPAY_GATE_PROTOCOL;
    }

    public function getGateApiHost(): string
    {
        $gateHostFromEnv = getenv('ECOMMPAY_GATE_HOST');
        return is_string($gateHostFromEnv) ? $gateHostFromEnv : EcpConfigHelper::ECOMMPAY_GATE_HOST;
    }

    public function getGateCaptureEndpoint($endpoint): string
    {
        $protocol = $this->getProtocol();
        $host = $this->getGateApiHost();
        return sprintf(self::GATE_CAPTURE_ENDPOINT_FORMAT, $protocol, $host, $endpoint);
    }

    public function getGateRefundEndpoint($endpoint): string
    {
        $protocol = $this->getProtocol();
        $host = $this->getGateApiHost();
        return sprintf(self::GATE_REFUND_ENDPOINT_FORMAT, $protocol, $host, $endpoint);
    }

    public function getSecretKeyDecrypted()
    {
        if ($this->isTestMode()) {
            return self::TEST_SECRET_KEY;
        }
        $saltEncrypted = $this->scopeConfig->getValue(self::CONFIG_PATH_SALT, $this->storeScope);
        return $this->encryptor->decrypt($saltEncrypted);
    }

    public function isTestMode(): bool
    {
        return (bool)($this->scopeConfig->getValue(self::CONFIG_PATH_IS_TEST, $this->storeScope));
    }

    public function getProjectId(): int
    {
        if ($this->isTestMode()) {
            return self::TEST_PROJECT_ID;
        }
        return (int)($this->scopeConfig->getValue(self::CONFIG_PATH_PROJECT_ID, $this->storeScope));
    }

    public function getDisplayMode()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_DISPLAY_MODE, $this->storeScope);
    }

    public function getPaymentActionType()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_PAYMENT_ACTION_TYPE, $this->storeScope);
    }

    public function getFailedPaymentAction()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_FAILED_PAYMENT_ACTION, $this->storeScope);
    }

    public function getPPHost(): string
    {
        $PPHostFromEnv = getenv('PAYMENTPAGE_HOST');
        return is_string($PPHostFromEnv) ? $PPHostFromEnv : EcpConfigHelper::ECOMMPAY_PP_HOST;
    }

    public function getMerchantCallbackUrl(): string
    {
        if ($debugCallbackUrl = getenv('ECOMMPAY_CALLBACK_URL')) {
            return $debugCallbackUrl;
        }
        $store = $this->storeManagerInterface->getStore();
        $baseUrl = $store->getBaseUrl();
        if ($this->getProtocol() === "http") {
            $baseUrl = str_replace("https", "http", $baseUrl);
        }
        return sprintf('%secommpay/endpayment/index', $baseUrl);
    }

    public function getInterfaceTypeId(): array
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

    public function getDescriptions(): array
    {
        $result = [];
        $methods = [
            'ecommpay_card',
            'ecommpay_applepay',
            'ecommpay_googlepay',
            'ecommpay_open_banking',
            'ecommpay_paypal',
            'ecommpay_paypal_paylater',
            'ecommpay_sofort',
            'ecommpay_ideal',
            'ecommpay_klarna',
            'ecommpay_blik',
            'ecommpay_giropay',
            'ecommpay_neteller',
            'ecommpay_skrill',
            'ecommpay_bancontact',
            'ecommpay_multibanco',
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

    public function getMethodEndPoint(string $code): ?string
    {
        return $this->scopeConfig->getValue('payment/' . $code . '/methodEndpoint', $this->storeScope);
    }

    public function getReturnChekoutUrl(bool $isDecliend = false): string
    {
        $redirectParams = ['_fragment' => self::PAYMENT_FRAGMENT];
        if ($isDecliend) {
            $redirectParams['_query'] = [self::DECLINED => 1];
        }
        return $this->urlBuilder->getUrl(self::DEFAULT_THEME_CHECKOUT_PATH, $redirectParams);
    }
}
