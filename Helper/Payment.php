<?php

namespace Twispay\Payments\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;

/**
 * Helper class for everything that has to do with payment
 *
 * @package Twispay\Payments\Helper
 */
class Payment extends \Magento\Framework\App\Helper\AbstractHelper {
  /**
   * Store manager object
   *
   * @var \Magento\Store\Model\StoreManagerInterface
   */
  private $storeManager;

  /**
   * @var \Twispay\Payments\Logger\Logger
   */
  private $log;

  /**
   * @var \Twispay\Payments\Model\Config
   */
  private $config;

  /**
   * @var \Magento\Framework\App\ObjectManager
   */
  private $objectManager;

  /**
   * @var \Magento\Sales\Api\OrderRepositoryInterface
   */
  private $orderRepository;

  /**
   * Constructor
   *
   * @param \Magento\Framework\App\Helper\Context $context
   * @param \Twispay\Payments\Model\Config $config
   * @param \Twispay\Payments\Logger\Logger $twispayLogger
   * @param \Magento\Store\Model\StoreManagerInterface $storeManager
   * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
   */
  public function __construct( \Magento\Framework\App\Helper\Context $context
                             , \Twispay\Payments\Model\Config $config
                             , \Twispay\Payments\Logger\Logger $twispayLogger
                             , \Magento\Store\Model\StoreManagerInterface $storeManager
                             , \Magento\Sales\Api\OrderRepositoryInterface $orderRepository)
  {
    parent::__construct($context);
    $this->config = $config;
    $this->log = $twispayLogger;
    $this->storeManager = $storeManager;
    $this->orderRepository = $orderRepository;

    $this->objectManager = ObjectManager::getInstance();
  }


  /************************** Notification START **************************/
  /**
   * Extract the full path for the back URL response.
   * 
   * @return string
   */
  public function getBackUrl(){
    $backUrl = $this->config->getBackUrl();
    if ("" !== $backUrl) {
      return $this->storeManager->getStore()->getBaseUrl() . $backUrl;
    }

    return "";
  }


  /**
   * Get the `jsonRequest` parameter (order parameters as JSON and base64 encoded).
   *
   * @param array $orderData The order parameters.
   *
   * @return string
   */
  public function getBase64JsonRequest(array $orderData) {
    return base64_encode(json_encode($orderData));
  }


  /**
   * Get the `checksum` parameter (the checksum computed over the `jsonRequest` and base64 encoded).
   *
   * @param array $orderData The order parameters.
   * @param string $secretKey The secret key (from Twispay).
   *
   * @return string
   */
  public function getBase64Checksum(array $orderData, $secretKey) {
    $hmacSha512 = hash_hmac(/*algo*/'sha512', json_encode($orderData), $secretKey, /*raw_output*/true);
    return base64_encode($hmacSha512);
  }
  /************************** Notification END **************************/



  /************************** Response START **************************/
  /**
   * Decrypt the response from Twispay server.
   *
   * @param string $tw_encryptedMessage - The encripted server message.
   *
   * @return Array([key => value,]) - If everything is ok array containing the decrypted data.
   *         bool(FALSE)            - If decription fails.
   */
  public function twispay_tw_decrypt_message($tw_encryptedMessage) {
    /* Get the API key from the cache to be used as a decryption key */
    $privateKey = $this->config->getPrivateKey();
    $encrypted = (string)$tw_encryptedMessage;

    if(!strlen($encrypted) || (FALSE == strpos($encrypted, ','))) {
      return FALSE;
    }

    /* Get the IV and the encrypted data */
    $encryptedParts = explode(/*delimiter*/',', $encrypted, /*limit*/2);
    $iv = base64_decode($encryptedParts[0]);
    if(FALSE === $iv) {
      return FALSE;
    }

    $encryptedData = base64_decode($encryptedParts[1]);
    if(FALSE === $encryptedData) {
      return FALSE;
    }

    /* Decrypt the encrypted data */
    $decryptedResponse = openssl_decrypt($encryptedData, /*method*/'aes-256-cbc', $privateKey, /*options*/OPENSSL_RAW_DATA, $iv);
    if(FALSE === $decryptedResponse) {
      return FALSE;
    }

    /* JSON decode the decrypted data. */
    return json_decode($decryptedResponse, /*assoc*/TRUE, /*depth*/4);
  }


