<?php

namespace Twispay\Payments\Plugin;

class GuestPaymentInformationManagement
{
    /**
     * @var \Twispay\Payments\Logger\Logger
     */
    private $log;

    /**
     * @var \Twispay\Payments\Helper\Payment
     */
    private $helper;

    /**
     * Constructor
     *
     * @param \Twispay\Payments\Logger\Logger $twispayLogger
     * @param \Twispay\Payments\Helper\Payment $helper
     */
    public function __construct(
        \Twispay\Payments\Logger\Logger $twispayLogger,
        \Twispay\Payments\Helper\Payment $helper
    ) {
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
    public function aroundSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\GuestPaymentInformationManagement $subject,
        \Closure $proceed,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    ) {
        $paymentDetails = $subject->getPaymentInformation($cartId);

        $orderId = $proceed($cartId, $email, $paymentMethod, $billingAddress);

        $data = $this->helper->prepareGatewayRequest($orderId, true);

        $this->log->debug("Intercepted order ID: " . $orderId);

        return json_encode($data);
    }
}
