=== PayByBank Integration for WooCommerce ===
Contributors: dichagr, paybybankdevs, theogk
Author: PayByBank
Author link: https://www.paybybank.eu/
Tags: paybybank, payments, payment gateway, rf payment, bacs
Requires at least: 5.6
Tested up to: 6.5.3
WC requires at least: 5.6.0
WC tested up to:   8.9
Requires PHP: 7.2
Version: 2.1.1
Stable tag: 2.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

PayByBank enables automated and secure online transactions. Online payments are completed in real time and the order proceeds without delay.

== Description ==

**PayByBank** enables automated and secure online transactions. Online payments are completed in real time and the order proceeds without delay.
Money are credited to the account immediately or the next day.

Payments are processed via ebanking/mobile banking or phone banking through payments sections of each bank respectively.

**Which Banks participate**
All Greek Banking Institutions

**Why choose PayByBank?**
There are no setup fees, no monthly fees, no hidden costs: you only get charged when you make transactions and the more transactions you make the less fees you pay!

For more information on pricing please contact info@paybybank.gr

Earnings are transferred to your bank account daily.

PayByBank facilitates online payments and provides secure online shopping as it is not associated with credit/debit card details.

**IMPORTANT NOTICE**
In order to be able to use the PayByBank service, a contract between you and PayByBank must be previously signed.

This plugin uses 3rd party services.
It uses the API of PayByBank to send orders to its system to create reference code so the client can pay with this via ebanking or mobile banking.
Also, the plugin uses these services to check the status of orders in PayByBank environment (if are paid or not).

PayByBank official website: https://www.paybybank.eu/en/
About PayByBank: https://www.paybybank.eu/en/company/
Terms of Use: https://www.paybybank.eu/en/terms/
Data Protection Policy: https://www.paybybank.eu/en/data-protection/

Test URL: https://testapi.e-paylink.com/gateway/rest/api/v1
Live URL: https://www.wu-online.gr/gateway/rest/api/v1

**FEATURES**
Provides pre-auth transactions and free installments.


== Installation ==

= Minimum Requirements =

* WooCommerce 5.6 or later
* WordPress 5.6 or later

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't even need to leave your web browser. To do an automatic installation of PayByBank plugin, log in to your WordPress admin panel, navigate to the Plugins menu and click Add New.

In the search field type "PayByBank" and click Search Plugins. You can install it by simply clicking Install Now. After clicking that link you will be asked if you're sure you want to install the plugin. Click yes and WordPress will automatically complete the installation. After installation has finished, click the 'activate plugin' link.

= Manual installation via the WordPress interface =
1. Download the plugin zip file to your computer
2. Go to the WordPress admin panel menu Plugins > Add New
3. Choose upload
4. Upload the plugin zip file, the plugin will now be installed
5. After installation has finished, click the 'activate plugin' link

= Manual installation via FTP =
1. Download the plugin file to your computer and unzip it
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.

== Usage ==

1. Go to the `WooCommerce -> Settings -> Payments` menu to manage payment gateways.
2. There you can enable the payment gateway and edit settings for it.

== Documentation ==

You can find the official documentation in the links below:
[Documentation PDF in English](https://www.paybybank.eu/files/paybybank_wordpress_plugin_EN.PDF)
[Documentation PDF in Greek](https://www.paybybank.eu/files/paybybank_wordpress_plugin_GR.PDF)

== Screenshots ==

1. This is how the payment will appear by default in your WooCommerce Store. Enable or disable payment gateway as a normal payment gateway.
2. Find the Merchant PaymentURL here.
3. Description of the plugin and how it will be displayed.
4. Instructions of plugin will be displayed in checkout page and in the email.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Not yet! We are working on that.

= Does this require an SSL certificate? =

No it doesn’t. We only need a Payment URL. We support http and https protocols in order to send out payment notifications.

= Does this support both production mode and sandbox mode for testing? =

Yes, it does! Production and playground mode are driven by the API keys you use obtained by [technical integration](https://www.paybybank.gr/en/node/30).

= Does this require an SSL certificate? =

For help setting up and configuring, please refer to our [documentation](https://www.paybybank.gr/files/Paybybank_WS_General_Documentation_en.pdf).

= Where can I get support or talk to other users? =

You can ask for help in the Plugin Forum.

== Changelog ==

= 2.1.0 =
*Release Date - 14 May 2024*
* Minor fix for greek translation files

= 2.1.0 =
*Release Date - 2 May 2024*
* Tested ok with WordPress 6.5.x and WooCommerce 8.8.x
* Separate messages enabled for thank you page and emails, and HTML tags enabled (only safe tags).
* Added a background automated mechanism to check for payment status changes.
* New interface in order edit page with PayByBank payment info, plus a button to check the payment status on demand.
* Show PayByBank payment status in emails.
* Added detailed logging functionality, using native WooCommerce Logger.
* Added full support for the new WooCommerce Block Checkout mode.
* Added a "Click to copy" functionality for easier copying of the RF code.
* Added options to stop "Paid by PayByBank" email notification.
* Added option to select the payment status after the successful payment via PayByBank.
* Added the {pbb_rf_code} placeholder to enter the RF code inside your custom messages.

= 2.0.0 =
*Release Date - 06 December 2022*
* Multiple small fixes and improvements.
* Security improvements.
* Translation pot file updated.
* Filters added to make fee taxable on checkout if needed.
* Check compatibility with WordPress 6.1.x and WooCommerce 7.2.x
* Marks compatibility with new WooCommerce custom order tables (HPOS)
* Code cleanup.

= 1.0.3 =
*Release Date - 22 March 2021*
* Fix answer message to return it appropriately.

= 1.0.2 =
*Release Date - 24 January 2020*
* Add error log for POST requests to locate error.

= 1.0.1 =
*Release Date - 24 January 2020*
* Change the live version URL of PayByBank service.

= 1.0.0 =
*Release Date - 10 January 2020*
* The first stable version of PayByBank plugin.