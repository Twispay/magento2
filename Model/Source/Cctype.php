<?php
/**
 * Payment CC Types Source Model
 *
 * @category    Twispay
 * @package     Twispay_Payments
 * @author      Webliant Software
 */

namespace Twispay\Payments\Model\Source;

class Cctype extends \Magento\Payment\Model\Source\Cctype
{
    /**
     * @return array
     */
    public function getAllowedTypes()
    {
        return array('VI', 'MC');
    }
}
