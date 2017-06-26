<?php
/**
 * Created by IntelliJ IDEA.
 * User: Alecs
 * Date: 25/06/2017
 * Time: 11:58
 */

namespace Twispay\Payments\Service\V1\Data;

use Twispay\Payments\Api\Data\country;
use Twispay\Payments\Api\Data\OrderPaymentResponseInterface;

/**
 * Class OrderPaymentResponse
 * @package Twispay\Payments\Service\V1\Data
 * @author Webliant Software
 */
class OrderPaymentResponse extends \Magento\Framework\Api\AbstractExtensibleObject implements OrderPaymentResponseInterface
{
	/**
	 * @return string
	 */
	public function getSiteId()
	{
		return $this->_get('siteId');
	}

	/**
	 * @return string
	 */
	public function getIdentifier()
	{
		return $this->_get('identifier');
	}

	/**
	 * @return string|null
	 */
	public function getFirstName()
	{
		return $this->_get('firstName');
	}

	/**
	 * @return string|null
	 */
	public function getLastName()
	{
		return $this->_get('lastName');
	}

	/**
	 * @return string|null
	 */
	public function getEmail()
	{
		return $this->_get('email');
	}

	/**
	 * @return string|null
	 */
	public function getCountry()
	{
		return $this->_get('country');
	}

	/**
	 * @return string|null
	 */
	public function getCity()
	{
		return $this->_get('city');
	}

	/**
	 * @return string|null
	 */
	public function getState()
	{
		return $this->_get('state');
	}

	/**
	 * @return string|null
	 */
	public function getZipCode()
	{
		return $this->_get('zipCode');
	}

	/**
	 * @return string|null
	 */
	public function getAddress()
	{
		return $this->_get('address');
	}

	/**
	 * @return string|null
	 */
	public function getPhone()
	{
		return $this->_get('phone');
	}

	/**
	 * @return string
	 */
	public function getAmount()
	{
		return $this->_get('amount');
	}

	/**
	 * @return string
	 */
	public function getCurrency()
	{
		return $this->_get('currency');
	}

	/**
	 * @return string|null
	 */
	public function getDescription()
	{
		return $this->_get('description');
	}

	/**
	 * @return string
	 */
	public function getOrderType()
	{
		return $this->_get('orderType');
	}

	/**
	 * @return string
	 */
	public function getOrderId()
	{
		return $this->_get('orderId');
	}

	/**
	 * @return array
	 */
	public function getItem() {
		return $this->_get('item');
	}

	/**
	 * @return array
	 */
	public function getUnitPrice()
	{
		return $this->_get('unitPrice');
	}

	/**
	 * @return array
	 */
	public function getUnits()
	{
		return $this->_get('units');
	}

	/**
	 * @return array
	 */
	public function getSubTotal()
	{
		return $this->_get('subTotal');
	}

	/**
	 * @return string
	 */
	public function getCardTransactionMode()
	{
		return $this->_get('cardTransactionMode');
	}

	/**
	 * @return string
	 */
	public function getBackUrl()
	{
		return $this->_get('backUrl');
	}

	/**
	 * @return string
	 */
	public function getChecksum()
	{
		return $this->_get('checksum');
	}

}