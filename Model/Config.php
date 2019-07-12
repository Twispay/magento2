<?php
namespace Twispay\Payments\Model;

class Config{
  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
  private $scopeConfigInterface;


  public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $configInterface) {
    $this->scopeConfigInterface = $configInterface;
  }


  public function getPrivateKey(){
    if ($this->liveMode()) {
      return $this->getConfigValue('live_private_key');
    } else {
      return $this->getConfigValue('staging_private_key');
    }
  }


  public function getSiteId(){
    if ($this->liveMode()) {
      return $this->getConfigValue('live_site_id');
    } else {
      return $this->getConfigValue('staging_site_id');
    }
  }


  public function liveMode(){
    return $this->getConfigValue('live_mode');
  }


  public function getRedirectUrl(){
    if ($this->liveMode()) {
      return $this->getConfigValue('live_redirect_url');
    } else {
      return $this->getConfigValue('staging_redirect_url');
    }
  }


  public function getBackUrl(){
    return trim($this->getConfigValue('back_url'));
  }


  public function getSuccessPage(){
    return $this->getConfigValue('success_page');
  }


  public function emailInvoice(){
    return $this->getConfigValue('email_invoice');
  }


  private function getConfigValue($value){
    return $this->scopeConfigInterface->getValue('payment/twispay/' . $value);
  }
}
