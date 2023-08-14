<?php

namespace Ecommpay\Payments\Common;

class RequestBuilder
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var EcpConfigHelper
     */
    protected $configHelper;

    /**
     * @var EcpSigner
     */
    protected $signer;

    protected $magentoVersion;

    /**
     * Signer constructor.
     */
    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $this->checkoutSession = $objectManager->get('\Magento\Checkout\Model\Session');
        $this->urlBuilder = $objectManager->get('Magento\Framework\UrlInterface');
        $this->configHelper = EcpConfigHelper::getInstance();
        $this->signer = new EcpSigner();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $this->magentoVersion = $productMetadata->getVersion();
    }

    /**
     * @return string
     */
    public function getOrderRedirectUrl(\Magento\Sales\Model\Order $order)
    {
        $successUrl = $this->urlBuilder->getUrl('checkout/onepage/success');
        $failUrl = $this->urlBuilder->getUrl('checkout/onepage/failure');
        $merchantCallbackUrl = $this->configHelper->getMerchantCallbackUrl();
        $urlData = $this->createUrlData($order, $successUrl, $failUrl, $merchantCallbackUrl);
        $urlData = $this->appendPaymentMethod($urlData, $order);

        $urlData['signature'] = $this->signer->getSignature($urlData);
        $urlArgs = http_build_query($urlData, '', '&');

        return sprintf(
            '%s://%s/payment?%s',
            $this->configHelper->getProtocol(),
            $this->configHelper->getPPHost(),
            $urlArgs
        );
    }

    /**
     * @param array $config
     * @param $successUrl
     * @param $failUrl
     * @return array
     */
    protected function createUrlData(
        \Magento\Sales\Model\Order $order,
                                   $successUrl,
                                   $failUrl,
                                   $merchantCallbackUrl
    )
    {
        $payment_id = $order->getId();
        if ($this->configHelper->isTestMode()) {
            $payment_id = EcpOrderIdFormatter::addOrderPrefix($payment_id, EcpConfigHelper::CMS_PREFIX);
        }

        $currencyCode = $order->getOrderCurrencyCode();
        $paymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($order->getTotalDue(), $currencyCode);
        $result = [
            'project_id' => $this->configHelper->getProjectId(),
            'payment_amount' => $paymentAmount,
            'payment_id' => $payment_id,
            'payment_currency' => $currencyCode,
            'merchant_success_url' => $successUrl,
            'merchant_success_enabled' => 2,
            'merchant_fail_url' => $failUrl,
            'merchant_fail_enabled' => 2,
            'merchant_callback_url' => $merchantCallbackUrl,
            'interface_type' => json_encode($this->configHelper->getInterfaceTypeId()),
            'customer_email' => $order->getBillingAddress()->getEmail(),
            'customer_first_name' => $order->getBillingAddress()->getFirstname(),
            'customer_last_name' => $order->getBillingAddress()->getLastname(),
            'customer_phone' => $order->getBillingAddress()->getTelephone(),
            'billing_city' => $order->getBillingAddress()->getCity(),
            'billing_address' => implode(' ', $order->getBillingAddress()->getStreet()),
            'avs_street_address' => implode(' ', $order->getBillingAddress()->getStreet()),
            '_plugin_version' => EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion
        ];
        if ($this->configHelper->getPPLanguage() != 'default') {
            $result['language_code'] = $this->configHelper->getPPLanguage();
        }
        if ($order->getBillingAddress()->getPostcode()) {
            $result['avs_post_code'] = $order->getBillingAddress()->getPostcode();
            $result['billing_postal'] = $order->getBillingAddress()->getPostcode();
        }
        if ($order->getBillingAddress()->getCountryId()) {
            $result['billing_country'] = $order->getBillingAddress()->getCountryId();
        }
        if ($order->getBillingAddress()->getRegion()){
            $result['billing_region'] = $order->getBillingAddress()->getRegion();
        }
        if ($order->getCustomerId()) {
            $result['customer_id'] = $order->getCustomerId();
        }
        $additionalParams = $this->configHelper->getAdditionalParameters();
        if (!empty($additionalParams)) {
                $additionalData = [];
                parse_str($additionalParams, $additionalData);
                $result = array_merge($result, $additionalData);
        }

        return $result;
    }

    private function get_receipt_data(\Magento\Sales\Model\Order $order)
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
                'positions' => $this->get_positions($items, $currency),
                // Total tax amount per payment.
                'total_tax_amount' => $totalTax,
                'common_tax' => round($totalTax * 100 / $total, 2),
            ]
            : [
                // Item positions.
                'positions' => $this->get_positions($items, $currency)
            ];
        return base64_encode(json_encode($receipt));
    }

    private function get_positions($items, $currency)
    {
        $positions = [];
        foreach ($items as $item) {
            $positions[] = $this->get_receipt_position($item, $currency);
        }
        return $positions;
    }

    private function get_receipt_position($item, $currency)
    {
        $quantity = abs(!is_null($item->getQtyOrdered()) ? $item->getQtyOrdered() : 0);
        $total = abs(!is_null($item->getRowTotal()) ? $item->getRowTotal() : 0);
        $total = EcpConfigHelper::priceMultiplyByCurrencyCode($total, $currency);
        $totalTax = abs(!is_null($item->getTaxAmount()) ? $item->getTaxAmount() : 0);
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
        if (!is_null($description) && (strlen($description) > 0)) {
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

    private function appendPaymentMethod($urlData, $order)
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
            'paypal-wallet'
        ];
        if (!empty($_GET['method'])) {
            if ($_GET['method'] === 'open_banking') {
                $urlData['force_payment_group'] = 'openbanking';
            } else if (in_array($_GET['method'], $forcePaymentMethods)) {
                $urlData['force_payment_method'] = $_GET['method'];
            } else if ($_GET['method'] === 'more_methods') {
                $forceMethodForMoreMethods = $this->configHelper->getForceMethodForMoreMethods();
                if (!empty($forceMethodForMoreMethods)) {
                    $urlData['force_payment_method'] = $forceMethodForMoreMethods;
                }
            }
            if ($_GET['method'] === 'klarna') {
                $urlData['receipt_data'] = $this->get_receipt_data($order);
                $countryId = $order->getBillingAddress()->getCountryId();
                if ($countryId) {
                    $urlData['customer_country'] = $countryId;
                }
            }
        }
        return $urlData;
    }

    public function getEmbeddedModeRedirectUrl()
    {
        $grandTotal = $this->checkoutSession->getQuote()->getGrandTotal();
        $currencyCode = $this->checkoutSession->getQuote()->getQuoteCurrencyCode();
        $paymentAmount = EcpConfigHelper::priceMultiplyByCurrencyCode($grandTotal, $currencyCode);
        $urlData = [
            'mode' => $paymentAmount > 0 ? 'purchase' : 'card_verify',
            'payment_amount' => $paymentAmount,
            'payment_currency' => $currencyCode,
            'project_id' => $this->configHelper->getProjectId(),
            'payment_id' => uniqid('mag_'),
            'force_payment_method' => 'card',
            'target_element' => 'ecommpay-iframe-embedded',
            'frame_mode' => 'iframe',
            'merchant_callback_url' => $this->configHelper->getMerchantCallbackUrl(),
            'interface_type' => json_encode($this->configHelper->getInterfaceTypeId()),
            'payment_methods_options' => "{\"additional_data\":{\"embedded_mode\":true}}",
            'customer_id' => $this->checkoutSession->getQuote()->getCustomerEmail(),
            'merchant_success_enabled' => 2,
            'merchant_fail_enabled' => 2,
            'language_code' => $this->configHelper->getPPLanguage(),
            'customer_email' => $this->checkoutSession->getQuote()->getBillingAddress()->getEmail(),
            'customer_first_name' => $this->checkoutSession->getQuote()->getBillingAddress()->getFirstname(),
            'customer_last_name' => $this->checkoutSession->getQuote()->getBillingAddress()->getLastname(),
            'billing_country' => $this->checkoutSession->getQuote()->getBillingAddress()->getCountry(),
            'billing_postal' => $this->checkoutSession->getQuote()->getBillingAddress()->getPostcode(),
            'billing_city' => $this->checkoutSession->getQuote()->getBillingAddress()->getCity(),
            'billing_address' => implode(' ', $this->checkoutSession->getQuote()->getBillingAddress()->getStreet()),
            'avs_post_code' => $this->checkoutSession->getQuote()->getBillingAddress()->getPostcode(),
            'avs_street_address' => implode(' ', $this->checkoutSession->getQuote()->getBillingAddress()->getStreet()),
            '_plugin_version', EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion,
        ];

        $additionalParams = $this->configHelper->getAdditionalParameters();
        if (!empty($additionalParams)) {
            $additionalData = [];
            parse_str($additionalParams, $additionalData);
            $urlData = array_merge($urlData, $additionalData);
        }

        $urlData['signature'] = $this->signer->getSignature($urlData);
        $urlArgs = http_build_query($urlData, '', '&');

        return sprintf(
            '%s://%s/payment?%s',
            $this->configHelper->getProtocol(),
            $this->configHelper->getPPHost(),
            $urlArgs
        );
    }
}