<?php

namespace Twispay\Payments\Logger;

/**
 * Handler for Twispay logs
 * @package Twispay\Payments\Logger
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
	/**
	 * Logging level
	 * @var int
	 */
	protected $loggerType = \Monolog\Logger::DEBUG;

	/**
	 * File name
	 * @var string
	 */
	protected $fileName = '/var/log/twispay.log';
}