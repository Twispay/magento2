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
		'Magento_Checkout/js/model/quote'
	],
	function (Component, checkoutData, quote) {
		'use strict';
		var wpConfig = window.checkoutConfig.payment.twispay;

console.log(checkoutData);
console.log(quote);

		var billingAddress = quote.billingAddress();

		return Component.extend({
			defaults: {
				template: 'Twispay_Payments/payment/twispay',
				firstname: billingAddress.firstname,
				lastname: billingAddress.lastname,
				site_id: wpConfig.site_id
			},

			redirectToTwispay: function() {
				return true;
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
