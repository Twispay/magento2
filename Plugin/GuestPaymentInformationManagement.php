<?php

namespace Twispay\Payments\Plugin;

/**
 * Twispay payment method  CSRF validator
 *
 * @category    Twispay\Payments\Plugin
 * @package     Twispay_Payments
 * @author      Twispay
 */
class GuestPaymentInformationManagement {
  /** @var \Twispay\Payments\Logger\Logger */
  private $log;
  /** @var \Twispay\Payments\Helper\Payment */
  private $helper;

  /**
   * Constructor
   *
   * @param \Twispay\Payments\Logger\Logger $twispayLogger
   * @param \Twispay\Payments\Helper\Payment $helper
   */
  public function __construct( \Twispay\Payments\Logger\Logger $twispayLogger, \Twispay\Payments\Helper\Payment $helper) {
    $this->log = $twispayLogger;
    $this->helper = $helper;
  }


  /**
   * Set payment information and place order for a specified cart.
   *
   * @param \Magento\Checkout\Model\GuestPaymentInformationManagement $subject
   * @param \Closure $proceed
   * @param $cartId
   * @param $email
   * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
   * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
   * @throws \Magento\Framework\Exception\CouldNotSaveException
   * @return string JSON encoded payment details
   */
  public function aroundSavePaymentInformationAndPlaceOrder( \Magento\Checkout\Model\GuestPaymentInformationManagement $subject
                                                           , \Closure $proceed
                                                           , $cartId
                                                           , $email
                                                           , \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
                                                           , \Magento\Quote\Api\Data\AddressInterface $billingAddress)
  {
    /* Execute the normal Magento 2 method and save the order ID. */
    $orderId = $proceed($cartId, $email, $paymentMethod, $billingAddress);

    $this->log->info(__FUNCTION__ . __(" Processing order #%1", $orderId));

    /* Create the payment gateway JSON request. */
    $data = $this->helper->createPurchaseRequest($orderId, /*isGuest*/TRUE);

    /* Return the payment JSON gateway request. */
    return json_encode($data);
  }
}
