/**
 * Twispay_Payments Magento JS component
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Twispay
 */
define(
  [
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
  ],
  function (
    Component,
    rendererList
  ) {
    'use strict';
    rendererList.push(
      {
        type: 'twispay',
        component: 'Twispay_Payments/js/view/payment/method-renderer/twispay-method'
      }
    );
    /** Add view logic here if needed */
    return Component.extend({});
  }
);
