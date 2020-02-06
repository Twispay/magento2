<?php

namespace Twispay\Payments\Model;

use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Twispay payment method model
 *
 * @category    Twispay\Payments\Model
 * @package     Twispay_Payments
 * @author      Twispay
 * @codingStandardsIgnoreFile
 */
class Twispay extends \Magento\Payment\Model\Method\AbstractMethod {
  const METHOD_CODE = 'twispay';

  /** @var \Twispay\Payments\Helper\Payment */
  private $helper;
  /** @var \Twispay\Payments\Logger\Logger */
  private $log;
  /** @var \Twispay\Payments\Model\Config */
  private $config;

  protected $_code = self::METHOD_CODE;
  protected $_isGateway = true;
  protected $_canAuthorize = true;
  protected $_canCapture = true;
  protected $_canCapturePartial = true;
  protected $_canRefund = true;
  protected $_canRefundInvoicePartial = true;
  protected $_canVoid = false;
  protected $_canUseInternal = true;
  protected $_canUseCheckout = true;
  protected $_canFetchTransactionInfo = true;
  protected $_isInitializeNeeded = false;
  protected $_isOffline = false;


  /**
   * Constructor
   * @param \Twispay\Payments\Helper\Payment $helper
   * @param \Twispay\Payments\Logger\Logger $twispayLogger
   * @param \Magento\Framework\Model\Context $context
   * @param \Magento\Framework\Registry $registry
   * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
   * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
   * @param \Magento\Payment\Helper\Data $paymentData
   * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
   * @param \Magento\Payment\Model\Method\Logger $logger
   * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
   * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
   * @param array $data
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct( \Twispay\Payments\Helper\Payment $helper
                             , \Twispay\Payments\Logger\Logger $twispayLogger
                             , \Twispay\Payments\Model\Config $config
                             , \Magento\Framework\Model\Context $context
                             , \Magento\Framework\Registry $registry
                             , \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
                             , \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
                             , \Magento\Payment\Helper\Data $paymentData
                             , \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
                             , \Magento\Payment\Model\Method\Logger $logger
                             , \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null
                             , \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null
                             , array $data = [] )
  {
    parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    $this->helper = $helper;
    $this->log = $twispayLogger;
    $this->config = $config;
  }


  /**
   * This method will prepare the post data for the Twispay gateway request
   * and store them on the checkout session
   *
   * @param string $paymentAction
   * @param object $stateObject
   *
   * @return $this
   * @throws /Magento\Framework\Exception\PaymentException
   * @api
   */
  public function initialize($paymentAction, $stateObject){
    /** @var \Magento\Sales\Model\Order\Payment $payment */
    $payment = $this->getInfoInstance();

    /** @var \Magento\Sales\Model\Order $order */
    $order = $payment->getOrder();
    if (empty($order) || !$order->getIncrementId()) {
      $this->log->error(__(' Order could not be loaded'));
      throw new PaymentException(__(' Order could not be loaded'));
    }
    /* Disable new order email. */
    $order->setCanSendNewEmailFlag(false);

    /* Set initial order state and status. */
    $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
    $stateObject->setStatus(\Magento\Sales\Model\Order::STATE_NEW);
    $stateObject->setIsNotified(false);
  }


  /**
   * Function that is called when the config 'Payment Action'
   *  option is set to "Authorize Only" and will automatically
   *  authorize all the received orders.
   *
   * Background operations:
   *   - order with status PROCESSING is created;
   *   - transaction is registered into magento with the transaction id;
   *   - order comment is added automatically for authorization;
   *
   */
  public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    $this->log->info(__FUNCTION__ . __(': Authorize payment'));
    return $this;
  }


  /**
   * Function that is called when the config 'Payment Action'
   *  option is set to "Authorize and Capture" and will automatically
   *  authorize and capture all the received orders.
   *
   * Background operations:
   *   - order with status PROCESSING is created;
   *   - transaction is registered into magento with the transaction id;
   *   - order comment is added automatically for authorization;
   *   - invoice is created with status PAID;
   *
   */
  public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    $this->log->info(__FUNCTION__ . __(': Authorize and capture payment'));
    return $this;
  }


  /**
   * Function that is called when a refund is done to refund
   *  specified amount for payment.
   *
   */
  public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    $this->log->info(__FUNCTION__ . __(': Refund payment', $amount));

    /* Get the order. */
    $order = $payment->getOrder();
    /* Extract the parent transaction. */
    $parentTransaction = $this->helper->getTransaction($order->getId(), $this->_getParentTransactionId($payment));

    /* Extract the transaction and transaction data. */
    $parentTransactionData = $parentTransaction->getAdditionalInformation()['raw_details_info'];

    /* Get the config values. */
    $apiKey = $this->config->getApiKey();
    $url = $this->config->getApiUrl();
    if (('' == $apiKey) || ('' == $url)) {
      /* Extract the message. */
      $message = __(' Refund failed: Incomplete or missing configuration');
      /* Log the error. */
      $this->log->error(__FUNCTION__ . ':' . $message);

      throw new \Magento\Framework\Validator\Exception($message);
    }

    /* Create the URL. */
    $url = $url . '/transaction/' . $parentTransactionData['transactionId'];

    /* Create the DELETE data arguments. */
    $postData = 'amount=' . $amount . '&' . 'message=' . 'Refund for order ' . $parentTransactionData['orderId'];
    $this->log->info(__('Refund amount=%1 for transaction=%2 from order %3', $amount, $parentTransactionData['transactionId'], $parentTransactionData['orderId']));
    
    /* Make the server request. */
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json', 'Authorization: ' . $apiKey]);
    curl_setopt($ch, CURLOPT_POST, strlen($postData));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    /* Send the request. */
    $response = curl_exec($ch);
    curl_close($ch);
    /* Decode the response. */
    $response = json_decode($response);

    /* Check if the decryption was successful, the response code is 200 and message is 'Success'. */
    if ((NULL !== $response) && (200 == $response->code) && ('Success' == $response->message)) {
      /* Updated the existing refund transaction with the received answer. */
      $payment->setTransactionId($response->data->transactionId);
      $payment->setTransactionAdditionalInfo( Transaction::RAW_DETAILS
                                            , [ 'orderId'               => $parentTransactionData['orderId']
                                              , 'refundedTransactionId' => $parentTransactionData['transactionId']
                                              , 'transactionId'         => $response->data->transactionId
                                              , 'amount'                => $amount]);
      $payment->setIsTransactionClosed(TRUE);
    } else {
      /* Extract the message. */
      $message = __(' Refund failed: Server returned error: %1', $response->code);
      /* Log the error. */
      $this->log->error(__FUNCTION__ . $message);

      throw new \Magento\Framework\Validator\Exception($message);
    }

    return $this;
  }


  /**
   * Parent transaction id getter
   *
   * @param \Magento\Payment\Model\InfoInterface $payment
   *
   * @return string
   */
  protected function _getParentTransactionId(\Magento\Payment\Model\InfoInterface $payment) {
    return $payment->getParentTransactionId() ? $payment->getParentTransactionId() : $payment->getLastTransId();
  }
}
