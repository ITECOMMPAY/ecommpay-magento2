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
        var CHECK_VALIDATION_POST_MESSAGE = "{\"message\":\"epframe.embedded_mode.check_validation\",\"from_another_domain\":true}";
        var paymentPageParams = false;
        var clarificationRunning = false;
        var displayMode = null;

        $(document).keydown(function(event) {
            if (event.which === 13 && isEcommpayCardPayment() && (displayMode === null || displayMode === 'embedded')) {
                event.preventDefault();
                if (displayMode === 'embedded') {
                    window.postMessage(CHECK_VALIDATION_POST_MESSAGE);
                }
            }
        });

        var timer = setInterval(function(){
            if (window.checkoutConfig.ecommpay_settings.paymentPageHost != null) {
                clearInterval(timer);
                displayMode = window.checkoutConfig.ecommpay_settings.displayMode;
                if(displayMode !== 'embedded') {
                    return;
                }
                setTimeout(function(){
                    var url = urlBuilder.build('ecommpay/startpayment/embeddedform?action=create');
                    jQuery.ajax({
                        method: 'POST',
                        url: url,
                        dataType: 'json',
                        data: {
                            ajax: true
                        },
                        success: function(response) {
                            if (response.success) {
                                paymentPageParams = response.cardRedirectUrl;
                                loadEmbeddedIframe(paymentPageParams);
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

        function loadEmbeddedIframe(paymentPageParams) {
            var embeddedIframeDivOld = null;
            var intervalId = setInterval(function(){
                var embeddedIframeDiv = $("#ecommpay-iframe-embedded");
                if(embeddedIframeDiv.length === 1 && embeddedIframeDiv.is(embeddedIframeDivOld) && paymentPageParams) {
                    $('#ecommpay-loader-embedded').show();
                    loadIFrame(paymentPageParams, 'embedded');
                    clearInterval(intervalId);
                    $('input[name="payment[method]"]').change(function(){
                        if (isEcommpayCardPayment()) {
                            $(window).trigger('resize');
                        }
                    });
                }
                embeddedIframeDivOld = embeddedIframeDiv;
            }, 100);
        }

        function parsePostMessage(message) {
            try {
                var parsed = JSON.parse(message);
                if (!!parsed.message && !!parsed.data) {
                    return parsed;
                }
            } catch (e) {}
            return false;
        }

        function isEcommpayCardPayment() {
            return $('input[name="payment[method]"]:checked').val() === 'ecommpay_card';
        }

        function loadIFrame(paymentPageParams, type) {
            var link = document.createElement('a');

            paymentPageParams.onPaymentSuccess = function() {
                window.location.replace(config.merchant_success_url);
            };

            paymentPageParams.onPaymentFail = function() {
                window.location.replace(config.merchant_fail_url);
            };
            delete paymentPageParams.paymentPageUrl;
            EPayWidget.run(paymentPageParams);
        }

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
            var endpoint = urlBuilder.build('ecommpay/startpayment/index?method=card');
            var modePopup = (window.checkoutConfig.ecommpay_settings.displayMode === 'popup');
            var modeRedirect = (window.checkoutConfig.ecommpay_settings.displayMode === 'redirect');
            jQuery.ajax({
                method: 'POST',
                url: endpoint,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (modePopup) {
                            loadIFrame(response.paymentPageParams, 'popup');
                        }
                        if (modeRedirect) {
                            redirect(response.paymentPageParams);
                        }
                        return;
                    }
                    alert(response.error);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert(textStatus);
                }
            });
        }

        function processEmbeddedForm() {
            var endpoint = urlBuilder.build('ecommpay/startpayment/embeddedform?action=process&payment_id=' + paymentPageParams.payment_id);
            jQuery.ajax({
                method: 'POST',
                url: endpoint,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var message = {"message":"epframe.embedded_mode.submit"};

                        var billingFields = [
                            "billing_address", "billing_city", "billing_country", "billing_region", "billing_postal", "customer_first_name",
                            "customer_last_name", "customer_phone", "customer_zip", "customer_address", "customer_city",
                            "customer_country", "customer_email"
                        ];
                        var fieldsObject = {};
                        Object.keys(response.data).forEach(key => {
                            var name = key;
                            if (billingFields.includes(key)) {
                                name = "BillingInfo[" + name + "]";
                            }
                            fieldsObject[name] = response.data[key];
                            if (key === 'billing_country') {
                                fieldsObject["BillingInfo[country]"] = response.data[key];
                            }
                        });

                        message.fields = fieldsObject;
                        message.from_another_domain = true;
                        window.postMessage(JSON.stringify(message));
                    } else {
                        alert(response.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert(textStatus);
                }
            });
        }

        function showOverlayLoader() {
            $('#ecommpay-overlay-loader').show();
        }

        function hideOverlayLoader() {
            $('#ecommpay-overlay-loader').hide();
        }

        window.addEventListener("message", function (e){
            e.preventDefault();
            var d = parsePostMessage(e.data);
            switch (d.message) {
                case 'epframe.loaded':
                    if(displayMode === 'embedded') {
                        $('#ecommpay-iframe-embedded').show();
                        $('#ecommpay-loader-embedded').hide();
                        $(window).trigger('resize');
                    }

                    break;
                case 'epframe.payment.success':
                case 'epframe.card.verify.success':
                    break;
                case 'epframe.payment.fail':
                case 'epframe.card.verify.fail':
                    break;
                case 'epframe.embedded_mode.redirect_3ds_parent_page':
                    redirect3DS(d.data);
                    break;
                case 'epframe.payment.sent':
                    showOverlayLoader();
                    break;
                case 'epframe.show_clarification_page':
                    startClarification();
                    break;
                case 'epframe.enter_key_pressed':
                    if(displayMode === 'embedded') {
                        window.postMessage(CHECK_VALIDATION_POST_MESSAGE);
                    }
                    break;
            }
        }, false);

        function showErrors() {}
        function clearErrors() {}

        function redirect3DS(data) {
            var form = document.createElement('form');
            form.setAttribute('method', data.method);
            form.setAttribute('action', data.url);
            form.setAttribute('style', 'display:none;');
            form.setAttribute('name', '3dsForm');
            for (let k in data.body) {
                const input = document.createElement('input');
                input.name = k;
                input.value = data.body[k];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }

        function startClarification() {
            clarificationRunning = true;
            hideOverlayLoader();
        }

        function submitClarification() {
            showOverlayLoader();
            var message = {"message":"epframe.embedded_mode.submit"};
            message.fields = {};
            message.from_another_domain = true;
            window.postMessage(JSON.stringify(message));
        }

        return Component.extend({
            defaults: {
                template: 'Ecommpay_Payments/payment/ecommpay_card',
                redirectAfterPlaceOrder: false,
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
                var data = parsePostMessage(event.data);
                if (!!data && data.message === "epframe.embedded_mode.check_validation_response") {
                    if (!!data.data  && Object.keys(data.data).length > 0) {
                        var errors = [];
                        jQuery.each(data.data, function( key, value ) {
                            errors.push(value);
                        });
                        var errorsUnique = [... new Set(errors)]; //remove duplicated
                        console.log('validation errors', errorsUnique);
                        showErrors();
                    } else {
                        showOverlayLoader();
                        clearErrors();
                        if(clarificationRunning) {
                            submitClarification();
                        } else {
                            this.checkCartAmountBeforePlaceOrder();
                        }
                    }
                }
            },
            afterPlaceOrder: function () {
                if (displayMode === 'embedded') {
                    processEmbeddedForm();
                } else {
                    initPaymentPage();
                }
            },

            getDescription: function() {
                if (window.checkoutConfig.ecommpay_settings.displayMode !== 'embedded') {
                    return window.checkoutConfig.ecommpay_settings.descriptions.ecommpay_card;
                }
            },

            placeOrderOnClick: function() {
                if (displayMode === 'embedded') {
                    window.postMessage(CHECK_VALIDATION_POST_MESSAGE);
                } else {
                    this.placeOrder();
                }
            },

            checkCartAmountBeforePlaceOrder: function() {
                var component = this;
                var endpoint = urlBuilder.build('ecommpay/startpayment/embeddedform?action=checkCartAmount&amount=' + paymentPageParams.payment_amount);
                jQuery.ajax({
                    method: 'POST',
                    url: endpoint,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if(response.amountIsEqual) {
                                component.placeOrder();
                            } else {
                                window.location.reload();
                            }
                        } else {
                            alert(response.error);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert(textStatus);
                    }
                });
            }
        });
    }
);