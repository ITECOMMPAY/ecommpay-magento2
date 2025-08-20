# Ecommpay Payments.
Contributors: Ecommpay.

Tags: card payments, apple pay, google pay, open banking, paypal, sofort, ideal, klarna, giropay, payment gateway, Magento

License: GPLv2s

License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept bank transfers, cards, local payment methods and cryptocurrencies. Boost conversion with a customisable checkout form. Enjoy 24/7 expert support.

## Description
Ecommpay’s Magento plugin is a complete growth-focused payment solution for merchants looking to dominate local markets or expand globally, maximise profits and reduce operational costs.

Accept card, bank, eWallet and crypto payments. Make payouts in any Mlocal currency and receive weekly or even more frequent settlements in EUR or GBP. Enjoy industry-leading support, low and transparent fees and advanced checkout form customisation options, including full localisation to any language.

The plugin is available to every business in the EEA and the UK. The integration is quick and intuitive and usually takes 1-2 business days.
## Feature highlights

### Cards
Accept VISA, Mastercard, American Express or Union Pay. Maximise acceptance rates and avoid double conversion with Smart Payment Rooting and Cascading technologies on board.
### Open Banking
Let your customers pay with their bank of choice, reduce processing fees and eliminate the risk of chargebacks. Works with 2000+ banks in Europe and the UK.
### Cryptocurrencies
Take payments in all the popular cryptocurrencies and settle in USD, GBP or EUR without the conversion risk.
### eWallets
Offer an option to pay Apple Pay and Google Pay or local eWallets your customers know and trust, like Blik, Bancontact, EPS, Giropay, iDEAL, Multibanco, Neteller and more.
### Payment links
Create payment links with a few clicks and let your customers pay straight from their email, messenger apps or SMS.
### Payouts
Make refunds or pay your suppliers and business partners in any currency. Payouts are delivered in 30 minutes after the approval.
### Customisation
Fine-tune the look and feel of your checkout form to reach the maximum conversion. Customise the design, available payment methods and languages.
### Support
Enjoy industry-leading support with an average response time of 15 minutes. We are always by your side to help with technical issues and share our knowledge of local markets.
### Settlements
Receive weekly or even more frequent settlements in EUR, USD or GBP.

## Installation
### From the marketplace (recommended)
1. Go to your Magento2 application folder and run this command
```
composer require ecommpay/module-payments
```
2. At this moment you may be asked to submit your access keys. How to get keys see [Get your authentication keys](https://devdocs.magento.com/guides/v2.3/install-gde/prereq/connect-auth.html).
3. Run update Magento commands
```
php bin/magento setup:upgrade
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

### From zip archive
1. Download the zip archive with the latest version of the ecommpay plugin from [github](https://github.com/ITECOMMPAY/ecommpay-magento2/releases).
2. Copy the 'Ecommpay' folder to the '/app/code/' directory on your Magento server. Ensure that the final path appears as 'app/code/Ecommpay/Payments'.
3. Execute the following commands in your Magento server's command line interface:
```
php bin/magento indexer:reindex 
php bin/magento setup:upgrade
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

## Setting up
1. In your Magento Admin Panel, navigate to _Stores -> Configuration -> Sales -> Payment Methods -> ECOMMPAY_.
2. Fill in the "Project ID" and "Secret key" fields in the "General Settings" section, and save the settings.
3. You're now ready to start using Ecommpay with Magento.

## How do I start?
1. Download and install our free Magento plugin. It’s quick and easy. Feel free to test it any time
2. Create a merchant account with Ecommpay and provide all the necessary documents
3. Once approved, go live and start accepting payments in just a couple of days.
4. Receive weekly or even more frequent settlements.
5. Scale your business easily and expand to new markets with the same plugin.

### Dependencies
General:
1. Magento >= 2.2

### Changelog
#### 2.2.1
* Security improvements
#### 2.2.0
* Feature: Restore the cart if the user returns from the payment page by clicking the back button in the browser
#### 2.1.0
* Feature: Enhanced payment flow. We've added a retry feature for failed payments, making it easier for users to complete their purchase.
#### 2.0.0
* Feature: Two-step payments are now available!
#### 1.2.0
* Feature: Add new payment gateways Neteller, Skrill, Bancontact, Multibanco
#### 1.1.2
* Patch: Change the signature level for Ecommpay API requests
#### 1.1.1
* Minor bug resolutions and code refactoring.
#### 1.1.0
* New Payment Page Display mode added it is called “Embedded mode". This mode makes the process of paying even smoother by putting the payment page right on the checkout page. Users no longer need to be redirected to a different page to complete their card payments.
#### 1.0.0
* First release.
