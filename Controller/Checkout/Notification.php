<?php

namespace Twispay\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Controller\ResultFactory;

/**
 * This controller handles the server to server notification
 *
 * @package Twispay\Payments\Controller\Checkout
 */
class Notification extends Action
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Twispay\Payments\Logger\Logger
     */
    private $log;

    /**
     * @var \Twispay\Payments\Helper\Payment
     */
    private $helper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param ResultFactory $resultFactory
     * @param \Twispay\Payments\Logger\Logger $twispayLogger
     * @param \Twispay\Payments\Helper\Payment $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        ResultFactory $resultFactory,
        \Twispay\Payments\Logger\Logger $twispayLogger,
        \Twispay\Payments\Helper\Payment $helper
    ) {

        $this->resultFactory = $resultFactory;
        $this->log = $twispayLogger;
        $this->helper = $helper;

        parent::__construct($context);
    }

    /**
     * Handle the server-to-server notification
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $this->log->info("Received server to server notification.");

        $oResponse = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $response = $this->getRequest()->getParams();

        $result = null;
        if (array_key_exists('opensslResult', $response)) {
            try {
                $result = $this->helper->decryptResponse($response['opensslResult']);

                if ($result != null) {
                    $result = json_decode($result);

                    $this->log->debug(var_export($result, true));
                } else {
                    $this->log->error("Decoded response is NULL");
                }
            } catch (\Exception $ex) {
                $this->log->error($ex->getMessage(), $ex);
            }
        }

        if ($result && isset($result->status) &&
            ($result->status == 'complete-ok' || $result->status == 'in-progress')) {
            try {
                $this->helper->processGatewayResponse($result);
                $oResponse->setContents('OK');
            } catch (PaymentException $e) {
                $this->log->error($e->getMessage(), $e);
                $oResponse->setContents('ERROR');
            }
        } else {
            $oResponse->setContents('ERROR');
        }

        return $oResponse;
    }
}
