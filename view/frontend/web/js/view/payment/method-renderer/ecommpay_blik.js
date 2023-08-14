define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url'
    ],
    function (
        $,
        Component,
        urlBuilder
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Ecommpay_Payments/payment/ecommpay_common',
                redirectAfterPlaceOrder: false
            },

            afterPlaceOrder: function () {
                var url = urlBuilder.build('ecommpay/startpayment/index?method=blik');
                console.log('Redirect after place order:', url);
                window.location.replace(url);
            },

            getDescription: function() {
                return window.checkoutConfig.ecommpay_settings.description;
            }
        });
    }
);