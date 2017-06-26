<?php
namespace Twispay\Payments\Api;

/**
 * Interface TwispayPaymentDetailsInterface
 * @api
 * @package Twispay\Payments\Api
 * @author Webliant Software
 */
interface TwispayPaymentDetailsInterface
{

	/**
	 * @param string $orderId
	 *
	 * @return \Twispay\Payments\Service\V1\Data\OrderPaymentResponse
	 */
	public function getPaymentDetails($orderId);

}