<?php
namespace Twispay\Payments\Model;

class Config{
  /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
  private $scopeConfigInterface;

  /* The URLs for production and staging. */
  private $live_host_name = 'https://secure.twispay.com';
  private $stage_host_name = 'https://secure-stage.twispay.com';

  /* The URLs for production and staging API. */
  private $live_api_host_name = 'https://api.twispay.com';
  private $stage_api_host_name = 'https://api-stage.twispay.com';


  /**
   * Function used for reading a config value.
   */
  private function getConfigValue($value){
    return $this->scopeConfigInterface->getValue('payment/twispay/' . $value);
  }


  public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $configInterface){
    $this->scopeConfigInterface = $configInterface;
  }


  /**
   * Function that extracts the value of the "liveMode" from
   *  the config.
   */
  public function getLiveMode(){
    return $this->getConfigValue('live_mode');
  }


  /**
   * Function that extracts the value "contact_email" from
   *  the config.
   */
  public function getContactEmail(){
    return $this->getConfigValue('contact_email');
  }


  /**
   * Function that extracts the value "email_invoice" from
   *  the config.
   */
  public function getEmailInvoice(){
    return ($this->getConfigValue('email_invoice')) ? (TRUE) : (FALSE);
  }


  /**
   * Function that extracts the value "success_page" from
   *  the config.
   */
  public function getSuccessPage(){
    return $this->getConfigValue('success_page');
  }


  /**
   * Function that extracts the value of the "apiKey" from
   *  the config depending of the "liveMode" value.
   */
  public function getApiKey(){
    if ($this->getLiveMode()) {
      return $this->getConfigValue('live_private_key');
    } else {
      return $this->getConfigValue('staging_private_key');
    }
  }


  /**
   * Function that extracts the value of the "siteId" from
   *  the config depending of the "liveMode" value.
   */
  public function getSiteId(){
    if ($this->getLiveMode()) {
      return $this->getConfigValue('live_site_id');
    } else {
      return $this->getConfigValue('staging_site_id');
    }
  }


  /**
   * Function that extracts the value of the "url"
   *  depending of the "liveMode" value.
   */
  public function getRedirectUrl(){
    if(1 == $this->getLiveMode()){
      return $this->live_host_name;
    } else {
      return $this->stage_host_name;
    }
  }


  /**
   * Function that extracts the value of the "api url"
   *  depending of the "liveMode" value.
   */
  public function getApiUrl(){
    if(1 == $this->getLiveMode()){
      return $this->live_api_host_name;
    } else {
      return $this->stage_api_host_name;
    }
  }


  /**
   * Function that returns the backUrl.
   */
  public function getBackUrl(){
    return 'twispay/payment/backurl';
  }
}
