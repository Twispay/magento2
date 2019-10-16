<?php
namespace Twispay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Twispay payment method configuration provider
 *
 * @category    Twispay\Payments\Model
 * @package     Twispay_Payments
 * @author      Twispay
 * @codingStandardsIgnoreFile
 */
class ConfigProvider implements ConfigProviderInterface {
  /**
   * @var Config
   */
  private $config;

  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    $outConfig = ['payment' => [\Twispay\Payments\Model\Twispay::METHOD_CODE => ['redirect_url' => $this->config->getRedirectUrl()]]];

    return $outConfig;
  }
}
