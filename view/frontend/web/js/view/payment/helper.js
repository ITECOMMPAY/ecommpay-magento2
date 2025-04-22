define(
    [],
    function () {
        'use strict';
        return {
            nonDecimalCurrencies: [
                'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG',
                'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
            ],
            priceMultiplyByCurrencyCode: function(price, currency)
            {
                if (this.nonDecimalCurrencies.includes(currency.toUpperCase())) {
                    return Math.round(price);
                }
                return Math.round(price * 100);
            },
            streetArrayToString: function(streetArray)
            {
                if (!Array.isArray(streetArray)) {
                    return "";
                }
                return streetArray.join(" ")
            }
        }
    }
);
