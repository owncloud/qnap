<?php

namespace OCA\QNAP;

use OC\License\ILicense;

use \DateTime;

class QnapLicense // implements ILicense
{
	const LICENSEpath = '/mnt/license/owncloud.json';

	const SKUlist = [
		"TODO-owncloud-sku",
	];

	const TIMEformat = 'Y-m-d H:i:s.u';

	private $license = null;
	private $licenseInfo = null;

	public function __construct() {
		$this->loadLicense();
	}

	private function loadLicense() {
		$licenseFile = \file_get_contents(self::LICENSEpath);
		if ($licenseFile === false) {
			return;
		}
		$this->license = \json_decode($licenseFile, true);
		if ($this->license === null) {
			return;
		}

		$licenseInfoStr = $this->license['license_info_json_str'];
		if ($licenseInfoStr === null) {
			return;
		}
		$this->licenseInfo = \json_decode($licenseInfoStr, true);
		if ($this->licenseInfo === null) {
			return;
		}
	}

	public function getLicenseString(): string {
		return 'qnap-license';
	}

	public function isValid(): bool {
		if ($this->licenseInfo === null) {
			return false;
		}

		if (!\in_array($this->licenseInfo['sku'], self::SKUlist)) {
			return false;
		}

		$validFrom = DateTime::createFromFormat(self::TIMEformat, $this->licenseInfo['valid_from']);

		if (!$validFrom instanceof DateTime) {
			return false;
		}

		$now = (new DateTime('NOW'))->getTimestamp();

		if ($now > $this->getExpirationTime() || $validFrom->getTimestamp() > $now) {
			return false;
		}

		return true;
	}

	public function getExpirationTime(): int {
		if ($this->licenseInfo == null) {
			return 0;
		}

		$validUntil = DateTime::createFromFormat(self::TIMEformat, $this->licenseInfo['valid_until']);
		if (!$validUntil instanceof DateTime) {
			return 0;
		}

		return $validUntil->getTimestamp();
	}

	public function getType(): int {
		if ($this->isValid()) {
			return self::LICENSE_TYPE_NORMAL;
		}
		return self::LICENSE_TYPE_DEMO;
	}
}
