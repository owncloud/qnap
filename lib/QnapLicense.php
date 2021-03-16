<?php

namespace OCA\QNAP;

use OC\License\ILicense;

use \DateTime;

class QnapLicense implements ILicense {
	const LICENSESpath = '/mnt/licenses/owncloud.json';

	const SKUlist = [
		"LS-OWNCLOUD-5U-1Y-EI",
	];

	const MinUserAllowance = 5;

	const TIMEformat = 'Y-m-d H:i:s.u';

	private $licenses = null;

	public function __construct() {
		$this->loadLicensesFile();
	}

	private function loadLicensesFile() {
		$licensesFile = \file_get_contents(self::LICENSESpath);
		if ($licensesFile === false) {
			return;
		}
		$cmdOutput = \json_decode($licensesFile, true);
		if ($cmdOutput === null) {
			return;
		}

		if (!\array_key_exists('result', $cmdOutput)) {
			return;
		}

		$this->licenses = $cmdOutput['result'];
		if ($this->licenses === null) {
			return;
		}

		foreach ($this->licenses as &$license) {
			$this->parseLicenseInfo($license);
		}

		// debug print all licenses after processing
		//echo json_encode($this->licenses, JSON_PRETTY_PRINT);
	}

	private function parseLicenseInfo(&$license) {
		if (!\array_key_exists('license_info_json_str', $license)) {
			return;
		}
		$licenseInfoStr = $license['license_info_json_str'];
		if ($licenseInfoStr === null) {
			return;
		}
		$licenseInfo = \json_decode($licenseInfoStr, true);
		if ($licenseInfo === null) {
			return;
		}

		$license['license_info'] = $licenseInfo;
		unset($license['license_info_json_str']);
	}

	public function getLicenseString(): string {
		return 'qnap-license';
	}

	public function isValid(): bool {
		foreach ($this->licenses as &$license) {
			if ($this->isLicenseValid($license)) {
				return true;
			}
		}
		return false;
	}

	private function isLicenseValid(&$license): bool {
		if (!\array_key_exists('license_info', $license)) {
			return false;
		}

		if (!\in_array($license['license_info']['sku'], self::SKUlist)) {
			return false;
		}

		$validFrom = DateTime::createFromFormat(self::TIMEformat, $license['license_info']['valid_from']);

		if (!$validFrom instanceof DateTime) {
			return false;
		}

		$now = (new DateTime('NOW'))->getTimestamp();

		if ($now > $this->getLicenseExpirationTime($license) || $validFrom->getTimestamp() > $now) {
			return false;
		}

		return true;
	}

	public function getExpirationTime(): int {
		$expirations = [];
		foreach ($this->licenses as &$license) {
			if ($this->isLicenseValid($license)) {
				$expirations[] = $this->getLicenseExpirationTime($license);
			}
		}

		if (\count($expirations) > 0) {
			return \min($expirations);
		}
		return 0;
	}

	private function getLicenseExpirationTime(&$license): int {
		if (!\array_key_exists('license_info', $license)) {
			return 0;
		}

		$validUntil = DateTime::createFromFormat(self::TIMEformat, $license['license_info']['valid_until']);
		if (!$validUntil instanceof DateTime) {
			return 0;
		}

		return $validUntil->getTimestamp();
	}

	public function getUserAllowance(): int {
		$allowance = 0;
		foreach ($this->licenses as &$license) {
			if ($this->isLicenseValid($license)) {
				$allowance = $allowance + $this->getLicenseUserAllowance($license);
			}
		}
		return \max($allowance, self::MinUserAllowance);
	}

	private function getLicenseUserAllowance(&$license): int {
		if (!\array_key_exists('license_info', $license)) {
			return 0;
		}
		if (!\array_key_exists('attributes', $license['license_info'])) {
			return 0;
		}
		if (!\array_key_exists('owncloud_account', $license['license_info']['attributes'])) {
			return 0;
		}

		$userAllowance = $license['license_info']['attributes']['owncloud_account'];
		if (!\is_numeric($userAllowance)) {
			return 0;
		}

		return $userAllowance;
	}

	public function getType(): int {
		if ($this->isValid()) {
			return self::LICENSE_TYPE_NORMAL;
		}
		return self::LICENSE_TYPE_DEMO;
	}
}
