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
	],
	function (Component, checkoutData, quote) {
		'use strict';
		var wpConfig = window.checkoutConfig.payment.twispay;
		return Component.extend({
			defaults: {
				template: 'Twispay_Payments/payment/twispay'
			},

			/** Returns send check to info */
			getMailingAddress: function() {
				return window.checkoutConfig.payment.checkmo.mailingAddress;
			},

			redirectToTwispay: function() {
				return true;
			},

			getData: function() {
				return {
					"method": "twispay",
					"additional_data" : {
						"twispay_endpoint" : "https://secure-stage.twispay.com",
						"site_id": wpConfig.site_id
					}
				};
			}
		});
	}
);
