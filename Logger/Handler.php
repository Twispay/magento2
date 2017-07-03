<?php

namespace Twispay\Payments\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Handler for Twispay logs
 * @package Twispay\Payments\Logger
 * @codingStandardsIgnoreFile
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

    /**
     * Constructor
     *
     * @param DriverInterface $filesystem
     * @param string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        $filePath = null
    ) {
        parent::__construct($filesystem, $filePath);
        $this->setFormatter(new LineFormatter(null, null, true, true));
    }
}
