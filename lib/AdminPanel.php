<?php

namespace OCA\QNAP;

use OCP\IUser;
use OCP\Settings\ISettings;
use OCP\Template;
use OCP\License\Exceptions\LicenseManagerException;

class AdminPanel implements ISettings {
	public function getPriority() {
		return 17;
	}

	public function getPanel() {
		$licenseManager = \OC::$server->getLicenseManager();
		try {
			$classname = $licenseManager->askLicenseFor('core', 'getLicenseClass');
			$isQNAP = $classname === QnapLicense::class;
		} catch (LicenseManagerException $ex) {
			$isQNAP = false;
		}
		if ($isQNAP) {
			$tmpl = new Template('qnap', 'settings-admin');
			$tmpl->assign('licenses', $licenseManager->askLicenseFor('core', 'getLicenses'));
			$tmpl->assign('licensed_users', $licenseManager->askLicenseFor('core', 'getUserAllowance'));
			$tmpl->assign('active_users', $this->getUserCount());
			return $tmpl;
		}
		return null;
	}

	public function getSectionID() {
		return 'general';
	}

	private function getUserCount(): int {
		$numberOfActiveUsers = 0;
		\OC::$server->getUserManager()->callForAllUsers(function (IUser $user) use (&$numberOfActiveUsers) {
			if ($user->isEnabled()) {
				$numberOfActiveUsers++;
			}
		});

		return $numberOfActiveUsers;
	}
}
