<?php

namespace Twispay\Payments\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Invoice;

/**
 * Helper class for everything that has to do with payment
 *
 * @package Twispay\Payments\Helper
 * @author Twispay
 */
class Payment extends \Magento\Framework\App\Helper\AbstractHelper {
  /** @var \Twispay\Payments\Model\Config */
  private $config;
  /** @var \Twispay\Payments\Logger\Logger */
  private $log;
  /** @var \Magento\Store\Model\StoreManagerInterface: Store manager object */
  private $storeManager;
  /** @var \Magento\Sales\Api\OrderRepositoryInterface */
  private $orderRepository;
  /** @var \Magento\Sales\Model\Service\InvoiceService */
  private $invoiceService;
  /** @var \Magento\Framework\DB\TransactionFactory */
  private $transactionFactory;
  /** @var \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory */
  private $transactions;
  /** @var \Magento\Framework\App\ObjectManager */
  private $objectManager;

  /* Array containing the possible result statuses. */
  private $resultStatuses = [ 'UNCERTAIN' => 'uncertain' /* No response from provider */
                            , 'IN_PROGRESS' => 'in-progress' /* Authorized */
                            , 'COMPLETE_OK' => 'complete-ok' /* Captured */
                            , 'COMPLETE_FAIL' => 'complete-failed' /* Not authorized */
                            , 'CANCEL_OK' => 'cancel-ok' /* Capture reversal */
                            , 'REFUND_OK' => 'refund-ok' /* Settlement reversal */
                            , 'VOID_OK' => 'void-ok' /* Authorization reversal */
                            , 'CHARGE_BACK' => 'charge-back' /* Charge-back received */
                            , 'THREE_D_PENDING' => '3d-pending' /* Waiting for 3d authentication */
                            , 'EXPIRING' => 'expiring' /* The recurring order has expired */
                            ];

  /************************** Inner functions START **************************/
  /**
   * Function that changes the state of an order and adds history comment.
   *
   * @param order: The purchase order to update.
   * @param state: The state to be set to the order.
   * @param status: The status to be set to the order.
   * @param comment: The comment to add to that status change.
   */
  private function setOrderState($order, $state, $status, $comment){
    /* Set the state of the order. */
    $order->setData('state', $state);
    $order->setStatus($status);

    /* Add history comment. */
    $history = $order->addStatusToHistory($status, $comment, /*isCustomerNotified*/FALSE);

    /* Save changes. */
    $order->save();
  }
  /************************** Inner functions END **************************/



  /**
   * Constructor
   *
   * @param \Twispay\Payments\Model\Config $config
   * @param \Twispay\Payments\Logger\Logger $twispayLogger
   * @param \Magento\Framework\App\Helper\Context $context
   * @param \Magento\Store\Model\StoreManagerInterface $storeManager
   * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
   * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
   * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
   * @param \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactions
   */
  public function __construct( \Twispay\Payments\Model\Config $config
                             , \Twispay\Payments\Logger\Logger $twispayLogger
                             , \Magento\Framework\App\Helper\Context $context
                             , \Magento\Store\Model\StoreManagerInterface $storeManager
                             , \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
                             , \Magento\Sales\Model\Service\InvoiceService $invoiceService
                             , \Magento\Framework\DB\TransactionFactory $transactionFactory
                             , \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactions)
  {
    parent::__construct($context);
    $this->config = $config;
    $this->log = $twispayLogger;
    $this->storeManager = $storeManager;
    $this->orderRepository = $orderRepository;
    $this->invoiceService = $invoiceService;
    $this->transactionFactory = $transactionFactory;
    $this->transactions = $transactions;

    $this->objectManager = ObjectManager::getInstance();
  }


  /**
   * Function that extracts an order.
   *
   * @param orderId: The ID of the order to extarct.
   *
   * @return Magento\Sales\Model\Order if found
   *         NULL if not found
   */
  public function getOrder($orderId){
    try {
      $order = $this->orderRepository->get($orderId);
    } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
      $order = NULL;
    }

