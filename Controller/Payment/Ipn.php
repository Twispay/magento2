<?php

namespace Twispay\Payments\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * This controller handles the server to server notification
 *
 * @package Twispay\Payments\Controller\Checkout
 */
class Ipn extends Action {
  /** @var \Twispay\Payments\Model\Config */
  private $config;
  /** @var \Twispay\Payments\Logger\Logger */
  private $log;
  /**  @var \Twispay\Payments\Helper\Payment */
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
    $this->log = $twispayLogger;
    $this->config = $config;
    $this->helper = $helper;

    parent::__construct($context);
  }


  /**
   * Function that processes the IPN (Instant Payment Notification) message of the server.
   *
   * @return \Magento\Framework\Controller\ResultInterface
   */
  public function execute() {
    $this->log->info(__FUNCTION__ . __(' Process the IPN response of the Twispay server'));

    /* Prepare the processing response that will be returned. */
    $_response = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);

    /* Get the config values. */
    $apiKey = $this->config->getApiKey();
    if ('' == $apiKey) {
      /* Extract the message. */
      $message = __(' Payment failed: Incomplete or missing configuration');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Check if we received a response. */
    if( (NULL === $this->getRequest()->getParam('opensslResult')) && (NULL === $this->getRequest()->getParam('result')) ) {
      /* Extract the message. */
      $message = __(' NULL response received');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Get the server response. */
    $response = (NULL !== $this->getRequest()->getParam('opensslResult')) ? ($this->getRequest()->getParam('opensslResult')) : ($this->getRequest()->getParam('result'));

    /* Decrypt the response. */
    $decrypted = $this->helper->twispay_tw_decrypt_message(/*tw_encryptedResponse*/$response, /*secretKey*/$apiKey);

    if(FALSE == $decrypted) {
      /* Extract the message. */
      $message = __(' Failed to decript the response');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Validate the decripted response. */
    $orderValidation = $this->helper->twispay_tw_checkValidation($decrypted);

    if(TRUE !== $orderValidation) {
      /* Extract the message. */
      $message = __(' Failed to validate the response');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Extract the order. */
    $order = $this->helper->getOrder($decrypted['externalOrderId']);
    if (NULL === $order) {
      /* Extract the message. */
      $message = __(' Order doesn\'t exists in store');
      /* Log the error. */
      $this->log->error($message);
      $_response->setContents($message);
      return $_response;
    }

    /* Update the status. */
    $statusUpdate = $this->helper->updateStatus_purchase_IPN($order, $decrypted['transactionId'], (empty($decrypted['status'])) ? ($decrypted['transactionStatus']) : ($decrypted['status']));

    /* Check status update result. */
    if (TRUE == $statusUpdate) {
      /* Check if a transaction with the same ID exists. */
      $transactions = $this->helper->getTransactions($order->getId());

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
        $this->helper->addPurchaseInvoice($order, $decrypted['transactionId']);
      }
    }

    /* Set the OK response body. */
    $_response->setContents('OK');

    return $_response;
  }
}
