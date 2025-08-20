define([
  'uiComponent',
  'Magento_Checkout/js/model/payment/renderer-list',
  'Magento_Ui/js/model/messageList',
  'jquery',
], function (Component, rendererList, messageList, $) {
  'use strict';

  var timer = setInterval(function () {
    if (window.checkoutConfig.ecommpay_settings.paymentPageHost != null) {
      var head = document.getElementsByTagName('head')[0];
      var link = document.createElement('link');
      var script = document.createElement('script');
      var paymentPageHost = window.checkoutConfig.ecommpay_settings.paymentPageHost;
      var paymentPageProtocol = window.checkoutConfig.ecommpay_settings.paymentPageProtocol;

      link.rel = 'stylesheet';
      link.href = paymentPageProtocol + '://' + paymentPageHost + '/shared/merchant.css';
      link.type = 'text/css';

      script.type = 'text/javascript';
      script.src = paymentPageProtocol + '://' + paymentPageHost + '/shared/merchant.js';
      script.async = true;
      script.onload = function () {
        window.checkoutConfig.ecommpay_settings.merchantScriptIsLoaded = true;
      };

      head.appendChild(link);
      head.appendChild(script);

      clearInterval(timer);
      return;
    }
  }, 10);

  if (window.checkoutConfig.ecommpay_settings.pluginEnabled) {
    let card_component_path = '';
    if (window.checkoutConfig.ecommpay_settings.displayMode === "embedded") {
      card_component_path = 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_card_embedded';
    } else {
      card_component_path = 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_card';
    }
    rendererList.push(
      {
        type: 'ecommpay_card',
        component: card_component_path,
      },
      {
        type: 'ecommpay_googlepay',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_googlepay',
      },
      {
        type: 'ecommpay_open_banking',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_open_banking',
      },
      {
        type: 'ecommpay_paypal',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_paypal',
      },
      {
        type: 'ecommpay_paypal_paylater',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_paypal_paylater',
      },
      {
        type: 'ecommpay_sofort',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_sofort',
      },
      {
        type: 'ecommpay_ideal',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_ideal',
      },
      {
        type: 'ecommpay_klarna',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_klarna',
      },
      {
        type: 'ecommpay_blik',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_blik',
      },
      {
        type: 'ecommpay_giropay',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_giropay',
      },
      {
        type: 'ecommpay_neteller',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_neteller',
      },
      {
        type: 'ecommpay_skrill',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_skrill',
      },
      {
        type: 'ecommpay_bancontact',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_bancontact',
      },
      {
        type: 'ecommpay_multibanco',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_multibanco',
      },
      {
        type: 'ecommpay_more_methods',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_more_methods',
      }
    );

    if (
      Object.prototype.hasOwnProperty.call(window, 'ApplePaySession') &&
      window.ApplePaySession.canMakePayments()
    ) {
      rendererList.push({
        type: 'ecommpay_applepay',
        component: 'Ecommpay_Payments/js/view/payment/method-renderer/ecommpay_applepay',
      });
    }
  }

  return Component.extend({});
});
