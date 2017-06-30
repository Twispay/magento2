<?php

namespace Twispay\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Exception\PaymentException;

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
    private $quoteManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ServiceInputProcessor
     */
    private $inputProcessor;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

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
        ServiceInputProcessor $inputProcessor,
        OrderFactory $orderFactory
    ) {

        parent::__construct($context);

        $this->log = $twispayLogger;
        $this->config = $config;
        $this->helper = $helper;
        $this->quoteManagement = $quoteManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->inputProcessor = $inputProcessor;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Handle the back URL redirect from Twispay gateway
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

                if ($result != null) {
                    $result = json_decode($result);

                    $this->log->debug(print_r($result, true));
                } else {
                    $this->log->error("Decoded response is NULL");
                }
            } catch (\Exception $ex) {
                $this->log->error($ex->getMessage(), $ex);
            }
        }

        if ($result && isset($result->status) &&
                ($result->status == 'complete-ok' || $result->status == 'in-progress')) {
            try {
                $this->helper->processGatewayResponse($result);

                $message = __('Payment has been successfully authorized. Transaction id: %1', $result->transactionId);
                $this->messageManager->addSuccessMessage($message);

                $successPage = $this->config->getSuccessPage();
                $this->log->debug("Redirecting:" . $successPage);
                $this->_redirect($successPage);
            } catch (PaymentException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->_redirect('checkout/cart');
            }
        } else {
            $this->messageManager->addErrorMessage(__('Failed to complete payment.'));
            $this->_redirect('checkout/cart');
        }
    }
}
