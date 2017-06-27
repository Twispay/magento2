<?php
namespace Twispay\Payments\Service\V1;

use Twispay\Payments\Api\TwispayPaymentDetailsInterface;
use \Magento\Sales\Model\Order;

/**
 * Class TwispayPaymentDetails
 * @package Twispay\Payments\Service\V1
 * @author Webliant Software
 */
class TwispayPaymentDetails implements TwispayPaymentDetailsInterface
{

	/**
	 * Factory for the response object
	 *
	 * @var \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory
	 */
	protected $responseFactory;

	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $checkoutSession;

	/**
	 * @var \Magento\Customer\Model\Session
	 */
	protected $customerSession;

	/**
	 * @var \Magento\Checkout\Helper\Data
	 */
	private $checkoutHelper;

	/**
	 * @var \Twispay\Payments\Model\Config
	 */
	private $config;

	/**
	 * @var \Twispay\Payments\Helper\Payment
	 */
	private $helper;

	/**
	 * @var \Twispay\Payments\Logger\Logger
	 */
	private $log;

	/**
	 * Constructor
	 *
	 * @param \Twispay\Payments\Model\Config $config
	 * @param \Twispay\Payments\Helper\Payment $helper
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Checkout\Helper\Data $checkoutHelper
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
	 */
	public function __construct(
		\Twispay\Payments\Model\Config $config,
		\Twispay\Payments\Helper\Payment $helper,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Helper\Data $checkoutHelper,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
	) {
		$this->config = $config;
		$this->helper = $helper;
		$this->checkoutHelper = $checkoutHelper;
		$this->checkoutSession = $this->checkoutHelper->getCheckout();
		$this->customerSession = $customerSession;
		$this->log = $twispayLogger;
		$this->responseFactory = $responseFactory;
	}

	/**
	 * Generates the payment details for the given order
	 * @param $orderId
	 * @return \Twispay\Payments\Service\V1\Data\OrderPaymentResponse
	 */
	public function getPaymentDetails($orderId)
	{
		// Get the details of the last order
		$order = $this->checkoutSession->getLastRealOrder();

		// Set the status of this order to pending payment
		$order->setState(Order::STATE_PENDING_PAYMENT, true);
		$order->setStatus(Order::STATE_PENDING_PAYMENT);
		$order->addStatusToHistory($order->getStatus(), 'Redirecting to Twispay payment gateway');
		$order->save();

		$address = $order->getBillingAddress();

		$items = array();
		$units = array();
		$unitPrice = array();
		$subTotal = array();
		foreach ($order->getAllVisibleItems() as $key => $item) {
			$items[$key] = $item->getName();
			$subTotal[$key] = strval(number_format((float)$item->getRowTotalInclTax(), 2, '.', ''));
			$unitPrice[$key] = strval(number_format((float)$item->getPriceInclTax(), 2, '.', ''));
			$units[$key] = (int)$item->getQtyOrdered();
		}

		// Add the shipping price
		if ($order->getShippingAmount() > 0) {
			$index             = count($items);
			$items[$index]     = __('Shipping')->render();
			$unitPrice[$index] = strval(number_format((float) $order->getShippingAmount(), 2, '.', ''));;
			$units[$index]     = "";
			$subTotal[$index]  = strval(number_format((float) $order->getShippingAmount(), 2, '.', ''));
		}

		$emptyStringArray = array();
		$emptyStringArray[0] = "";

		$data = [
			'siteId' => strval($this->config->getSiteId()),
			'orderId' => strval((int)$order->getRealOrderId()),
			'currency' => $order->getOrderCurrencyCode(),
			'amount' => strval(number_format((float)$order->getGrandTotal(), 2, '.', '')),
			'orderType' => $this->config->getOrderType(),
			'cardTransactionMode' => $this->config->getCardTransactionMode(),
			'firstName' => $address->getFirstname() != null ? $address->getFirstname() : '',
			'lastName' => $address->getLastname() != null ? $address->getLastname() : '',
			'city' => $address->getCity() != null ? $address->getCity() : '',
			'state' => ($address->getCountryId() == 'US' && $address->getRegionCode() != null) ? $address->getRegionCode() : '',
			'country' => $address->getCountryId() != null ? $address->getCountryId() : '',
			'zipCode' => $address->getPostcode() != null ? preg_replace("/[^0-9]/", '', $address->getPostcode()) : '',
			'address' => $address->getStreet() != null ? join(',', $address->getStreet()) : '',
			'email' => $address->getEmail() != null ? $address->getEmail() : '',
			'phone' => $address->getTelephone() != null ? preg_replace("/[^0-9\+]/", '', $address->getTelephone()) : '',
			'item' => $items,
			'backUrl' => $this->helper->getBackUrl(),
			'unitPrice' => $unitPrice,
			'units' => $units,
			'subTotal' => $subTotal,
			'identifier' => '_' . $this->customerSession->getCustomerId()
		];

		$oResponse = $this->responseFactory->create();
		foreach ($data as $key => $value) {
			$oResponse->setData($key, $value);
		}
		$oResponse->setData('checksum', $this->helper->computeChecksum($data));
		$oResponse->setData('success', true);

		return $oResponse;
	}

}