<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;

class RequestBuilder
{
    private const SUCCESS_URL = 'checkout/onepage/success';
    private const FAIL_URL = 'ecommpay/endpayment/restorecart';
    private const RETURN_URL = 'ecommpay/endpayment/restorecart?cancelled=1';
    private const CARD_OPERATION_TYPE_SALE = 'sale';
    private const CARD_OPERATION_TYPE_AUTH = 'auth';

    public EcpSigner $signer;
    protected ScopeConfigInterface $scopeConfig;
    protected Session $checkoutSession;
    protected UrlInterface $urlBuilder;
    protected EcpConfigHelper $configHelper;
    protected RequestInterface $request;
    protected string $magentoVersion;
    protected OrderPaymentManager $orderPaymentManager;

    public function __construct(
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        Session $session,
        UrlInterface $urlBuilder,
        EcpSigner $signer,
        EcpConfigHelper $configHelper,
        ProductMetadataInterface $productMetadata,
        OrderPaymentManager $orderPaymentManager
    ) {
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $session;
        $this->urlBuilder = $urlBuilder;
        $this->configHelper = $configHelper;
        $this->signer = $signer;
        $this->magentoVersion = $productMetadata->getVersion();
        $this->orderPaymentManager = $orderPaymentManager;
    }

    public function getPaymentPageParams(Order $order): array
    {
        $paymentId = uniqid(EcpConfigHelper::CMS_PREFIX);
        if ($this->configHelper->isTestMode()) {
            $paymentIdFormater = new EcpPaymentIdFormatter($this->request);
            $paymentId = $paymentIdFormater->addPaymentPrefix($paymentId, EcpConfigHelper::TEST_PREFIX);
        }
        $this->orderPaymentManager->insert($order->getEntityId(), $paymentId);
        $paymentPageParams = $this->buildParams($order, $paymentId);
        $paymentPageParams = $this->appendPaymentMethod($paymentPageParams, $order);

        $paymentPageParams = $this->signer->unsetNullParams($paymentPageParams);

        $paymentPageParams['signature'] = $this->signer->getSignature($paymentPageParams);

        $paymentPageParams['paymentPageUrl'] = sprintf(
            '%s://%s/payment',
            $this->configHelper->getProtocol(),
            $this->configHelper->getPPHost()
        );
        return $paymentPageParams;
    }

    protected function buildParams(Order $order, string $paymentId): array
    {
        $currencyCode = $order->getOrderCurrencyCode();
        $paymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($order->getTotalDue(), $currencyCode);
        $baseParams = [
            'project_id' => $this->configHelper->getProjectId(),
            'payment_amount' => $paymentAmount,
            'payment_id' => $paymentId,
            'payment_currency' => $currencyCode,
            'interface_type' => json_encode($this->configHelper->getInterfaceTypeId()),
            'merchant_success_url' => $this->urlBuilder->getUrl(self::SUCCESS_URL),
            'merchant_success_enabled' => 2,
            'merchant_fail_url' => $this->urlBuilder->getUrl(self::FAIL_URL),
            'merchant_fail_enabled' => 2,
            'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            'merchant_return_url' => $this->urlBuilder->getUrl(self::RETURN_URL),
            '_plugin_version' => EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion,
        ];

        $optionalParams = $this->getBillingDataFromOrder($order);
        $optionalParams = $this->setCardOperationType($optionalParams);
        $optionalParams = $this->setAdditionalParams($optionalParams);
        $optionalParams = $this->setPPlanguage($optionalParams);
        $optionalParams['customer_id'] = $order->getCustomerId();

        $optionalParams = $this->signer->unsetNullParams($optionalParams);
        return array_merge($baseParams, $optionalParams);
    }

