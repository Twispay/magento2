<?php

namespace Twispay\Payments\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Exception\PaymentException;

/**
 * Helper class for everything that has to do with payment
 */
class Payment extends \Magento\Framework\App\Helper\AbstractHelper
{
	/**
	 * Store manager object
	 *
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $storeManager;

	/**
	 * @var \Twispay\Payments\Logger\Logger
	 */
	protected $log;

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
	 * @param \Magento\Sales\Model\Order $order
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

		$this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	}

	public function getBackUrl() {
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
	public function computeChecksum(array &$data) {
		// Get the API key from the cache to be used as an encryption key
		$apiKey = $this->config->getApiKey();

		$this->log->debug($apiKey);

		// Sort the keys in the object alphabetically
		$this->recursiveKeySort($data);

		$this->log->debug(var_export($data, true));

		// Build an encoded HTTP query string from the data
		$query = http_build_query($data);

		$this->log->debug($query);

		// Encrypt the query string with SHA-512 algorithm
		$encoded = hash_hmac('sha512', $query, $apiKey, true);

		$checksum = base64_encode($encoded);

		$this->log->debug($checksum);

		return $checksum;
	}

	/**
	 * Sort the array based on the keys
	 * @param array $data
	 */
	private function recursiveKeySort(array &$data) {
		ksort($data, SORT_STRING);
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$this->recursiveKeySort($data[$key]);
			}
		}
	}

	/**
	 * This method computes the checksum on the given data array
	 *
	 * @param string $encrypted
	 * @return array the decrypted response
	 */
	public function decryptResponse($encrypted) {
		// Get the API key from the cache to be used as a decryption key
		$apiKey = $this->config->getApiKey();

		$encrypted = (string)$encrypted;
		if (!strlen($encrypted)) {
			return null;
		}

		if (strpos($encrypted, ',') !== false) {
			$encryptedParts = explode(',', $encrypted, 2);
			$iv = base64_decode($encryptedParts[0]);
			if (false === $iv) {
				throw new \Exception("Invalid encryption iv");
			}
			$encrypted = base64_decode($encryptedParts[1]);
			if (false === $encrypted) {
				throw new \Exception("Invalid encrypted data");
			}
			$decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $apiKey, OPENSSL_RAW_DATA, $iv);
			if (false === $decrypted) {
				throw new \Exception("Data could not be decrypted");
			}

			return $decrypted;
		}

		return null;
	}

	/**
	 * This method receives as a parameter the response from the Twispay gateway
	 * and
	 *
	 * @param $respose
	 *
	 * @throws PaymentException
	 */
	public function processGatewayResponse($response) {
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
		$paymentMethod = $order->getPayment()->getMethodInstance();

		if ($paymentMethod->getCode() !== 'twispay') {
			$this->log->error('Unsupported payment method', [$paymentMethod->getCode()]);
			throw new PaymentException(__('Unsupported payment method'));
		}

		if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
			$order->getPayment()->setTransactionId($transactionId);

			// Create the transaction
			/** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
			$transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
			$transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
			$transaction->setCreatedAt($timestamp);
			$transaction->save();

			// Update the order state
			$order->setState(Order::STATE_PROCESSING, true);
			$order->setStatus(Order::STATE_PROCESSING);
			$order->setExtCustomerId($response->customerId);
			$order->setExtOrderId($response->orderId);
			$order->addStatusToHistory($order->getStatus(), __('Order paid successfully with reference #%1', $transactionId));
			$order->save();
		}
	}

}