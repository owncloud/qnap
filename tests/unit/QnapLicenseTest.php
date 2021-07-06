<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\LicenseParser;
use OCA\QNAP\QnapLicense;
use Test\TestCase;

class QnapLicenseTest extends TestCase {
	/**
	 * @var QnapLicense
	 */
	private $license;

	/**
	 * @var LicenseParser
	 */
	private $parser;

	public function testGetExpirationTime(): void {
		$this->parser->method('getExpirationTime')->willReturn(1616059559);
		self::assertEquals(1616059559, $this->license->getExpirationTime());
	}

	public function testGetLicenseString(): void {
		self::assertEquals("qnap-license", $this->license->getLicenseString());
	}

	public function testIsValid(): void {
		$this->parser->method('isValid')->willReturn(true);
		self::assertTrue($this->license->isValid());
	}

	public function testIsInvalid(): void {
		$this->parser->method('isValid')->willReturn(false);
		self::assertFalse($this->license->isValid());
	}

	public function testGetTypeNormal(): void {
		$this->parser->method('isValid')->willReturn(true);
		self::assertEquals(QnapLicense::LICENSE_TYPE_NORMAL, $this->license->getType());
	}

	public function testGetTypeDemo(): void {
		$this->parser->method('isValid')->willReturn(false);
		self::assertEquals(QnapLicense::LICENSE_TYPE_DEMO, $this->license->getType());
	}

	/**
	 * @dataProvider providesUserAllowance
	 * @param int $expectedUserAllowance
	 * @param int $givenUsersAllowance
	 */
	public function getUserAllowance(int $expectedUserAllowance, int $givenUsersAllowance): void {
		$this->parser->method('getUserAllowance')->willReturn($givenUsersAllowance);
		self::assertEquals($expectedUserAllowance, $this->license->getUserAllowance());
	}

	public function providesUserAllowance(): array {
		return [
			'no licensed users' => [
				5, 0,
			],
			'5 licensed users' => [
				5, 5,
			],
			'10 licensed users' => [
				10, 10
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();
		$ref = new \ReflectionClass(QnapLicense::class);
		$this->license = new QnapLicense('');
		$this->parser = $this->createMock(LicenseParser::class);
		$p = $ref->getProperty('licenseParser');
		$p->setAccessible(true);
		$p->setValue($this->license, $this->parser);
	}
}
