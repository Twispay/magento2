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
        $outConfig = [];
		$outConfig = [
            'payment' => [
                'twispay' => [
                    'api_key' => $this->config->getApiKey(),
                    'site_id' => $this->config->getSiteId(),
                    'redirect_url' => $this->config->getRedirectUrl()
                ]
             ]
        ];
        return $outConfig;
    }
}