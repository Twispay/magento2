<?php

namespace Twispay\Payments\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Handler for Twispay logs
 * @package Twispay\Payments\Logger
 * @author Twispay
 * @codingStandardsIgnoreFile
 */
class Handler extends \Magento\Framework\Logger\Handler\Base {
  /**  @var int: Logging level */
  protected $loggerType = \Monolog\Logger::DEBUG;

  /** @var string: File name */
  protected $fileName = '/var/log/twispay.log';


  /**
   * Constructor
   *
   * @param DriverInterface $filesystem
   * @param string $filePath
   */
  public function __construct(DriverInterface $filesystem,$filePath = null) {
    parent::__construct($filesystem, $filePath);
    $this->setFormatter(new LineFormatter(null, null, true, true));
  }
}
