<?php
namespace Twispay\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Config
     */
    protected $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $outConfig = [
            'payment' => [
                'twispay' => [
                    'redirect_url' => $this->config->getRedirectUrl(),
                    'back_url' => $this->config->getBackUrl()
                ]
            ]
        ];
        return $outConfig;
    }
}
