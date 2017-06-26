<?php

namespace Twispay\Payments\Test\Unit\Helper;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;


class PaymentTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @var \Magento\Framework\App\Helper\Context
	 */
	private $contextMock;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $storeManagerMock;

	/**
	 * @var \Twispay\Payments\Logger\Logger
	 */
	protected $logMock;

	/**
	 * @var \Twispay\Payments\Model\Config
	 */
	private $configMock;

	/**
	 * @var \Twispay\Payments\Helper\Payment
	 */
	private $helper;

	/**
	 * @var ObjectManagerHelper
	 */
	protected $objectManagerHelper;

	/**
	 * test setup
	 */
	public function setUp()
	{
		$this->configMock = $this->getMockBuilder('\Twispay\Payments\Model\Config')
			->disableOriginalConstructor()
			->getMock();

		$this->logMock = $this->getMockBuilder('\Twispay\Payments\Logger\Logger')
			->disableOriginalConstructor()
			->setMethods(array('debug', 'info', 'error'))
			->getMock();

		$this->storeManagerMock = $this->getMockBuilder('\Magento\Store\Model\StoreManagerInterface')
			->disableOriginalConstructor()
			->getMock();

		$this->contextMock = $this->getMockBuilder('\Magento\Framework\App\Helper\Context')
			->disableOriginalConstructor()
			->getMock();

		$this->objectManagerHelper = new ObjectManagerHelper($this);

		$this->helper = $this->objectManagerHelper->getObject(
			'\Twispay\Payments\Helper\Payment',
			[
				'context' => $this->contextMock,
				'config' => $this->configMock,
				'twispayLogger' => $this->logMock,
				'storeManager' => $this->storeManagerMock
			]
		);
	}

	public function testComputeChecksum()
	{
		$this->logMock->expects($this->any())
			->method('debug')
			->will($this->returnCallback(
				function ($message) {
					echo $message;
					echo PHP_EOL;
				}
			));

		$this->configMock->expects($this->once())
			->method('getApiKey')
			->willReturn('c198a361a19f6d351c81b2cca955ef1e');

		$data = [
			'address' => '',
			'amount' => '44.00',
			'backUrl' => 'http://local.melarium.ro/magento/twispay/checkout/backpayment',
			'cardTransactionMode' => 'authAndCapture',
			'city' => 'Calder',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'roni_cost@example.com',
			'firstName' => 'Veronica',
			'identifier' => '_1',
			'item' =>
				array (
					0 => 'Layla Tee',
					1 => 'Shipping'
				),
			'lastName' => 'Costello',
			'orderId' => '119',
			'orderType' => 'purchase',
			'phone' => '5552343456',
			'siteId' => '127',
			'state' => 'MN',
			'subTotal' =>
				array (
					0 => '29.00',
					1 => '15.00'
				),
			'unitPrice' =>
				array (
					0 => '29.00',
					1 => '15.00'
				),
			'units' =>
				array (
					0 => 1,
					1 => ''
				),
			'zipCode' => '49628123'
		];

		$this->assertEquals('AguFd+K3Vn9rs315E0PWEHN9DooLtxl3STaXt/9IQKbmZj/+Id9GiGKIxSdqXdg+9RYGwxFH3ZIgs45Gf7P6kA==', $this->helper->computeChecksum($data)) ;
	}

}