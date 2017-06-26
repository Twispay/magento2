<?php

namespace Twispay\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Customer\Model\Session;

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
	 * Constructor
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Twispay\Payments\Model\Config $config
	 * @param CartManagementInterface $quoteManagement
	 * @param QuoteIdMaskFactory $quoteIdMaskFactory
	 * @param CartRepositoryInterface $cartRepository
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param ServiceInputProcessor $inputProcessor
	 * @param OrderFactory $orderFactory
	 * @param JsonFactory $resultJsonFactory
	 */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Twispay\Payments\Model\Config $config,
		CartManagementInterface $quoteManagement,
		QuoteIdMaskFactory $quoteIdMaskFactory,
		CartRepositoryInterface $cartRepository,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Model\Session $checkoutSession,
		ServiceInputProcessor $inputProcessor,
		OrderFactory $orderFactory
	)
	{
		$this->log = $twispayLogger;
		$this->config = $config;
		$this->quoteManagement = $quoteManagement;
		$this->quoteIdMaskFactory = $quoteIdMaskFactory;
		$this->cartRepository = $cartRepository;
		$this->_customerSession = $customerSession;
		$this->_checkoutSession = $checkoutSession;
		$this->inputProcessor = $inputProcessor;
		$this->_orderFactory = $orderFactory;

		parent::__construct($context);
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

		$successPage = $this->config->getSuccessPage();

		$this->_redirect($successPage);
	}
}