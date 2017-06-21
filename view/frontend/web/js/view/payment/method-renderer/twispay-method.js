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

window.checkoutData = checkoutData;
window.quote = quote;

		var billingAddress = quote.billingAddress();
//		var totals = quote.totals();

		return Component.extend({
			defaults: {
				template: 'Twispay_Payments/payment/twispay',
				firstname: billingAddress.firstname,
				lastname: billingAddress.lastname,
				country: billingAddress.country,
				city: billingAddress.city,
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
