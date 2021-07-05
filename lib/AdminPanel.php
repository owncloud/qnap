<?php

namespace OCA\QNAP;

use OC\Helper\UserTypeHelper;
use OC\User\Manager;
use OCP\IUser;
use OCP\Settings\ISettings;
use OCP\License\ILicenseManager;
use OCP\Template;
use OCP\License\Exceptions\LicenseManagerException;

class AdminPanel implements ISettings {
	/**
	 * @var ILicenseManager
	 */
	private $licenseManager;

	/**
	 * @var Manager
	 */
	private $userManager;

	public function __construct() {
		$this->licenseManager = \OC::$server->getLicenseManager();
		$this->userManager = \OC::$server->getUserManager();
	}

	public function getPriority() {
		return 17;
	}

	public function getPanel() {
		try {
			$classname = $this->licenseManager->askLicenseFor('core', 'getLicenseClass');
			$isQNAP = $classname === QnapLicense::class;
		} catch (LicenseManagerException $ex) {
			$isQNAP = false;
		}

		$tmpl = new Template('qnap', 'settings-admin');
		if ($isQNAP) {
			$tmpl->assign('licenses', $this->licenseManager->askLicenseFor('core', 'getLicenses'));
			$tmpl->assign('licensed_users', $this->licenseManager->askLicenseFor('core', 'getUserAllowance'));
		} else {
			$tmpl->assign('licenses', []);
			$tmpl->assign('licensed_users', QnapLicense::MIN_USER_ALLOWANCE);
		}
		$tmpl->assign('active_users', $this->getUserCount());
		$tmpl->assign('active_guest_users', $this->getGuestUserCount());
		return $tmpl;
	}

	public function getSectionID() {
		return 'general';
	}

	private function getUserCount(): int {
		$numberOfActiveUsers = 0;
		$this->userManager->callForAllUsers(function (IUser $user) use (&$numberOfActiveUsers) {
			if ($user->isEnabled()) {
				$numberOfActiveUsers++;
			}
		});

		return $numberOfActiveUsers - $this->getGuestUserCount();
	}

	private function getGuestUserCount(): int {
		$userTypeHelper = new UserTypeHelper();

		$numberOfActiveGuestUsers = 0;
		$this->userManager->callForAllUsers(function (IUser $user) use (&$numberOfActiveGuestUsers, $userTypeHelper) {
			if ($user->isEnabled()) {
				if ($userTypeHelper->isGuestUser($user->getUID()) === true) {
					$numberOfActiveGuestUsers++;
				}
			}
		});

		return $numberOfActiveGuestUsers;
	}
}
