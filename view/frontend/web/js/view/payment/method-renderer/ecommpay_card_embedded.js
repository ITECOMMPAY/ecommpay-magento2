define([
    "jquery",
    "Magento_Checkout/js/view/payment/default",
    "Magento_Checkout/js/model/quote",
    "Magento_Ui/js/model/messageList",
    "Magento_Checkout/js/model/full-screen-loader",
    "Ecommpay_Payments/js/view/payment/helper",
  ], function (
    $,
    Component,
    quote,
    messageList,
    fullScreenLoader,
    helper
  ) {
    'use strict';
    const ECOMMPAY_IFRAME_EMBEDDED = "#ecommpay-iframe-embedded";
    const ECOMMPAY_LOADER_EMBEDDED = "#ecommpay-loader-embedded";
    const PAYMENT_METHOD = 'input[name="payment[method]"]';
    const SELECTED_PAYMENT_METHOD = 'input[name="payment[method]"]:checked'
    const ENTER_KEY_NUMBER = 13;
    const URL_START_PAYMENT = "/ecommpay/startpayment/embeddedform?action=create";
    const URL_PROCESS_PAYMENT = "/ecommpay/startpayment/embeddedform?action=process&payment_id=";
    const URL_RESTORE_CART = "/ecommpay/endpayment/restorecart";
    const URL_AMOUNT_VALIDATION = "/ecommpay/startpayment/embeddedform?action=checkCartAmount&amount=";
    const CHECK_VALIDATION_POST_MESSAGE =
      {"message": "epframe.embedded_mode.check_validation", "from_another_domain": true};
    const SUBMIT_POST_MESSAGE =
      {"message": "epframe.embedded_mode.submit", "from_another_domain": true};

    let clarificationRunning = false;
    let paymentPageParams = false;
    let cardFormIsValid = null;

    function isEcommpayCardPayment() {
      return $(SELECTED_PAYMENT_METHOD).val() === "ecommpay_card";
    }

    return Component.extend({
      initialize: function () {
        this._super();
        const waitForSettingsLoaded = setInterval(() => {
          if (window.checkoutConfig.ecommpay_settings.paymentPageHost != null) {
            clearInterval(waitForSettingsLoaded);
            setTimeout(this.startEmbeddedPayment.bind(this), 10);
          }
        }, 10);
        this.registerOnEnterKeyAction();
      },

      registerOnEnterKeyAction: function () {
        $(document).keydown((event) => {
          if (event.which === ENTER_KEY_NUMBER && isEcommpayCardPayment()) {
            event.preventDefault();
            this.placeOrderOnClick().then();
          }
        });
      },

      defaults: {
        template: "Ecommpay_Payments/payment/ecommpay_card",
        redirectAfterPlaceOrder: false,
      },

      getDescription: () => '',

      initObservable: function () {
        this._super();

        quote.totals.subscribe((totals) => {
          if (!paymentPageParams) return;
          const currentCartTotal = helper.priceMultiplyByCurrencyCode(
            totals.grand_total,
            totals.quote_currency_code
          );
          if (currentCartTotal !== paymentPageParams.payment_amount)
            this.startEmbeddedPayment();
        }, this);
        return this;
      },

      afterPlaceOrder: function () {
        fullScreenLoader.startLoader();
        jQuery.ajax({
          method: "POST",
          url: URL_PROCESS_PAYMENT + paymentPageParams.payment_id,
          success: (response) => {
            if (response.success) {
              const message = SUBMIT_POST_MESSAGE;

              const fieldsObject = {};
              Object.keys(response.data).forEach((key) => {
                fieldsObject[key] = response.data[key];
              });

              message.fields = fieldsObject;
              window.postMessage(JSON.stringify(message));
            } else {
              alert(response.error);
            }
          },
          error: function (jqXHR, textStatus, errorThrown) {
            alert(textStatus);
          },
        });
      },

      validate: function () {
        return true;
      },

      placeOrderOnClick: function () {
        return new Promise(resolve => {
          this._validateCardForm().then(result => {
            if (result) {
              if (clarificationRunning) {
                fullScreenLoader.startLoader();
                window.postMessage(JSON.stringify(SUBMIT_POST_MESSAGE));
              } else {
                this._validateCartAmount().then(result => {
                  if (result) {
                    this.placeOrder();
                  }
                  resolve(result);
                })
              }
            }
          })
        })
      },

      startEmbeddedPayment: function () {
        jQuery.ajax({
          method: "POST",
          url: URL_START_PAYMENT,
          success: (response) => {
            if (response.success) {
              paymentPageParams = response.cardRedirectUrl;
              this.loadEmbeddedIframe(paymentPageParams);
              return;
            }
            alert(response.error);
          },
          error: function (jqXHR, textStatus, errorThrown) {
            alert(textStatus);
            console.log(errorThrown);
          },
        });
      },

      loadEmbeddedIframe: function (paymentPageParams) {
        let embeddedIframeDivOld = null;
        const waitForLastRequest = setInterval(() => {
          const embeddedIframeDiv = $(ECOMMPAY_IFRAME_EMBEDDED);
          if (
            embeddedIframeDiv.length === 1 &&
            embeddedIframeDiv.is(embeddedIframeDivOld) &&
            paymentPageParams
          ) {
            $(ECOMMPAY_LOADER_EMBEDDED).show();
            this.runWidget(paymentPageParams);
            clearInterval(waitForLastRequest);
            $(PAYMENT_METHOD).change(() => $(window).trigger("resize"));
          }
          embeddedIframeDivOld = embeddedIframeDiv;
        }, 100);
      },

      runWidget: function (paymentPageParams) {
        delete paymentPageParams.paymentPageUrl;

        Object.assign(paymentPageParams, {
          onLoaded: this._onLoaded.bind(this),
          onPaymentFail: this._onPaymentFail.bind(this),
          onCardVerifyFail: this._onPaymentFail.bind(this),
          onEmbeddedModeRedirect3dsParentPage: this._onEmbeddedModeRedirect3dsParentPage.bind(this),
          onShowClarificationPage: this._onShowClarificationPage.bind(this),
          onEnterKeyPressed: this._onEnterKeyPressed.bind(this),
          onDestroy: this._onDestroy.bind(this),
          onEmbeddedModeCheckValidationResponse: this._onEmbeddedModeCheckValidationResponse.bind(this),
        });

        const waitForSdkLoaded = setInterval(() => {
          if (window.EPayWidget !== undefined) {
            clearInterval(waitForSdkLoaded);
          }
        }, 100);

        window.EPayWidget.run(paymentPageParams);
      },

      _validateCardForm: async function () {
        cardFormIsValid = null;
        window.postMessage(JSON.stringify(CHECK_VALIDATION_POST_MESSAGE))
        return new Promise(resolve => {
          function check() {
            if (cardFormIsValid !== null && cardFormIsValid !== undefined) {
              resolve(cardFormIsValid);
            } else {
              requestAnimationFrame(check);
            }
          }

          requestAnimationFrame(check);
        });
      },

      _validateCartAmount: async function () {
        return new Promise((resolve, reject) => {
          jQuery.ajax({
            method: 'POST',
            url: URL_AMOUNT_VALIDATION + paymentPageParams.payment_amount,
            dataType: 'json',
            success: (response) => {
              if (response.amountIsEqual === true) {
                resolve(true);
              } else {
                window.location.reload();
                resolve(false);
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              alert(textStatus);
            }
          })
        });
      },

      _onEmbeddedModeCheckValidationResponse: function (data) {
        if (!!data && Object.keys(data).length > 0) {
          let errors = [];
          jQuery.each(data, function (key, value) {
            errors.push(value);
          });
          console.log('Validation errors', [...new Set(errors)]);
          return;
        }
        cardFormIsValid = true;
      },

      _onLoaded: function () {
        $(ECOMMPAY_LOADER_EMBEDDED).hide();
        $(ECOMMPAY_IFRAME_EMBEDDED).show();
        $(window).trigger('resize');
      },

      _onPaymentFail: function () {
        jQuery.ajax({
          method: 'POST',
          url: URL_RESTORE_CART,
          success: () => {
            this.startEmbeddedPayment();
            fullScreenLoader.stopLoader();
            messageList.addErrorMessage({message: 'Payment was declined. You can try another payment method.'});
          },
          error: function (jqXHR, textStatus) {
            alert(textStatus);
          }
        });
      },

      _onShowClarificationPage: function () {
        clarificationRunning = true;
        fullScreenLoader.stopLoader();
      },

      _onEmbeddedModeRedirect3dsParentPage: function (data) {
        fullScreenLoader.startLoader();

        const form = $("<form>", {
          method: data.method,
          action: data.url,
          style: "display:none;",
          name: "3dsForm",
        });

        if (data.body && typeof data.body === "object") {
          $.each(data.body, function (key, value) {
            form.append(
              $("<input>", {
                type: "hidden",
                name: key,
                value: value,
              })
            );
          });
        }
        form.appendTo("body").submit();
      },

      _onEnterKeyPressed: function () {
        this.placeOrderOnClick().then()
      },

      _onDestroy: function () {
        window.location.replace(URL_RESTORE_CART)
      },
    });
  }
);
