<?php

namespace Twispay\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Customer\Model\Session;
use \Magento\Sales\Model\Order;

/**
 * This controller handles the server to server notification
 *
 * @package Twispay\Payments\Controller\Checkout
 */
class Notification extends Action
{
	/**
	 * @var CartManagementInterface
	 */
	protected $quoteManagement;

	/**
	 * @var QuoteIdMaskFactory
	 */
	protected $quoteIdMaskFactory;

	/**
	 * @var CartRepositoryInterface
	 */
	protected $cartRepository;

	/**
	 * @var \Magento\Customer\Model\Session
	 */
	protected $_customerSession;

	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $_checkoutSession;

	/**
	 * @var ServiceInputProcessor
	 */
	protected $inputProcessor;

	/**
	 * @var \Magento\Sales\Model\OrderFactory
	 */
	protected $_orderFactory;

	/**
	 * @var ResultFactory
	 */
	protected $resultFactory;

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
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param CartManagementInterface $quoteManagement
	 * @param QuoteIdMaskFactory $quoteIdMaskFactory
	 * @param CartRepositoryInterface $cartRepository
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param ServiceInputProcessor $inputProcessor
	 * @param OrderFactory $orderFactory
	 * @param ResultFactory $resultFactory
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Twispay\Payments\Helper\Payment $helper
	 */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		CartManagementInterface $quoteManagement,
		QuoteIdMaskFactory $quoteIdMaskFactory,
		CartRepositoryInterface $cartRepository,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Model\Session $checkoutSession,
		ServiceInputProcessor $inputProcessor,
		OrderFactory $orderFactory,
		ResultFactory $resultFactory,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Twispay\Payments\Helper\Payment $helper
	)
	{
		$this->quoteManagement = $quoteManagement;
		$this->quoteIdMaskFactory = $quoteIdMaskFactory;
		$this->cartRepository = $cartRepository;
		$this->_customerSession = $customerSession;
		$this->_checkoutSession = $checkoutSession;
		$this->inputProcessor = $inputProcessor;
		$this->_orderFactory = $orderFactory;
		$this->resultFactory = $resultFactory;
		$this->log = $twispayLogger;
		$this->helper = $helper;

		parent::__construct($context);
	}

	/**
	 * Handle the server-to-server notification
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		$oResponse = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);

		$response = $this->getRequest()->getParams();
		$this->log->debug(print_r($response, true));

		$result = null;
		if (array_key_exists('opensslResult', $response)) {
			try {
				$result = $this->helper->decryptResponse($response['opensslResult']);

				if ($result != null) {
					$result = json_decode($result);

					$this->log->debug(print_r($result, true));
				} else {
					$this->log->error("Decoded response is NULL");
				}
			} catch (LocalizedException $ex) {
				$this->log->error($ex->getMessage(), $ex);
			}
		}

		if ($result && ($result['status'] == 'complete-ok' || $result['status'] == 'in-progress')) {

			// Set the status of this order to processing
			$orderId = $result->externalOrderId;
			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$order = $objectManager->create('\Magento\Sales\Model\Order') ->load($orderId);
			$order->setState(Order::STATE_PROCESSING, true);
			$order->setStatus(Order::STATE_PROCESSING);
			$order->addStatusToHistory($order->getStatus(), 'Order paid successfully with reference ' . $result->transactionId);
			$order->save();

			$oResponse->setContents('OK');
		} else {
			$oResponse->setContents('ERROR');

		}

		return $oResponse;
	}
}