<?php

namespace OCA\QNAP;

use OCP\AppFramework\Utility\ITimeFactory;

class LicenseParser {
	public const DATETIME_FORMAT = 'Y-m-d H:i:s.u';

	private $activeLicenses = [];
	private $expiredLicenses = [];
	private $futureLicenses = [];

	/**
	 * @var ITimeFactory
	 */
	private $timeFactory;

	public function __construct(ITimeFactory $timeFactory) {
		$this->timeFactory = $timeFactory;
	}

	public function loadLicensesText(string $licenseText): void {
		$licensesString = \base64_decode($licenseText, true);
		if ($licensesString === false) {
			return;
		}

		$licenses = \json_decode($licensesString, true);
		if ($licenses === null) {
			return;
		}

		$now = $this->timeFactory->getTime();
		foreach ($licenses as $r) {
			$license =  \json_decode($r['license_info_json_str'] ?? '{}', true);
			$validFrom = \DateTime::createFromFormat(self::DATETIME_FORMAT, $license['valid_from']); // @phan-suppress-current-line PhanTypeArraySuspiciousNullable
			$validUntil = \DateTime::createFromFormat(self::DATETIME_FORMAT, $license['valid_until']); // @phan-suppress-current-line PhanTypeArraySuspiciousNullable
			# invalid license information
			# TODO: log
			if ($validFrom === false || $validUntil === false) {
				continue;
			}
			$license['valid_from'] = $validFrom;
			$license['valid_until'] = $validUntil;

			$r['license_info'] = $license;

			# future license
			if ($validFrom->getTimestamp() > $now) {
				$this->futureLicenses[]= $r;
				continue;
			}
			# expired licenses
			if ($this->timeFactory->getTime() > $validUntil->getTimestamp()) {
				$this->expiredLicenses[]= $r;
				continue;
			}
			if ($r['status'] !== "valid") {
				$this->expiredLicenses[]= $r;
				continue;
			}
			# active licenses
			$this->activeLicenses[]= $r;
		}
	}

	public function isValid(): bool {
		return !empty($this->activeLicenses);
	}

	public function getExpirationTime(): int {
		# if there is at least one active license -> use that as expiration
		if (!empty($this->activeLicenses)) {
			$expirations = \array_map(function ($l) {
				/** @var \DateTime $validUntil */
				$validUntil = $l['license_info']['valid_until'];
				return $validUntil->getTimestamp();
			}, $this->activeLicenses);
			return \min($expirations);
		}
		# if there is no active license -> use expired licenses
		if (!empty($this->expiredLicenses)) {
			$expirations = \array_map(function ($l) {
				/** @var \DateTime $validUntil */
				$validUntil = $l['license_info']['valid_until'];
				return $validUntil->getTimestamp();
			}, $this->expiredLicenses);
			return \min($expirations);
		}

		# neither active nor expired licenses at hand
		return 0;
	}

	public function getUserAllowance(): int {
		$allowance = 0;
		foreach ($this->activeLicenses as $license) {
			$allowance += $this->getLicenseUserAllowance($license);
		}
		return $allowance;
	}

	private function getLicenseUserAllowance($license): int {
		return (int)($license['license_info']['attributes']['owncloud_account'] ?? 0);
	}

	public function getLicenses(): array {
		return \array_merge($this->activeLicenses, $this->expiredLicenses, $this->futureLicenses);
	}
}
