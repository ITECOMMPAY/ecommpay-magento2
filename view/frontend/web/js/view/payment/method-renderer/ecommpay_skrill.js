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

        function redirect(paymentPageParams) {
            var url = paymentPageParams.paymentPageUrl;
            delete paymentPageParams.paymentPageUrl;
            let form =
                $('<form>', {
                    method: 'post',
                    action: url,
                    style: {
                        display: 'none',
                    }
                });

            $.each(paymentPageParams, function (key, value) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }));
            });

            $(form).appendTo('body').submit();
        }

        function initPaymentPage() {
            var endpoint = urlBuilder.build('ecommpay/startpayment/index?method=skrill');
            jQuery.ajax({
                method: 'POST',
                url: endpoint,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        redirect(response.paymentPageParams);
                        return;
                    }
                    alert(response.error);
                    },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert(textStatus);
                }
            })
        }

        return Component.extend({
            defaults: {
                template: 'Ecommpay_Payments/payment/ecommpay_common',
                redirectAfterPlaceOrder: false
            },

            afterPlaceOrder: function () {
                initPaymentPage();
                },

            getDescription: function() {
                return window.checkoutConfig.ecommpay_settings.descriptions.ecommpay_skrill;
            }
        });
    }
    );
