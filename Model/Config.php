<?php
namespace Twispay\Payments\Model;

class Config
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfigInterface;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $configInterface)
    {
        $this->_scopeConfigInterface = $configInterface;
    }

    public function getApiKey()
    {
        if ($this->isDebugMode()) {
            return $this->_scopeConfigInterface->getValue('payment/twispay/live_api_key');
        } else {
            return $this->_scopeConfigInterface->getValue('payment/twispay/test_api_key');
        }
    }

    public function getSiteId() {
        return $this->_scopeConfigInterface->getValue('payment/twispay/site_id');
    }

    public function isDebugMode() {
        return !!$this->_scopeConfigInterface->getValue('payment/twispay/debug');
    }

    public function getBackUrl() {
        return $this->_scopeConfigInterface->getValue('payment/twispay/success_page');
    }

}