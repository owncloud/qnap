<?php

namespace OCA\QNAP;

use OCP\License\AbstractLicense;

class QnapLicense extends AbstractLicense {
	public const MIN_USER_ALLOWANCE = 5;

	/**
	 * @var LicenseParser
	 */
	private $licenseParser;

	public function __construct(string $licenseKey) {
		$this->licenseParser = new LicenseParser(\OC::$server->getTimeFactory());
		$this->licenseParser->loadLicensesText($licenseKey);
	}

	public function getLicenseString(): string {
		return 'qnap-license'; // license is stored by ownCloud not by QnapLicense
	}

	public function isValid(): bool {
		return $this->licenseParser->isValid();
	}

	public function getExpirationTime(): int {
		return $this->licenseParser->getExpirationTime();
	}

	public function getUserAllowance(): int {
		return \max($this->licenseParser->getUserAllowance(), self::MIN_USER_ALLOWANCE);
	}

	public function getType(): int {
		if ($this->isValid()) {
			return self::LICENSE_TYPE_NORMAL;
		}
		return self::LICENSE_TYPE_DEMO;
	}

	public function getLicenses(): array {
		return $this->licenseParser->getLicenses();
	}

	public function getProtectedMethods(): array {
		return ['getLicenseString'];
	}
}
