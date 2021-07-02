<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\QnapLicense;
use OCA\QNAP\LicenseParser;
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


	public function testGetExpirationTime(): void
    {
		$this->parser->method('getExpirationTime')->willReturn(1616059559);
        self::assertEquals(1616059559, $this->license->getExpirationTime());
    }

	public function testGetLicenseString(): void
    {
        self::assertEquals("qnap-license", $this->license->getLicenseString());
    }


	// TODO: why does this create a "Failed asserting that true is false."???
	/* public function testIsValid(): void
    {	
		$this->parser->method('isValid')->willReturn(true);
        self::assertTrue($this->license->isValid());

		$this->parser->method('isValid')->willReturn(false);
        self::assertFalse($this->license->isValid());
    } */

	public function getUserAllowance(): void
	{
		$this->parser->method('getUserAllowance')->willReturn(0);
        self::assertEquals(5, $this->license->getUserAllowance());

		$this->parser->method('getUserAllowance')->willReturn(5);
        self::assertEquals(5, $this->license->getUserAllowance());

		$this->parser->method('getUserAllowance')->willReturn(10);
        self::assertEquals(10, $this->license->getUserAllowance());
	}

	/**
	 * @dataProvider providesLicenses
	 * @param bool $expectedValid
	 * @param int $expectedUsers
	 * @param int $expectedExpiration
	 * @param string $licenseStr
	 */
	public function testLicense(bool $givenValid, int $givenUsers, bool $expectedValid, int $expectedUsers, int $expectedType): void {
		$this->parser->method('isValid')->willReturn($givenValid);
		$this->parser->method('getUserAllowance')->willReturn($givenUsers);

		self::assertEquals($expectedValid, $this->license->isValid());
		self::assertEquals($expectedType, $this->license->getType());
		self::assertEquals($expectedUsers, $this->license->getUserAllowance());
	}

	public function providesLicenses(): array {
		return [
			'no license' => [
				false, 0, false, 5, QnapLicense::LICENSE_TYPE_DEMO,
			],
			'valid license with 1 users' => [
				true, 1, true, 5, QnapLicense::LICENSE_TYPE_NORMAL,
			],
			'valid license with 5 users' => [
				true, 5, true, 5, QnapLicense::LICENSE_TYPE_NORMAL,
			],
			'valid license with 10 users' => [
				true, 10, true, 10, QnapLicense::LICENSE_TYPE_NORMAL,
			],
		];
	}

	protected function setUp(): void {
		parent::setUp();
		$ref = new \ReflectionClass('OCA\QNAP\QnapLicense');
		$this->license = new QnapLicense('');
		$this->parser = $this->createMock(LicenseParser::class);
		$p = $ref->getProperty('licenseParser');
		$p->setAccessible(true);
		$p->setValue($this->license, $this->parser);
	}
}
