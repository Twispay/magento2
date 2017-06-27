/**
 * Twispay_Payments Magento JS component
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Webliant Software
 */

define(
	[
		'jquery',
		'Magento_Checkout/js/view/payment/default',
		'Magento_Checkout/js/model/url-builder',
		'mage/storage',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/action/place-order',
		'Magento_Checkout/js/model/payment/additional-validators',
		'Magento_Checkout/js/model/quote',
		'Magento_Customer/js/model/customer'
	],
	function ($, Component, urlBuilder, storage, fullScreenLoader,
					placeOrderAction, additionalValidators, quote, customer) {
		'use strict';
		var wpConfig = window.checkoutConfig.payment.twispay;

		return Component.extend({
			defaults: {
				template: 'Twispay_Payments/payment/twispay'
			},

			redirectAfterPlaceOrder: false,

			placeOrder: function (data, event) {
				var self = this;

				if (event) {
					event.preventDefault();
				}

				if (this.validate() && additionalValidators.validate()) {
					this.isPlaceOrderActionAllowed(false);

					this.getPlaceOrderDeferredObject()
						.fail(
							function () {
								self.isPlaceOrderActionAllowed(true);
							}
						).done(
							function (orderId) {
								self.afterPlaceOrder(orderId);

							}
						);

					return true;
				}

				return false;
			},

			/**
			 * After the order is placed we must redirect to Twispay to do the payment
			 */
			afterPlaceOrder: function (orderId) {
				var serviceUrl, payload;
				var self = this;

				payload = {
					order_id: orderId
				};

				if (customer.isLoggedIn()) {
					serviceUrl = urlBuilder.createUrl('/carts/mine/retrieve-twispay-payment-details', {});
				} else {
					serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/retrieve-twispay-payment-details', {
						quoteId: quote.getQuoteId()
					});
					payload.email = quote.guestEmail;
				}

				fullScreenLoader.startLoader();

				storage.post(
					serviceUrl, JSON.stringify(payload)
				).fail(
					function (response) {
						errorProcessor.process(response, messageContainer);
					}
				).done(
					function (response) {
						self.redirectToGateway(response);
					}
				).always(
					function () {
						fullScreenLoader.stopLoader();
					}
				);
				return false;
			},

			/**
			 * Redirects the user to Twispay payment page
			 * @param data
			 */
			redirectToGateway: function(params) {
				var form = document.createElement("form");
				form.setAttribute("id", "twispayForm");
				form.setAttribute("method", "post");
				form.setAttribute("accept-charset", "UTF-8");
				form.setAttribute("action", wpConfig.redirect_url);

				var snakeToCamel = function(s){
					return s.replace(/(\_\w)/g, function(m){return m[1].toUpperCase();});
				};

				for(var key in params) {
					if(params.hasOwnProperty(key)) {
						if (params[key] instanceof Array) {
							for (var i = 0; i < params[key].length; i++) {
								var hiddenField = document.createElement("input");
								hiddenField.setAttribute("type", "hidden");
								hiddenField.setAttribute("name", snakeToCamel(key) + "["+i+"]");
								hiddenField.setAttribute("value", params[key][i]);

								form.appendChild(hiddenField);
							}
						} else {
							var hiddenField = document.createElement("input");
							hiddenField.setAttribute("type", "hidden");
							hiddenField.setAttribute("name", snakeToCamel(key));
							hiddenField.setAttribute("value", params[key]);

							form.appendChild(hiddenField);
						}
					}
				}

				document.body.appendChild(form);
				form.submit();
			},

			getData: function() {
				return {
					method: "twispay"
				};
			}
		});
	}
);