  /**
   * Function that validates a decripted response.
   *
   * @param tw_response The server decripted and JSON decoded response
   *
   * @return bool(FALSE)     - If any error occurs
   *         bool(TRUE)      - If the validation is successful
   */
  public function twispay_tw_checkValidation($tw_response) {
    $tw_errors = array();

    if(!$tw_response) {
      return FALSE;
    }

    if(empty($tw_response['status']) && empty($tw_response['transactionStatus'])) {
      $tw_errors[] = __('[RESPONSE-ERROR]: Empty status');
    }

    if(empty($tw_response['identifier'])) {
      $tw_errors[] = __('[RESPONSE-ERROR]: Empty identifier');
    }

    if(empty($tw_response['externalOrderId'])) {
      $tw_errors[] = __('[RESPONSE-ERROR]: Empty externalOrderId');
    }

    if(empty($tw_response['transactionId'])) {
      $tw_errors[] = __('[RESPONSE-ERROR]: Empty transactionId');
    }

    if(sizeof($tw_errors)) {
      foreach($tw_errors as $err) {
        $this->log->error($err);
      }

      return FALSE;
    } else {
      $data = [ 'externalOrderId' => explode('_', $tw_response['externalOrderId'])[0]
              , 'status'          => (empty($tw_response['status'])) ? ($tw_response['transactionStatus']) : ($tw_response['status'])
              , 'identifier'      => $tw_response['identifier']
              , 'orderId'         => (int)$tw_response['orderId']
              , 'transactionId'   => (int)$tw_response['transactionId']
              , 'customerId'      => (int)$tw_response['customerId']
              , 'cardId'          => (!empty($tw_response['cardId'])) ? (( int )$tw_response['cardId']) : (0)];

      $this->log->notice(__('[RESPONSE]: Data: ') . json_encode($data));

      if(!in_array($data['status'], $this->resultStatuses)){
        $this->log->error(__('[RESPONSE-ERROR]: Wrong status: ') . $data['status']);

        return FALSE;
      }

      $this->log->notice(__('[RESPONSE]: Validating completed for order ID: ') . $data['externalOrderId']);

      return TRUE;
    }
  }
  /************************** Response END **************************/



  /**
   * Prepares the request data to be sent to the Twispay gateway
   *
   * @param $orderId - The ID of the ordered to be payed.
   * @param $isGuest - Flag indicating if the order comes from
   *                    an authenticated customer or a guest.
   *
   * @return array $data - <Key, Value> array representing the JSON
   *                        to be sent to the payment gateway.
   */
  public function createRequest($orderId, $isGuest) {
    /* Get the details of the last order. */
    $order = $this->orderRepository->get($orderId);
    $this->log->debug(__FUNCTION__ . ': orderId=' . $orderId);

    /* Set order status to payment pending. */
    $order->setState(Order::STATE_PENDING_PAYMENT, true);
    $order->setStatus(Order::STATE_PENDING_PAYMENT);
    $order->addStatusToHistory($order->getStatus(), __('Redirecting to Twispay payment gateway'));
    $order->save();

    /* Read the configuration values. */
    $siteId = $this->config->getSiteId();
    $apiKey = $this->config->getPrivateKey();
    $url = $this->config->getRedirectUrl();

    if(('' == $siteId) || ('' == $apiKey)){
      $this->log->error(__('Payment failed: Incomplete or missing configuration.'));
      return FALSE;
    }
    $this->log->debug(__FUNCTION__ . ': siteId=' . $siteId . ' apiKey=' . $apiKey . ' url=' . $url);

    /* Extract the billind and shipping addresses. */
    $billingAddress = $order->getBillingAddress();
    $shippingAddress = $order->getShippingAddress();

    /* Extract the customer details. */
    $customer = [ 'identifier' => (TRUE == $isGuest) ? ('_' . $orderId . '_' . date('YmdHis')) : ('_' . $billingAddress->getCustomerId())
                , 'firstName' => ($billingAddress->getFirstname()) ? ($billingAddress->getFirstname()) : ($shippingAddress->getFirstname())
                , 'lastName' => ($billingAddress->getLastname()) ? ($billingAddress->getLastname()) : ($shippingAddress->getLastname())
                , 'country' => ($billingAddress->getCountryId()) ? ($billingAddress->getCountryId()) : ($shippingAddress->getCountryId())
                // , 'state' => (('US' == $billingAddress->getCountryId()) && (NULL != $billingAddress->getRegionCode())) ? ($billingAddress->getRegionCode()) : ((('US' == $shippingAddress->getCountryId()) && (NULL != $shippingAddress->getRegionCode())) ? ($shippingAddress->getRegionCode()) : (''))
                , 'city' => ($billingAddress->getCity()) ? ($billingAddress->getCity()) : ($shippingAddress->getCity())
                , 'address' => ($billingAddress->getStreet()) ? (join(', ', $billingAddress->getStreet())) : (join(', ', $shippingAddress->getStreet()))
                , 'zipCode' => ($billingAddress->getPostcode()) ? (preg_replace("/[^0-9]/", '', $billingAddress->getPostcode())) : (preg_replace("/[^0-9]/", '', $shippingAddress->getPostcode()))
                , 'phone' => ($billingAddress->getTelephone()) ? ('+' . preg_replace('/([^0-9]*)+/', '', $billingAddress->getTelephone())) : (($shippingAddress->getTelephone()) ? ('+' . preg_replace('/([^0-9]*)+/', '', $shippingAddress->getTelephone())) : (''))
                , 'email' => ($billingAddress->getEmail()) ? ($billingAddress->getEmail()) : ($shippingAddress->getEmail())
                /* , 'tags' => [] */
                ];

    /* Extract the items details. */
    $items = array();
    foreach($order->getAllVisibleItems() as $item){
      $items[] = [ 'item' => $item->getName()
                , 'units' =>  (int) $item->getQtyOrdered()
                , 'unitPrice' => (string) number_format((float) $item->getPriceInclTax(), 2, '.', '')
                /* , 'type' => '' */
                /* , 'code' => '' */
                /* , 'vatPercent' => '' */
                /* , 'itemDescription' => '' */
                ];
    }

    /* Check if shiping price needs to be added. */
    if(0 < $order->getShippingAmount()){
      $items[] = [ 'item' => "Transport"
                 , 'units' =>  1
                 , 'unitPrice' => (string) number_format((float) $order->getShippingAmount(), 2, '.', '')
                 ];
    }

    /* Calculate the order amount. */
    $amount = $order->getGrandTotal();
    $index = strpos($amount, '.');
    if(FALSE !== $index){
      $amount = substr($amount, 0, $index + 3);
    }

    /* Build the data object to be posted to Twispay. */
    $orderData = [ 'siteId' => $siteId
                 , 'customer' => $customer
                 , 'order' => [ 'orderId' => $orderId
                              , 'type' => 'purchase'
                              , 'amount' => $amount
                              , 'currency' => $order->getOrderCurrencyCode()
                              , 'items' => $items
                              /* , 'tags' => [] */
                              /* , 'intervalType' => '' */
                              /* , 'intervalValue' => 1 */
                              /* , 'trialAmount' => 1 */
                              /* , 'firstBillDate' => '' */
                              /* , 'level3Type' => '', */
                              /* , 'level3Airline' => [ 'ticketNumber' => '' */
                              /*                      , 'passengerName' => '' */
                              /*                      , 'flightNumber' => '' */
                              /*                      , 'departureDate' => '' */
                              /*                      , 'departureAirportCode' => '' */
                              /*                      , 'arrivalAirportCode' => '' */
                              /*                      , 'carrierCode' => '' */
                              /*                      , 'travelAgencyCode' => '' */
                              /*                      , 'travelAgencyName' => ''] */
                              ]
                 , 'cardTransactionMode' => 'authAndCapture'
                 /* , 'cardId' => 0 */
                 , 'invoiceEmail' => ''
                 , 'backUrl' => $this->getBackUrl()
                 /* , 'customData' => [] */
    ];

    /* Encode the data and calculate the checksum. */
    $base64JsonRequest = $this->getBase64JsonRequest($orderData);
    $this->log->debug(__FUNCTION__ . ': base64JsonRequest=' . $base64JsonRequest);
    $base64Checksum = $this->getBase64Checksum($orderData, $apiKey);
    $this->log->debug(__FUNCTION__ . ': base64Checksum=' . $base64Checksum);

    return ['jsonRequest' => $base64JsonRequest, 'checksum' => $base64Checksum];
  }



