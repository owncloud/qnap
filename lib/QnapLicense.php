<?php

namespace OCA\QNAP;

use OC\License\ILicense;

class QnapLicense implements ILicense {
	public const LICENSE_PATH = '/mnt/licenses/owncloud.json';

	/**
	 * @var LicenseParser
	 */
	private $licenseParser;

	public function __construct() {
		$this->licenseParser = new LicenseParser(\OC::$server->getTimeFactory());
		$this->licenseParser->loadLicensesFile(self::LICENSE_PATH);
	}

	public function getLicenseString(): string {
		return 'qnap-license';
	}

	public function isValid(): bool {
		return $this->licenseParser->isValid();
	}

	public function getExpirationTime(): int {
		return $this->licenseParser->getExpirationTime();
	}

	public function getUserAllowance(): int {
		return $this->licenseParser->getUserAllowance();
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