    public function getBillingDataFromOrder(Order $order): array
    {
        $billingAddress = $order->getBillingAddress();
        $address = implode(' ', $billingAddress->getStreet());
        $postCode = $billingAddress->getPostcode();
        $result = [
            'customer_email' => $billingAddress->getEmail(),
            'customer_first_name' => $billingAddress->getFirstname(),
            'customer_last_name' => $billingAddress->getLastname(),
            'customer_phone' => $billingAddress->getTelephone(),
            'billing_country' => $billingAddress->getCountryId(),
            'billing_region' => $order->getBillingAddress()->getRegion(),
            'billing_city' => $billingAddress->getCity(),
            'billing_address' => $address,
            'billing_postal' => $postCode,
            'payment_description' => 'Order ID = ' . $order->getIncrementId(),
        ];
        if ($address !== null && $postCode !== null) {
            $result['avs_street_address'] = $address;
            $result['avs_post_code'] = $postCode;
        }
        return $result;
    }

    private function setCardOperationType(array $paymentPageParams): array
    {
        $isAuthorizeType = $this->configHelper->getPaymentActionType() === EcpConfigHelper::AUTHORIZE_TYPE;
        $paymentPageParams['operation_type'] = $isAuthorizeType ? self::CARD_OPERATION_TYPE_AUTH : self::CARD_OPERATION_TYPE_SALE;
        return $paymentPageParams;
    }

    private function setAdditionalParams(array $paymentPageParams): array
    {
        $additionalParams = $this->configHelper->getAdditionalParameters();
        if (empty($additionalParams)) {
            return $paymentPageParams;
        }
        $additionalData = [];
        parse_str($additionalParams, $additionalData);
        return array_merge($paymentPageParams, $additionalData);
    }

    private function setPPlanguage(array $paymentPageParams): array
    {
        $ppLanguage = $this->configHelper->getPPLanguage();
        if ($ppLanguage !== EcpConfigHelper::PP_LANGUAGE_DEFAULT) {
            $paymentPageParams['language_code'] = $ppLanguage;
        }
        return $paymentPageParams;
    }

    private function appendPaymentMethod(array $urlData, Order $order): array
    {
        $forcePaymentMethods = [
            'card',
            'apple_pay_core',
            'google_pay_host',
            'sofort',
            'blik',
            'giropay',
            'ideal',
            'klarna',
            'paypal-wallet',
            'neteller',
            'skrill',
            'bancontact',
            'multibanco',
        ];
        $methodParam = $this->request->getParam('method');
        if (empty($methodParam)) {
            return $urlData;
        }

        if ($methodParam === 'open_banking') {
            $urlData['force_payment_group'] = 'openbanking';
        } elseif ($methodParam === 'paypal-paylater') {
            $urlData['force_payment_method'] = 'paypal-wallet';
            $urlData['payment_methods_options'] = "{\"submethod_code\": \"paylater\"}";
        } elseif (in_array($methodParam, $forcePaymentMethods)) {
            $urlData['force_payment_method'] = $methodParam;
        } elseif ($methodParam === 'more_methods') {
            $forceMethodForMoreMethods = $this->configHelper->getForceMethodForMoreMethods();
            if (!empty($forceMethodForMoreMethods)) {
                $urlData['force_payment_method'] = $forceMethodForMoreMethods;
            }
        }
        if ($methodParam === 'klarna') {
            $urlData['receipt_data'] = $this->getReceiptData($order);
            $countryId = $order->getBillingAddress()->getCountryId();
            if ($countryId) {
                $urlData['customer_country'] = $countryId;
            }
        }
        return $urlData;
    }

    private function getReceiptData(Order $order)
    {
        $items = $order->getItems();
        $currency = $order->getOrderCurrency();
        $total = $order->getGrandTotal() - $order->getShippingAmount();
        $total = EcpConfigHelper::priceMultiplyByCurrencyCode($total, $currency);
        $totalTax = $order->getBaseTaxAmount();
        $totalTax = EcpConfigHelper::priceMultiplyByCurrencyCode($totalTax, $currency);

        $receipt = $totalTax > 0
            ? [
                // Item positions.
                'positions' => $this->getPositions($items, $currency),
                // Total tax amount per payment.
                'total_tax_amount' => $totalTax,
                'common_tax' => round($totalTax * 100 / $total, 2),
            ]
            : [
                // Item positions.
                'positions' => $this->getPositions($items, $currency)
            ];
        return base64_encode(json_encode($receipt));
    }

    private function getPositions($items, $currency)
    {
        $positions = [];
        foreach ($items as $item) {
            $positions[] = $this->getReceiptPosition($item, $currency);
        }
        return $positions;
    }

