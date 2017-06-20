<?php
/**
 * Twispay payment method model
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Webliant Software
 */
namespace Twispay\Payments\Model;



/**
 * Pay In Store payment method model
 */
class Twispay extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'twispay';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;


  

}
