<?php

namespace Twispay\Payments\Model;

use Magento\Framework\Exception\PaymentException;

/**
 * Twispay payment method model
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Webliant Software
 */
class Twispay extends \Magento\Payment\Model\Method\AbstractMethod
{

	const METHOD_CODE = 'twispay';

	/**
	 * Payment code
	 *
	 * @var string
	 */
	protected $_code = self::METHOD_CODE;

	/**
	 * @var String
	 */
	private $apiKey;

	/**
	 * Payment Method feature
	 *
	 * @var bool
	 */
	protected $_isInitializeNeeded = true;

	/**
	 * @var \Twispay\Payments\Logger\Logger
	 */
	private $log;

	/**
	 * Constructor
	 * @param \Magento\Framework\App\RequestInterface $request
	 * @param \Magento\Framework\UrlInterface $urlBuilder
	 * @param \Twispay\Payments\Helper\Payment $helper
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Framework\Locale\ResolverInterface $resolver
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
	 * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
	 * @param \Magento\Payment\Helper\Data $paymentData
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Payment\Model\Method\Logger $logger
	 * @param \Magento\Checkout\Model\Session $session
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
	 * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
	 * @param array $data
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
	 */
	public function __construct(
		\Magento\Framework\App\RequestInterface $request,
		\Magento\Framework\UrlInterface $urlBuilder,
		\Twispay\Payments\Helper\Payment $helper,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\Locale\ResolverInterface $resolver,
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Checkout\Model\Session $session,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		parent::__construct(
			$context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$resource,
			$resourceCollection,
			$data
		);

		$this->log = $twispayLogger;

		$debug = (bool)$this->getConfigData('debug');
		if ($debug) {
			$this->apikey = $this->getConfigValue('test_api_key');
		} else {
			$this->apikey = $this->getConfigValue('live_api_key');
		}

	}

	/**
	 * This method will prepare the post data for the twispay gateway request
	 * and store them on the checkout session
	 *
	 * @param string $paymentAction
	 * @param object $stateObject
	 *
	 * @return $this
	 * @throws /Magento\Framework\Exception\PaymentException
	 * @api
	 */
	public function initialize($paymentAction, $stateObject)
	{
		/** @var \Magento\Sales\Model\Order\Payment $payment */
		$payment = $this->getInfoInstance();

		/** @var \Magento\Sales\Model\Order $order */
		$order = $payment->getOrder();
//		$order->setCanSendNewEmailFlag(false);

		// Set Initial Order Status
		$state = \Magento\Sales\Model\Order::STATE_NEW;
		$stateObject->setState($state);
		$stateObject->setStatus($state);
		$stateObject->setIsNotified(false);

		$orderId = $order->getIncrementId();

		if (empty($order) || !$orderId) {
			$this->log->error('Order could not be loaded');
			throw new PaymentException(__('Order could not be loaded'));
		}

		$this->log->info($orderId);
	}
}
