<?php
namespace Twispay\Payments\Service\V1;

use Twispay\Payments\Api\TwispayPaymentDetailsInterface;
use \Magento\Sales\Model\Order;

/**
 * Class TwispayPaymentDetails
 * @package Twispay\Payments\Service\V1
 * @author Webliant Software
 */
class TwispayPaymentDetails implements TwispayPaymentDetailsInterface
{

    /**
     * Factory for the response object
     *
     * @var \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory
     */
    private $responseFactory;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \Twispay\Payments\Model\Config
     */
    private $config;

    /**
     * @var \Twispay\Payments\Helper\Payment
     */
    private $helper;

    /**
     * @var \Twispay\Payments\Logger\Logger
     */
    private $log;

    /**
     * Constructor
     *
     * @param \Twispay\Payments\Model\Config $config
     * @param \Twispay\Payments\Helper\Payment $helper
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Twispay\Payments\Logger\Logger $twispayLogger
     * @param \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
     */
    public function __construct(
        \Twispay\Payments\Model\Config $config,
        \Twispay\Payments\Helper\Payment $helper,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Twispay\Payments\Logger\Logger $twispayLogger,
        \Twispay\Payments\Service\V1\Data\OrderPaymentResponseFactory $responseFactory
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->checkoutHelper = $checkoutHelper;
        $this->log = $twispayLogger;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Generates the payment details for the given order
     *
     * @return \Twispay\Payments\Service\V1\Data\OrderPaymentResponse
     */
    public function getPaymentDetails()
    {
        $this->log->debug(print_r($this->checkoutHelper->getCheckout()->toArray(), true));
        $data = $this->checkoutHelper->getCheckout()->getTwispayDaya();

        $oResponse = $this->responseFactory->create();
        foreach ($data as $key => $value) {
            $oResponse->setData($key, $value);
        }

        $oResponse->setData('success', true);

        return $oResponse;
    }
}
