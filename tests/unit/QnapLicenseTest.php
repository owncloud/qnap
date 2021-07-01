<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\QnapLicense;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class QnapLicenseTest extends TestCase {

	/**
	 * @var QnapLicense
	 */
	private $license;

	/**
	 * @dataProvider providesLicenses
	 * @param bool $expectedValid
	 * @param int $expectedUsers
	 * @param int $expectedExpiration
	 * @param string $licenseStr
	 */
	public function test(bool $expectedValid, int $expectedType, int $expectedUsers, int $expectedExpiration, string $licenseStr): void {
		$this->license = new QnapLicense($licenseStr);

		self::assertEquals($expectedValid, $this->license->isValid());
		self::assertEquals($expectedType, $this->license->getType());
		self::assertEquals($expectedUsers, $this->license->getUserAllowance());
		self::assertEquals($expectedExpiration, $this->license->getExpirationTime());
	}

	public function providesLicenses(): array {
		return [
			'no license' => [
				false, QnapLicense::LICENSE_TYPE_DEMO, 5, 0, ''
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();
	}
}