  /**
   * This method receives as a parameter the response from the Twispay gateway
   * and creates the transaction record
   *
   * @param $response
   * @throws PaymentException
   */
  public function processGatewayResponse($response)
  {
    $orderId = (int)$response->externalOrderId;
    $transactionId = (int)$response->transactionId;
    $timestamp = $response->timestamp;

    $details = $response->custom;
    $details['card_id'] = $response->cardId;
    $details['customer'] = $response->identifier;

    /** @var \Magento\Sales\Model\Order $order */
    $order = $this->orderRepository->get($orderId);

    if (empty($order) || !$order->getId()) {
        $this->log->error('Order don\'t exists in store', [$orderId]);
        throw new PaymentException(__('Order doesn\'t exists in store'));
    }

    // Add payment transaction
    $payment = $order->getPayment();
    $paymentMethod = $payment->getMethodInstance();

    if ($paymentMethod->getCode() !== \Twispay\Payments\Model\Twispay::METHOD_CODE) {
        $this->log->error('Unsupported payment method', [$paymentMethod->getCode()]);
        throw new PaymentException(__('Unsupported payment method'));
    }

    if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
        $payment->setTransactionId($transactionId);
        $payment->setLastTransId($transactionId);

        // Create the transaction
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
        $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
        $transaction->setCreatedAt($timestamp);
        $transaction->save();

        $payment->addTransactionCommentsToOrder(
            $transaction,
            __('The authorized amount is %1.', $order->getBaseCurrency()->formatTxt($order->getGrandTotal()))
        );
        $payment->setParentTransactionId(null);
        $payment->save();

        // Update the order state
        $order->setState(Order::STATE_PROCESSING, true);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setExtCustomerId($response->customerId);
        $order->setExtOrderId($response->orderId);
        $order->addStatusToHistory(
            $order->getStatus(),
            __('Order paid successfully with reference #%1', $transactionId)
        );

        $order->save();
    }
  }
}
