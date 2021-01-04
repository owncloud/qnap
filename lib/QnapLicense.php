<?php

namespace OCA\QNAP;

use OC\License\ILicense;

class QnapLicense implements ILicense {
	public function getLicenseString(): string {
		return 'qnap-license';
	}

	public function isValid(): bool {
		return true;
	}

	public function getExpirationTime(): int {
		// TODO: read from qnap license
		$date = new \DateTime('2021-01-01');
		return $date->getTimestamp();
	}

	public function getType(): int {
		// TODO: return NORMAL as soon as the EE license was purchased
		return self::LICENSE_TYPE_DEMO;
	}
}
