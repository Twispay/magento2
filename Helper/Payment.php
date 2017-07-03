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
class Payment extends \Magento\Framework\App\Helper\AbstractHelper
{
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
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Twispay\Payments\Model\Config $config,
        \Twispay\Payments\Logger\Logger $twispayLogger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->log = $twispayLogger;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;

        $this->objectManager = ObjectManager::getInstance();
    }

    public function getBackUrl()
    {
        $backUrl = $this->config->getBackUrl();
        if (isset($backUrl) && trim($backUrl)!=='') {
            return $this->storeManager->getStore()->getBaseUrl() . $this->config->getBackUrl();
        }

        return "";
    }

    /**
     * This method computes the checksum on the given data array
     *
     * @param array $data
     * @return string the computed checksum
     */
    public function computeChecksum(array &$data)
    {
        // Get the API key from the cache to be used as an encryption key
        $apiKey = $this->config->getApiKey();

        // Sort the keys in the object alphabetically
        $this->recursiveKeySort($data);

        $this->log->debug(var_export($data, true));

        // Build an encoded HTTP query string from the data
        $query = http_build_query($data);

        $this->log->debug($query);

        // Encrypt the query string with SHA-512 algorithm
        $encoded = hash_hmac('sha512', $query, $apiKey, true);

        $checksum = base64_encode($encoded);

        $this->log->debug("Checksum: " . $checksum);

        return $checksum;
    }

    /**
     * Sort the array based on the keys
     * @param array $data
     */
    private function recursiveKeySort(array &$data)
    {
        ksort($data, SORT_STRING);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->recursiveKeySort($data[$key]);
            }
        }
    }

    /**
     * Prepares the request data to be sent to the Twispay gateway
     *
     * @param $orderId
     * @param $isGuestCustomer
     *
     * @return array $data
     */
    public function prepareGatewayRequest($orderId, $isGuestCustomer)
    {
        // Get the details of the last order
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderRepository->get($orderId);

        // Set the status of this order to pending payment
        $order->setState(Order::STATE_PENDING_PAYMENT, true);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($order->getStatus(), __('Redirecting to Twispay payment gateway'));
        $order->save();

        $address = $order->getBillingAddress();

        $items = $units = $unitPrice = $subTotal = [];
        foreach ($order->getAllVisibleItems() as $key => $item) {
            $items[$key] = $item->getName();
            $subTotal[$key] = (string)number_format((float)$item->getRowTotalInclTax(), 2, '.', '');
            $unitPrice[$key] = (string)number_format((float)$item->getPriceInclTax(), 2, '.', '');
            $units[$key] = (int)$item->getQtyOrdered();
        }

        // Add the shipping price
        if ($order->getShippingAmount() > 0) {
            $index             = count($items);
            $items[$index]     = __('Shipping')->render();
            $unitPrice[$index] = (string)number_format((float) $order->getShippingAmount(), 2, '.', '');
            ;
            $units[$index]     = "";
            $subTotal[$index]  = (string)number_format((float) $order->getShippingAmount(), 2, '.', '');
        }

        $emptyStringArray = [];
        $emptyStringArray[0] = "";

        $data = [
            'siteId' => (string)$this->config->getSiteId(),
            'orderId' => (string)(int)$orderId,
            'currency' => $order->getOrderCurrencyCode(),
            'amount' => (string)number_format((float)$order->getGrandTotal(), 2, '.', ''),
            'orderType' => $this->config->getOrderType(),
            'cardTransactionMode' => $this->config->getCardTransactionMode(),
            'firstName' => $address->getFirstname() != null ? $address->getFirstname() : '',
            'lastName' => $address->getLastname() != null ? $address->getLastname() : '',
            'city' => $address->getCity() != null ? $address->getCity() : '',
            'state' => (
                ($address->getCountryId() == 'US' && $address->getRegionCode() != null) ? $address->getRegionCode() : ''
            ),
            'country' => $address->getCountryId() != null ? $address->getCountryId() : '',
            'zipCode' => (
                $address->getPostcode() != null ? preg_replace("/[^0-9]/", '', $address->getPostcode()) : ''
            ),
            'address' => $address->getStreet() != null ? join(',', $address->getStreet()) : '',
            'email' => $address->getEmail() != null ? $address->getEmail() : '',
            'phone' => (
                $address->getTelephone() != null ? preg_replace("/[^0-9\+]/", '', $address->getTelephone()) : ''
            ),
            'item' => $items,
            'backUrl' => $this->getBackUrl(),
            'unitPrice' => $unitPrice,
            'units' => $units,
            'subTotal' => $subTotal,
            'identifier' => $isGuestCustomer ? $address->getEmail() : '_' . $address->getCustomerId()
        ];

        // Compute and add the checksum to the return array
        $data['checksum'] = $this->computeChecksum($data);

        return $data;
    }

    /**
     * This method computes the checksum on the given data array
     *
     * @param string $encrypted
     * @return array the decrypted response
     * @throws LocalizedException
     */
    public function decryptResponse($encrypted)
    {
        // Get the API key from the cache to be used as a decryption key
        $apiKey = $this->config->getApiKey();

        $encrypted = (string)$encrypted;
        if ($encrypted == "") {
            return null;
        }

        if (strpos($encrypted, ',') !== false) {
            $encryptedParts = explode(',', $encrypted, 2);

            // @codingStandardsIgnoreStart
            $iv = base64_decode($encryptedParts[0]);
            if (false === $iv) {
                throw new LocalizedException(__("Invalid encryption iv"));
            }
            $encrypted = base64_decode($encryptedParts[1]);
            if (false === $encrypted) {
                throw new LocalizedException(__("Invalid encrypted data"));
            }
            // @codingStandardsIgnoreEnd

            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $apiKey, OPENSSL_RAW_DATA, $iv);
            if (false === $decrypted) {
                throw new LocalizedException(__("Data could not be decrypted"));
            }

            return $decrypted;
        }

        return null;
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