    private function getReceiptPosition($item, $currency)
    {
        $quantity = abs(!($item->getQtyOrdered() === null) ? $item->getQtyOrdered() : 0);
        $total = abs(!($item->getRowTotal() === null) ? $item->getRowTotal() : 0);
        $total = EcpConfigHelper::priceMultiplyByCurrencyCode($total, $currency);
        $totalTax = abs(!($item->getTaxAmount() === null) ? $item->getTaxAmount() : 0);
        $totalTax = EcpConfigHelper::priceMultiplyByCurrencyCode($totalTax, $currency);
        $description = $item->getName();
        $taxPercentage = $item->getTaxPercent();

        $data = [
            'amount' => $total
        ];
        if ($quantity > 0) {
            // Quantity of the goods or services. Multiple of: 0.000001.
            $data['quantity'] = round($quantity, 6);
        }
        if (!($description === null) && (strlen($description) > 0)) {
            // Goods or services description. >= 1 characters<= 255 characters.
            $data['description'] = $description;
        }
        if ($totalTax > 0) {
            // Tax percentage for the position. Multiple of: 0.01.
            $data['tax'] = round($taxPercentage, 2);
            // Tax amount for the position.
            $data['tax_amount'] = $totalTax;
        }
        return $data;
    }

    public function getPaymentPageParamsForEmbeddedMode(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $paymentId = uniqid(EcpConfigHelper::CMS_PREFIX);
        if ($this->configHelper->isTestMode()) {
            $paymentIdFormater = new EcpPaymentIdFormatter($this->request);
            $paymentId = $paymentIdFormater->addPaymentPrefix($paymentId, EcpConfigHelper::TEST_PREFIX);
        }

        $grandTotal = $quote->getGrandTotal();
        $currencyCode = $quote->getQuoteCurrencyCode();
        $paymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($grandTotal, $currencyCode);
        $paymentPageParams = [
            'mode' => $paymentAmount > 0 ? 'purchase' : 'card_verify',
            'payment_amount' => $paymentAmount,
            'payment_currency' => $currencyCode,
            'project_id' => $this->configHelper->getProjectId(),
            'payment_id' => $paymentId,
            'force_payment_method' => 'card',
            'target_element' => 'ecommpay-iframe-embedded',
            'frame_mode' => 'iframe',
            'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            'interface_type' => json_encode($this->configHelper->getInterfaceTypeId()),
            'payment_methods_options' => "{\"additional_data\":{\"embedded_mode\":true}}",
            'redirect_success_url' => $this->urlBuilder->getUrl(self::SUCCESS_URL),
            'redirect_success_enabled' => 2,
            'redirect_success_mode' => 'parent_page',
            'merchant_fail_url' => $this->urlBuilder->getUrl(self::FAIL_URL),
            'merchant_fail_enabled' => 2,
            'merchant_fail_redirect_mode' => 'parent_page',
            '_plugin_version' => EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion,
        ];

        if ($customerId = $quote->getCustomerId()) {
            $paymentPageParams['customer_id'] = $customerId;
        }

        if ($billingAddress = $quote->getBillingAddress()) {
            if ($billingStreet = $billingAddress->getStreet()) {
                $paymentPageParams['avs_street_address'] = implode(' ', $billingStreet);
            }
            if ($billingPostcode = $billingAddress->getPostcode()) {
                $paymentPageParams['avs_post_code'] = $billingPostcode;
            }
        }

        $paymentPageParams = $this->setCardOperationType($paymentPageParams);
        $paymentPageParams = $this->setAdditionalParams($paymentPageParams);
        $paymentPageParams = $this->setPPlanguage($paymentPageParams);

        $paymentPageParams = $this->signer->unsetNullParams($paymentPageParams);

        $paymentPageParams['signature'] = $this->signer->getSignature($paymentPageParams, ["frame_mode"]);

        $paymentPageParams['paymentPageUrl'] = sprintf(
            '%s://%s/payment',
            $this->configHelper->getProtocol(),
            $this->configHelper->getPPHost()
        );

        return $paymentPageParams;
    }
}
