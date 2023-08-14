<?php

namespace Ecommpay\Payments\Common;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session;

class RequestBuilder
{
    private const SUCCESS_URL = 'checkout/onepage/success';
    private const FAIL_URL = 'checkout/onepage/failure';
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
    public $signer;

    protected $magentoVersion;

    /**
     * Signer constructor.
     */
    public function __construct(RequestInterface $request)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->request = $request;
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->checkoutSession = $objectManager->get(Session::class);
        $this->urlBuilder = $objectManager->get(UrlInterface::class);
        $this->configHelper = EcpConfigHelper::getInstance();
        $this->signer = new EcpSigner();
        $productMetadata = $objectManager->get(ProductMetadataInterface::class);
        $this->magentoVersion = $productMetadata->getVersion();
    }

    /**
     *
     * @return string */
    public function getPaymentPageParams(\Magento\Sales\Model\Order $order)
    {
        $orderPaymentManager = new OrderPaymentManager();
        $paymentId = uniqid(EcpConfigHelper::CMS_PREFIX);
        if ($this->configHelper->isTestMode()) {
            $paymentIdFormater = new EcpPaymentIdFormatter($this->request);
            $paymentId = $paymentIdFormater->addPaymentPrefix($paymentId, EcpConfigHelper::TEST_PREFIX);
        }
        $orderPaymentManager->insert($order->getEntityId(), $paymentId);
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

    /**
     *
     * @param Order $order
     * @param string $paymentId
     * @return array
     */
    protected function buildParams(\Magento\Sales\Model\Order $order, $paymentId)
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
            '_plugin_version' => EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion
        ];

        $optinalParams = $this->getBillingDataFromOrder($order);

        $optinalParams['customer_id'] = $order->getCustomerId();

        if ($this->configHelper->getPPLanguage() != 'default') {
            $optinalParams['language_code'] = $this->configHelper->getPPLanguage();
        }

        $additionalParams = $this->configHelper->getAdditionalParameters();
        if (!empty($additionalParams)) {
            $additionalData = [];
            parse_str($additionalParams, $additionalData);
            $optinalParams = array_merge($optinalParams, $additionalData);
        }
        $optinalParams = $this->signer->unsetNullParams($optinalParams);
        return array_merge($baseParams, $optinalParams);
    }

    public function getBillingDataFromOrder(\Magento\Sales\Model\Order $order) {
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
        ];
        if ($address !== null && $postCode !== null) {
            $result['avs_street_address'] = $address;
            $result['avs_post_code'] = $postCode;
        }
        return $result;
    }

    private function getReceiptData(\Magento\Sales\Model\Order $order)
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
        $methodParam = $this->request->getParam('method');
        if (!empty($methodParam)) {
            if ($methodParam === 'open_banking') {
                $urlData['force_payment_group'] = 'openbanking';
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
        }
        return $urlData;
    }

    public function getPaymentPageParamsForEmbeddedMode()
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
            'redirect_fail_url' => $this->urlBuilder->getUrl(self::FAIL_URL),
            'redirect_fail_enabled' => 2,
            'redirect_fail_mode' => 'parent_page',
            '_plugin_version' => EcpConfigHelper::PLUGIN_VERSION,
            '_magento_version' => $this->magentoVersion,
        ];

        if ($this->configHelper->getPPLanguage() != 'default') {
            $paymentPageParams['language_code'] = $this->configHelper->getPPLanguage();
        }

        if (!empty($this->checkoutSession->getQuote()->getCustomerId())) {
            $paymentPageParams['customer_id'] = $quote->getCustomerId();
        }

        $additionalParams = $this->configHelper->getAdditionalParameters();
        if (!empty($additionalParams)) {
            $additionalData = [];
            parse_str($additionalParams, $additionalData);
            $paymentPageParams = array_merge($paymentPageParams, $additionalData);
        }

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
