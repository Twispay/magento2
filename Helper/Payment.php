<?php

namespace Twispay\Payments\Helper;

use Magento\Framework\Exception\LocalizedException;

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
	 * Constructor
	 *
	 * @param \Magento\Framework\App\Helper\Context $context
	 * @param \Twispay\Payments\Model\Config $config
	 * @param \Twispay\Payments\Logger\Logger $twispayLogger
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 */
	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Twispay\Payments\Model\Config $config,
		\Twispay\Payments\Logger\Logger $twispayLogger,
		\Magento\Store\Model\StoreManagerInterface $storeManager
	) {
		parent::__construct($context);
		$this->config = $config;
		$this->log = $twispayLogger;
		$this->storeManager = $storeManager;
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
				throw new LocalizedException("Invalid encryption iv");
			}
			$encrypted = base64_decode($encryptedParts[1]);
			if (false === $encrypted) {
				throw new LocalizedException("Invalid encrypted data");
			}
			$decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $apiKey, OPENSSL_RAW_DATA, $iv);
			if (false === $decrypted) {
				throw new LocalizedException("Data could not be decrypted");
			}

			return $decrypted;
		}

		return null;
		}
}