    return $order;
  }


  /**
   * Function that extracts an transaction.
   *
   * @param orderId: The ID of the order for which to extract the transaction.
   * @param txnId: The txnId of the transaction to be extracted.
   *
   * @return Magento\Sales\Model\Order\Payment\Transaction if found
   *         NULL if not found
   */
  public function getTransaction($orderId, $txnId){
    $transaction = NULL;

    foreach ($this->getTransactions($orderId) as $key => $_transaction) {
      if ($_transaction->getTxnId() == $txnId) {
        $transaction = $_transaction;
        break;
      }
    }

    return $transaction;
  }


  /**
   * Function that extracts a list of transactions for an order.
   *
   * @param orderId: The ID of the order for which to extarct
   *                  the transactions list.
   *
   * @return List of Magento\Sales\Model\Order\Payment\Transaction
   */
  public function getTransactions($orderId){
    return $this->transactions->create()->addOrderIdFilter($orderId)->getItems();
  }



  /************************** Notification START **************************/
  /**
   * Get the `jsonRequest` parameter (order parameters as JSON and base64 encoded).
   *
   * @param orderData: Array containing the order parameters.
   *
   * @return string
   */
  public function getJsonRequest(array $orderData) {
    return base64_encode(json_encode($orderData));
  }


  /**
   * Get the `checksum` parameter (the checksum computed over the `jsonRequest` and base64 encoded).
   *
   * @param orderData: The order parameters.
   * @param secretKey: The secret key (from Twispay).
   *
   * @return string
   */
  public function getChecksum(array $orderData, $secretKey) {
    $hmacSha512 = hash_hmac(/*algo*/'sha512', json_encode($orderData), $secretKey, /*raw_output*/true);
    return base64_encode($hmacSha512);
  }


  /**
   * Prepares the request data to be sent to the Twispay gateway
   *
   * @param orderId - The ID of the ordered to be payed.
   * @param isGuest - Flag indicating if the order comes from an authenticated customer or a guest.
   *
   * @return array([key => value,]) - Representing the JSON to be sent to the payment gateway.
   *         bool(FALSE)            - Otherwise
   */
  public function createPurchaseRequest($orderId, $isGuest) {
    /* Get the details of the last order. */
    $order = $this->orderRepository->get($orderId);
    $this->log->info(__FUNCTION__ . __(' Create payment request for order #%1', $orderId));

    /* Set order status to payment pending. */
    $order->setState(Order::STATE_PENDING_PAYMENT, true);
    $order->setStatus(Order::STATE_PENDING_PAYMENT);
    $order->addStatusToHistory($order->getStatus(), __(' Redirecting to Twispay payment gateway'));
    $order->save();

    /* Read the configuration values. */
    $siteId = $this->config->getSiteId();
    $apiKey = $this->config->getApiKey();
    if (('' == $siteId) || ('' == $apiKey)) {
      $this->log->error(__(' Payment failed: Incomplete or missing configuration.'));
      return FALSE;
    }

    /* Extract the billind and shipping addresses. */
    $billingAddress = $order->getBillingAddress();
    $shippingAddress = $order->getShippingAddress();

    /* Extract the customer details. */
    $customer = [ 'identifier' => (TRUE == $isGuest) ? ('p_' . $orderId . '_' . date('YmdHis')) : ('p_' . $billingAddress->getCustomerId() . '_' . date('YmdHis'))
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
    foreach ($order->getAllVisibleItems() as $item){
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
    if (0 < $order->getShippingAmount()) {
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
                 , 'backUrl' => $this->storeManager->getStore()->getBaseUrl() . $this->config->getBackUrl()
                 /* , 'customData' => [] */
    ];

    /* Encode the data and calculate the checksum. */
    $jsonRequest = $this->getJsonRequest($orderData);
    $checksum = $this->getChecksum($orderData, $apiKey);

    return ['jsonRequest' => $jsonRequest, 'checksum' => $checksum];
  }
  /************************** Notification END **************************/



  /************************** Response START **************************/
  /**
   * Decrypt the response from Twispay server.
   *
   * @param encryptedMessage: - The encripted server message.
   * @param secretKey:        - The secret key (from Twispay).
   *
   * @return Array([key => value,]) - If everything is ok array containing the decrypted data.
   *         bool(FALSE)            - If decription fails.
   */
  public function twispay_tw_decrypt_message($encryptedMessage, $secretKey) {
    $encrypted = (string)$encryptedMessage;

    if(!strlen($encrypted) || (FALSE === strpos($encrypted, ','))) {
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
    $decryptedResponse = openssl_decrypt($encryptedData, /*method*/'aes-256-cbc', $secretKey, /*options*/OPENSSL_RAW_DATA, $iv);
    if(FALSE === $decryptedResponse) {
      return FALSE;
    }

    /* JSON decode the decrypted data. */
    $decodedResponse = json_decode($decryptedResponse, /*assoc*/TRUE, /*depth*/4);

    /* Check if the decryption was successful. */
    if (NULL === $decodedResponse) {
      return FALSE;
    }

    return $decodedResponse;
  }


  /**
   * Function that validates a decripted response.
   *
   * @param response The server decripted and JSON decoded response
   *
   * @return bool(FALSE)     - If any error occurs
   *         bool(TRUE)      - If the validation is successful
   */
  public function twispay_tw_checkValidation($response) {
    $errors = array();

    if(!$response) {
      return FALSE;
    }

    if(empty($response['transactionStatus'])) {
      $errors[] = __(' [RESPONSE-ERROR]: Empty status');
    }

    if(empty($response['identifier'])) {
      $errors[] = __(' [RESPONSE-ERROR]: Empty identifier');
    }

    if(empty($response['externalOrderId'])) {
      $errors[] = __(' [RESPONSE-ERROR]: Empty externalOrderId');
    }

    if(empty($response['transactionId'])) {
      $errors[] = __(' [RESPONSE-ERROR]: Empty transactionId');
    }

    if(sizeof($errors)) {
      foreach($errors as $err) {
        $this->log->error(__FUNCTION__ . $err);
      }

      return FALSE;
    } else {
      $data = [ 'externalOrderId' => explode('_', $response['externalOrderId'])[0]
              , 'status'          => $response['transactionStatus']
              , 'identifier'      => $response['identifier']
              , 'orderId'         => (int)$response['orderId']
              , 'transactionId'   => (int)$response['transactionId']
              , 'customerId'      => (int)$response['customerId']
              , 'cardId'          => (!empty($response['cardId'])) ? (( int )$response['cardId']) : (0)];

      $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Data: ') . json_encode($data));

      if(!in_array($data['status'], $this->resultStatuses)){
        $this->log->error(__FUNCTION__ . __(' [RESPONSE-ERROR]: Wrong status: ') . $data['status']);

        return FALSE;
      }

      $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Validation completed for order ID: ') . $data['externalOrderId']);

      return TRUE;
    }
  }


  /**
   * Update the status of a purchase order according to the received server status.
   *
   * @param purchase: The purchase order for which to update the status.
   * @param transactionId: The unique server transaction ID of the purchase.
   * @param serverStatus: The status received from server.
   *
   * @return bool(FALSE)     - If server status in: [COMPLETE_FAIL, THREE_D_PENDING]
   *         bool(TRUE)      - If server status in: [IN_PROGRESS, COMPLETE_OK]
   */
  public function updateStatus_purchase_backUrl($purchase, $transactionId, $serverStatus){
    switch ($serverStatus) {
      case $this->resultStatuses['COMPLETE_FAIL']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_CANCELED
                            , Order::STATE_CANCELED
                            , __(' Order #%1 canceled as payment for transaction #%2 failed', $purchase->getIncrementId(), $transactionId));

        $this->log->error(__FUNCTION__ . __(' [RESPONSE]: Status failed for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['THREE_D_PENDING']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_PENDING_PAYMENT
                            , Order::STATE_PENDING_PAYMENT
                            , __(' Order #%1 pended as payment for transaction #%2 is pending', $purchase->getIncrementId(), $transactionId));

        $this->log->warning(__FUNCTION__ . __(' [RESPONSE]: Status three-d-pending for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['IN_PROGRESS']:
      case $this->resultStatuses['COMPLETE_OK']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_PROCESSING
                            , Order::STATE_PROCESSING
                            , __(' Order #%1 processing as payment for transaction #%2 is successful', $purchase->getIncrementId(), $transactionId));

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status complete-ok for order ID: ') . $purchase->getIncrementId());
        return TRUE;
      break;

      default:
        $this->log->error(__FUNCTION__ . __(' [RESPONSE-ERROR]: Wrong status: ') . $serverStatus);
        return FALSE;
      break;
    }
  }


  /**
   * Update the status of a purchase order according to the received server status.
   *
   * @param purchase: The purchase order for which to update the status.
   * @param transactionId: The unique transaction ID of the order.
   * @param serverStatus: The status received from server.
   *
   * @return bool(FALSE)     - If server status in: [COMPLETE_FAIL, CANCEL_OK, VOID_OK, CHARGE_BACK, THREE_D_PENDING]
   *         bool(TRUE)      - If server status in: [REFUND_OK, IN_PROGRESS, COMPLETE_OK]
   */
  public function updateStatus_purchase_IPN($purchase, $transactionId, $serverStatus){
    switch ($serverStatus) {
      case $this->resultStatuses['COMPLETE_FAIL']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_CANCELED
                            , Order::STATE_CANCELED
                            , __(' Order #%1 canceled as payment for transaction #%2 failed', $purchase->getIncrementId(), $transactionId));

        $this->log->error(__FUNCTION__ . __(' [RESPONSE]: Status failed for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['REFUND_OK']:
        /* Set order status. */
        if($purchase->getTotalPaid() > $purchase->getTotalRefunded()){
          $this->setOrderState( $purchase
                              , Order::STATE_PROCESSING
                              , Order::STATE_PROCESSING
                              , __(' Order #%1 processing as payment for transaction #%2 is partially refunded', $purchase->getIncrementId(), $transactionId));
        } else {
          $this->setOrderState( $purchase
                              , Order::STATE_CLOSED
                              , Order::STATE_CLOSED
                              , __(' Order #%1 closed as payment for transaction #%2 has been refunded', $purchase->getIncrementId(), $transactionId));
        }

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status refund-ok for order ID: ') . $purchase->getIncrementId());
        return TRUE;
      break;

      case $this->resultStatuses['CANCEL_OK']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_CANCELED
                            , Order::STATE_CANCELED
                            , __(' Order #%1 canceled as payment for transaction #%2 has been canceled', $purchase->getIncrementId(), $transactionId));

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status cancel-ok for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['VOID_OK']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_CANCELED
                            , Order::STATE_CANCELED
                            , __(' Order #%1 canceled as payment for transaction #%2 has been voided ok', $purchase->getIncrementId(), $transactionId));

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status void-ok for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['CHARGE_BACK']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_CANCELED
                            , Order::STATE_CANCELED
                            , __(' Order #%1 canceled as payment for transaction #%2 has been charged back', $purchase->getIncrementId(), $transactionId));

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status charge-back for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['THREE_D_PENDING']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_PENDING_PAYMENT
                            , Order::STATE_PENDING_PAYMENT
                            , __(' Order #%1 pended as payment for transaction #%2 is pending', $purchase->getIncrementId(), $transactionId));

        $this->log->warning(__FUNCTION__ . __(' [RESPONSE]: Status three-d-pending for order ID: ') . $purchase->getIncrementId());
        return FALSE;
      break;

      case $this->resultStatuses['IN_PROGRESS']:
      case $this->resultStatuses['COMPLETE_OK']:
        /* Set order status. */
        $this->setOrderState( $purchase
                            , Order::STATE_PROCESSING
                            , Order::STATE_PROCESSING
                            , __(' Order #%1 processing as payment for transaction #%2 is successful', $purchase->getIncrementId(), $transactionId));

        $this->log->info(__FUNCTION__ . __(' [RESPONSE]: Status complete-ok for order ID: ') . $purchase->getIncrementId());
        return TRUE;
      break;

      default:
        $this->log->error(__FUNCTION__ . __(' [RESPONSE-ERROR]: Wrong status: ') . $serverStatus);
        return FALSE;
      break;
    }
  }


  /**
   * Function that adds a new transaction to the order.
   *
   * @param order: The order to which to add the transaction.
   * @param serverResponse: Array containing the server decripted response.
   */
  public function addOrderTransaction($order, $serverResponse){
    /* Save the payment transaction. */
    $payment = $order->getPayment();
    $payment->setTransactionId($serverResponse['transactionId']);
    $payment->setLastTransId($serverResponse['transactionId']);
    $payment->setParentTransactionId(NULL);
    $transaction = $payment->addTransaction(Transaction::TYPE_CAPTURE, null, TRUE, 'OK');
    $transaction->setAdditionalInformation( Transaction::RAW_DETAILS
                                          , [ 'identifier'    => $serverResponse['identifier']
                                            , 'status'        => $serverResponse['status']
                                            , 'orderId'       => $serverResponse['orderId']
                                            , 'transactionId' => $serverResponse['transactionId']
                                            , 'customerId'    => $serverResponse['customerId']
                                            , 'cardId'        => $serverResponse['cardId']
                                            , 'storeId'       => $this->storeManager->getStore()->getId()]);
    // $transaction->setIsClosed(TRUE);
    $transaction->setCreatedAt($serverResponse['timestamp']);
    $transaction->save();
    $payment->save();

    $order->setExtCustomerId($serverResponse['customerId']);
    $order->setExtOrderId($serverResponse['orderId']);
    $order->save();
  }


  /**
   * Function that adds a transaction to an invoice.
   *
   * @param order: The order that has the transaction and the invoice.
   * @param transactionId: The ID of the transaction.
   */
  public function addPurchaseInvoice($order, $transactionId){
    /* Add the transaction to the invoice. */
    $invoice = $order->getInvoiceCollection()->addAttributeToSort('created_at', 'DSC')->setPage(1, 1)->getFirstItem();
    $invoice->setTransactionId($transactionId);
    $invoice->save();
  }

  /**
   * Create Invoice Based on Order Object
   *
   * @param order: The order that has the transaction and the invoice.
   * @param transactionId: The ID of the transaction.
   *
   * @return bool
   */
  public function generateInvoice($order, $transactionId){
    $this->log->info(__FUNCTION__ . __(': START'));
    /* Check if the order is cannot be invoiced. */
    // if(FALSE == $order->canInvoice()) {
    //   $this->log->info(__FUNCTION__ . __(': cannot invoice'));
    //   return FALSE;
    // }

    $invoice = $this->invoiceService->prepareInvoice($order);
    if (!$invoice || !$invoice->getTotalQty()) {
      $this->log->info(__FUNCTION__ . __(': null qty'));
      return FALSE;
    }

    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
    $invoice->register();
    $invoice->getOrder()->setCustomerNoteNotify(FALSE);
    $invoice->getOrder()->setIsInProcess(TRUE);
    $invoice->setTransactionId($transactionId);
    $invoice->save();
    $order->addStatusHistoryComment('Automatically INVOICED', FALSE);
    $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
    $transactionSave->save();

    return TRUE;
  }
  /************************** Response END **************************/
}
