<?php

namespace Twispay\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;

/**
 * This controller handles the payment back URL
 *
 * @package Twispay\Payments\Controller\Checkout
 */
class BackPayment extends Action
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
	 * @var \Twispay\Payments\Model\Config
	 */
	private $config;

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
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Twispay\Payments\Model\Config $config
	 * @param \Twispay\Payments\Helper\Payment $helper
	 * @param CartManagementInterface $quoteManagement
	 * @param QuoteIdMaskFactory $quoteIdMaskFactory
	 * @param CartRepositoryInterface $cartRepository
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param ServiceInputProcessor $inputProcessor
	 * @param OrderFactory $orderFactory
	 */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Twispay\Payments\Model\Config $config,
		\Twispay\Payments\Helper\Payment $helper,
		CartManagementInterface $quoteManagement,
		QuoteIdMaskFactory $quoteIdMaskFactory,
		CartRepositoryInterface $cartRepository,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Model\Session $checkoutSession,
		ServiceInputProcessor $inputProcessor,
		OrderFactory $orderFactory
	)
	{
		parent::__construct($context);

		$this->log = $twispayLogger;
		$this->config = $config;
		$this->helper = $helper;
		$this->quoteManagement = $quoteManagement;
		$this->quoteIdMaskFactory = $quoteIdMaskFactory;
		$this->cartRepository = $cartRepository;
		$this->_customerSession = $customerSession;
		$this->_checkoutSession = $checkoutSession;
		$this->inputProcessor = $inputProcessor;
		$this->_orderFactory = $orderFactory;



	}

	/**
	 * View CMS page action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{

		$response = $this->getRequest()->getParams();
		$this->log->debug(print_r($response, true));

		$result = null;
		if (array_key_exists('opensslResult', $response)) {
			try {
				$result = $this->helper->decryptResponse($response['opensslResult']);

				$this->log->debug(print_r($result, true));
			} catch (LocalizedException $ex) {
				$this->log->error($ex->getMessage(), $ex);
			}
		}

		if ($result && ($result['status'] == 'complete-ok' || $result['status'] == 'in-progress')) {

			$this->messageManager->addSuccessMessage(__('Payment has been successfully authorized. Transaction id: %s'), $result['transactionId']);

			$successPage = $this->config->getSuccessPage();
			$this->log->debug("Redirecting:" + $successPage);
			$this->_redirect($successPage);
		} else {
			$this->messageManager->addErrorMessage(__('Failed to complete payment.'));
			$this->_redirect('checkout/cart');
		}

	}
}