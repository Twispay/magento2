/**
 * Twispay_Payments Magento JS component
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Webliant Software
 */

define(
	[
		'Magento_Checkout/js/view/payment/default',
		'Magento_Checkout/js/checkout-data',
		'Magento_Checkout/js/model/quote',
		'Magento_Customer/js/customer-data'
	],
	function (Component, checkoutData, quote, customerData) {
		'use strict';
		var wpConfig = window.checkoutConfig.payment.twispay;

window.checkoutData = checkoutData;
window.quote = quote;
window.customerData = customerData;

		var billingAddress = quote.billingAddress();
		var totals = quote.totals();
//		var customer = customerData.get('customer');

		return Component.extend({
			defaults: {
				template: 'Twispay_Payments/payment/twispay',
				firstname: billingAddress.firstname,
				lastname: billingAddress.lastname,
				country: billingAddress.country,
				city: billingAddress.city,
				amount: totals.grand_total,
				currency: totals.quote_currency_code,
				site_id: wpConfig.site_id
			},

			redirectToTwispay: function() {
				return true;
			},

			getBackUrl: function() {
				return "hello world";//url.build(wpConfig.back_url);
			},

			getData: function() {
				return {
					"method": "twispay",
					"twispay_endpoint" : "https://secure-stage.twispay.com"
				};
			}
		});
	}
);
