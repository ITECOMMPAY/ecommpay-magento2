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

        var timer = setInterval(function(){
            if (window.checkoutConfig.ecommpay_settings.paymentPageHost != null) {
                clearInterval(timer);
                if(window.checkoutConfig.ecommpay_settings.paymentPageHost.displayMode !== 'embedded') {
                    $('#ecommpay-loader-embedded').hide();
                    return;
                }
                setTimeout(function(){
                    var url = urlBuilder.build('ecommpay/startpayment/embeddedform');
                    jQuery.ajax({
                        method: 'POST',
                        url: url,
                        dataType: 'json',
                        data: {
                            ajax: true
                        },
                        success: function(response) {
                            console.log('embedded frame redirect url', response);
                            if (response.success) {
                                var redirectUrl = response.cardRedirectUrl;
                                showPopup(redirectUrl, 'embedded');
                                return;
                            }
                            alert(response.error);
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert(textStatus);
                            console.log(errorThrown);
                        }
                    })
                },10);
            }
        }, 10);
        function parseMessage(message) {
            try {
                var parsed = JSON.parse(message);
                if (!!parsed.message && !!parsed.data) {
                    return parsed;
                }
            } catch (e) {}
            return false;
        }
        function triggerPopup(url) {
            jQuery.ajax({
                method: 'POST',
                url: url,
                dataType: 'json',
                data: {
                    ajax: true
                },
                success: function(response) {
                    if (response.success) {
                        var redirectUrl = response.cardRedirectUrl;
                        showPopup(redirectUrl, 'popup');
                        return;
                    }
                    alert(response.error);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert(textStatus);
                }
            })
        }

        function showPopup(url, type) {
            var link = document.createElement('a');
            link.href = url;
            var params = link.search.replace(/^\?/, '');

            var config = parseParams(params);

            config.onPaymentSuccess = function() {
                window.location.replace(config.merchant_success_url);
            };

            config.onPaymentFail = function() {
                window.location.replace(config.merchant_fail_url);
            };

            console.log(config);
            EPayWidget.run(config);
            if (type === 'embedded') {
                $('#ecommpay-iframe-embedded').show();
                $('#ecommpay-loader-embedded').hide();
            }
        }

        function parseParams(str) {
            return str.split('&').reduce(function (params, param) {
                var paramSplit = param.split('=').map(function (value) {
                    return decodeURIComponent(value.replace('+', ' '));
                });
                params[paramSplit[0]] = paramSplit[1];
                return params;
            }, {});
        }


        return Component.extend({
            defaults: {
                template: 'Ecommpay_Payments/payment/ecommpay_card',
                redirectAfterPlaceOrder: false
            },
            initialize: function () {
                this._super();
                if (window.checkoutConfig.ecommpay_settings.displayMode !== 'embedded') {
                    $('#ecommpay-loader-embedded').hide();
                }
                this.onIFrameValidation = this.onIFrameValidation.bind(this);
                window.addEventListener("message", this.onIFrameValidation, false);
                return this;
            },

            onIFrameValidation: function(event) {
                var data = parseMessage(event.data);
                if (data.message === "epframe.embedded_mode.check_validation_response") {
                    if (!!data.data  && Object.keys(data.data).length > 0) {
                        var errors = [];
                        jQuery.each(data.data, function( key, value ) {
                            errors.push(value);
                        });
                        var errorsUnique = [... new Set(errors)]; //remove duplicated
                        console.log('validation errors', errorsUnique);
                    } else {
                        this.placeOrder();
                    }
                }
            },
            afterPlaceOrder: function () {
                var url = urlBuilder.build('ecommpay/startpayment/index?method=card');
                if (window.checkoutConfig.ecommpay_settings.displayMode === 'redirect') {
                    window.location.replace(url);
                } else if (window.checkoutConfig.ecommpay_settings.displayMode === 'popup') {
                    triggerPopup(url);
                }
            },

            getDescription: function() {
                return window.checkoutConfig.ecommpay_settings.description;
            },

            placeOrderOnClick: function() {
                if (window.checkoutConfig.ecommpay_settings.paymentPageHost.displayMode === 'embedded') {
                    window.postMessage("{\"message\":\"epframe.embedded_mode.check_validation\",\"from_another_domain\":true}");
                } else {
                    this.placeOrder();
                }
            },
        });
    }
);