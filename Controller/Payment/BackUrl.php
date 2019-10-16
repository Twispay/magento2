<?php

namespace Twispay\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\PaymentException;

/**
 * This controller handles the payment back URL
 *
 * @package Twispay\Payments\Controller\Checkout
 */
class BackUrl extends Action {
  /** @var \Twispay\Payments\Model\Config */
  private $config;
  /** @var \Twispay\Payments\Logger\Logger */
  private $log;
  /** @var \Twispay\Payments\Helper\Payment */
  private $helper;


  /**
   * Constructor
   *
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Twispay\Payments\Logger\Logger $twispayLogger
   * @param \Twispay\Payments\Model\Config $config
   * @param \Twispay\Payments\Helper\Payment $helper
   */
  public function __construct( \Magento\Framework\App\Action\Context $context
                             , \Twispay\Payments\Logger\Logger $twispayLogger
                             , \Twispay\Payments\Model\Config $config
                             , \Twispay\Payments\Helper\Payment $helper)
  {
    parent::__construct($context);

    $this->log = $twispayLogger;
    $this->config = $config;
    $this->helper = $helper;
  }


  /**
   * Handle the back URL redirect from Twispay gateway
   *
   * @return \Magento\Framework\Controller\ResultInterface
   */
  public function execute() {
    $this->log->info(__FUNCTION__ . __(' Process the backUrl response of the Twispay server'));

    /* Get the config values. */
    $apiKey = $this->config->getApiKey();
    if ('' == $apiKey) {
      $this->messageManager->addErrorMessage(__FUNCTION__ . __(' Payment failed: Incomplete or missing configuration'));
      $this->_redirect('checkout/onepage/failure', ['_secure' => TRUE]);
    }

    /* Get the server response. */
    $response = $this->getRequest()->getPost('opensslResult', NULL);
    /* Check that the 'opensslResult' POST param exists. */
    if(NULL == $response){
      /* Try to get the 'result' POST param. */
      $response = $this->getRequest()->getPost('result', NULL);
    }
    /* Check that the 'result' POST param exists. */
    if(NULL == $response){
      /* Extract the message. */
      $message = __(' NULL response received');
      /* Log the error. */
      $this->log->error(__FUNCTION__ . $message);
      $this->messageManager->addErrorMessage(__FUNCTION__ . $message);
      /* Redirect to fail page. */
      $this->_redirect('checkout/onepage/failure', ['_secure' => TRUE]);
    }

    /* Decrypt the response. */
    $decrypted = $this->helper->twispay_tw_decrypt_message(/*tw_encryptedResponse*/$response, /*secretKey*/$apiKey);

    if(FALSE == $decrypted){
      /* Extract the message. */
      $message = __(' Failed to decript the response');
      /* Log the error. */
      $this->log->error(__FUNCTION__ . $message);
      $this->messageManager->addErrorMessage(__FUNCTION__ . $message);
      /* Redirect to fail page. */
      $this->_redirect('checkout/onepage/failure', ['_secure' => TRUE]);
    }

    /* Validate the decripted response. */
    $orderValidation = $this->helper->twispay_tw_checkValidation($decrypted);

    if(FALSE == $orderValidation){
      /* Extract the message. */
      $message = _(' Failed to validate the response');
      /* Log the error. */
      $this->log->error(__FUNCTION__ . $message);
      $this->messageManager->addErrorMessage(__FUNCTION__ . $message);
      /* Redirect to fail page. */
      $this->_redirect('checkout/onepage/failure', ['_secure' => TRUE]);
    }

    /* Extract the order. */
    $order = $this->helper->getOrder($decrypted['externalOrderId']);
    if (NULL === $order) {
      /* Extract the message. */
      $message = _(' Order doesn\'t exists in store');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Update the status. */
    $statusUpdate = $this->helper->updateStatus_purchase_backUrl($order, $decrypted['transactionId'], (empty($decrypted['status'])) ? ($decrypted['transactionStatus']) : ($decrypted['status']));

    /* Check status update result. */
    if (TRUE == $statusUpdate) {
      /* Check if a transaction with the same ID exists. */
      $transactions = $this->helper->getOrderTransactions($order->getId());

      /* Check if the transaction has already been registered. */
      $skipTransactionAdd = FALSE;
      foreach ($transactions as $transaction) {
        if($decrypted['transactionId'] == $transaction->getTxnId()){
          $skipTransactionAdd = TRUE;
          break;
        }
      }

      if (FALSE == $skipTransactionAdd) {
        /* Save the payment transaction. */
        $this->helper->addOrderTransaction($order, /*serverResponse*/$decrypted);

        /* Link transaction to existing invoice. */
        $this->helper->generateInvoice($order, $decrypted['transactionId']);
      }

      $successPage = $this->config->getSuccessPage();
      if ('' == $successPage) {
        $successPage = 'checkout/onepage/success';
      }

      $this->_redirect($successPage, ['_secure' => TRUE]);
    } else {
      $this->messageManager->addErrorMessage(__(' Failed to complete payment'));
      $this->_redirect('checkout/onepage/failure', ['_secure' => TRUE]);
    }
  }
}
