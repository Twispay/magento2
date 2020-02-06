<?php
namespace Twispay\Payments\Plugin;

/**
 * Twispay payment method  CSRF validator
 *
 * @category    Twispay\Payments\Plugin
 * @package     Twispay_Payments
 * @author      Twispay
 */
class CsrfValidatorSkip {
  /**
   * @param \Magento\Framework\App\Request\CsrfValidator $subject
   * @param \Closure $proceed
   * @param \Magento\Framework\App\RequestInterface $request
   * @param \Magento\Framework\App\ActionInterface $action
   */
  public function aroundValidate($subject, \Closure $proceed, $request, $action) {
    if ($request->getModuleName() == 'twispay') {
      /* Skip CSRF check. */
      return;
    } else {
      /* Proceed Magento 2 core functionalities. */
      $proceed($request, $action);
    }
  }
}
