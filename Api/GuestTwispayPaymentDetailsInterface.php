<?php
namespace Twispay\Payments\Api;

/**
 * Interface GuestTwispayPaymentDetailsInterface
 * @api
 * @package Twispay\Payments\Api
 * @author Webliant Software
 */
interface GuestTwispayPaymentDetailsInterface
{

    /**
     * @param string $orderId
     *
     * @return \Twispay\Payments\Service\V1\Data\OrderPaymentResponse
     */
    public function getPaymentDetails($orderId);
}
