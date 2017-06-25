<?php
namespace Twispay\Payments\Service\V1;



use Twispay\Payments\Api\TwispayPaymentDetailsInterface;

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
	 * Constructor
	 *
	 * @param \Twispay\Payments\Model\Config $config
	 * @param \Twispay\Payments\Helper\Payment $helper
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Checkout\Helper\Data $checkoutHelper
	 * @param \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
	 */
	public function __construct(
		\Twispay\Payments\Model\Config $config,
		\Twispay\Payments\Helper\Payment $helper,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Helper\Data $checkoutHelper,
		\Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
	) {
		$this->config = $config;
		$this->helper = $helper;
		$this->checkoutHelper = $checkoutHelper;
		$this->checkoutSession = $this->checkoutHelper->getCheckout();
		$this->customerSession = $customerSession;

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
		$quote = $this->checkoutSession->getQuote();

		$address = $quote->getBillingAddress();

		$items = array();
		foreach ($order->getAllItems() as $key => $item) {
			$items[$key] = $item->getName();
		}

		$data = [
			'siteId' => $this->config->getSiteId(),
			'orderId' => $order->getRealOrderId(),
			'currency' => $order->getOrderCurrencyCode(),
			'amount' => number_format((float)$order->getGrandTotal(), 2, '.', ''),
			'orderType' => $this->config->getOrderType(),
			'cardTransactionMode' => $this->config->getCardTransactionMode(),
			'firstName' => $address->getFirstname(),
			'lastName' => $address->getLastname(),
			'city' => $address->getCity(),
			'state' => $address->getRegion(),
			'country' => $address->getCountry(),
			'zipCode' => $address->getPostcode(),
			'address' => $address->getStreetFull(),
			'item' => $items,
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