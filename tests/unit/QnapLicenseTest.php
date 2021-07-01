<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\QnapLicense;
use OCA\QNAP\LicenseParser;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class QnapLicenseTest extends TestCase {

	/**
	 * @var LicenseParser
	 */
	private $parser;

	/**
	 * @dataProvider providesLicenses
	 * @param bool $expectedValid
	 * @param int $expectedUsers
	 * @param int $expectedExpiration
	 * @param string $licenseStr
	 */
	public function test(bool $givenValid, int $givenUsers, bool $expectedValid, int $expectedUsers, int $expectedType): void {
		$ref = new \ReflectionClass('OCA\QNAP\QnapLicense');

		$license = new QnapLicense('');

		$this->parser->method('isValid')->willReturn($givenValid);
		$this->parser->method('getUserAllowance')->willReturn($givenUsers);

		$p = $ref->getProperty('licenseParser');
		$p->setAccessible(true);
		$p->setValue($license, $this->parser);

		self::assertEquals($expectedValid, $license->isValid());
		self::assertEquals($expectedType, $license->getType());
		self::assertEquals($expectedUsers, $license->getUserAllowance());
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
		$this->parser = $this->createMock(LicenseParser::class);
	}
}
