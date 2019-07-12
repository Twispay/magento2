<?php
namespace Twispay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

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
  public function getConfig(){
    $outConfig = [
        'payment' => [
            \Twispay\Payments\Model\Twispay::METHOD_CODE => [
                'redirect_url' => $this->config->getRedirectUrl(),
            ]
        ]
    ];

    return $outConfig;
  }
}
