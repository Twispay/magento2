<?php
namespace Twispay\Payments\Model;

class Config
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfigInterface;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $configInterface
    ) {
    
        $this->_scopeConfigInterface = $configInterface;
    }

    public function getApiKey()
    {
        if ($this->isDebugMode()) {
            return $this->getConfigValue('test_api_key');
        } else {
            return $this->getConfigValue('live_api_key');
        }
    }

    public function getSiteId()
    {
        return $this->getConfigValue('site_id');
    }

    public function isDebugMode()
    {
        return !!$this->getConfigValue('debug');
    }

    public function getRedirectUrl()
    {
        if ($this->isDebugMode()) {
            return $this->getConfigValue('test_redirect_url');
        } else {
            return $this->getConfigValue('live_redirect_url');
        }
    }

    public function getBackUrl()
    {
        return $this->getConfigValue('back_url');
    }

    public function getSuccessPage()
    {
        return $this->getConfigValue('success_page');
    }

    public function getOrderType()
    {
        return $this->getConfigValue('order_type');
    }

    public function getCardTransactionMode()
    {
        return $this->getConfigValue('card_transaction_mode');
    }

    public function isEmailInvoice()
    {
        return !!$this->getConfigValue('email_invoice');
    }

    private function getConfigValue($value)
    {
        return $this->_scopeConfigInterface->getValue('payment/twispay/' . $value);
    }
}
