define([
  "jquery",
  "Magento_Checkout/js/view/payment/default"
], function (
  $,
  Component,
) {
  "use strict";
  const URL_START_PAYMENT = "/ecommpay/startpayment/index?method=card";
  const URL_RESTORE_CART = "/checkout/";
  const DISPLAY_MODE = window.checkoutConfig.ecommpay_settings.displayMode;
  const POPUP = "popup";
  const REDIRECT = "redirect";

  let paymentPageUrl = false;

  return Component.extend({
    defaults: {
      template: "Ecommpay_Payments/payment/ecommpay_card",
      redirectAfterPlaceOrder: false,
    },

    getDescription: () => window.checkoutConfig.ecommpay_settings.descriptions.ecommpay_card,

    placeOrderOnClick: function () {
      this.placeOrder()
    },

    afterPlaceOrder: function () {
      jQuery.ajax({
        method: "POST",
        url: URL_START_PAYMENT,
        dataType: 'json',
        success: (response) => {
          if (response.success) {
            paymentPageUrl = response.paymentPageParams.paymentPageUrl;
            delete response.paymentPageParams.paymentPageUrl;
            if (DISPLAY_MODE === POPUP) {
              this.loadPopup(response.paymentPageParams);
            }
            if (DISPLAY_MODE === REDIRECT) {
              this.redirect(response.paymentPageParams);
            }
            return;
          }
          alert(response.error);
        },
        error: function (jqXHR, textStatus, errorThrown) {
          alert(textStatus);
        },
      });
    },

    loadPopup: function (paymentPageParams) {
      paymentPageParams.onDestroy = function () {
        window.location.replace(URL_RESTORE_CART);
      };
      EPayWidget.run(paymentPageParams);
    },

    redirect: function (paymentPageParams) {
      let form = $("<form>", {
        method: "post",
        action: paymentPageUrl,
        style: {
          display: "none",
        },
      });

      $.each(paymentPageParams, function (key, value) {
        form.append(
          $("<input>", {
            type: "hidden",
            name: key,
            value: value,
          })
        );
      });

      $(form).appendTo("body").submit();
    }
  });
});
