<?php

namespace OCA\QNAP;

use OC\Helper\UserTypeHelper;
use OCP\IUser;
use OCP\IUserManager;
use OCP\License\Exceptions\LicenseManagerException;
use OCP\License\ILicenseManager;
use OCP\Settings\ISettings;
use OCP\Template;

class AdminPanel implements ISettings {
	/** @var ILicenseManager */
	private $licenseManager;

	/** @var IUserManager */
	private $userManager;

	/** @var UserTypeHelper */
	private $userTypeHelper;

	public function __construct(ILicenseManager $licenseManager, IUserManager $userManager, UserTypeHelper $userTypeHelper) {
		$this->licenseManager = $licenseManager;
		$this->userManager = $userManager;
		$this->userTypeHelper = $userTypeHelper;
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
		return $tmpl;
	}

	public function getSectionID() {
		return 'general';
	}

	private function getUserCount(): int {
		$numberOfActiveUsers = 0;
		$this->userManager->callForAllUsers(function (IUser $user) use (&$numberOfActiveUsers) {
			if ($user->isEnabled()) {
				if ($this->userTypeHelper->isGuestUser($user->getUID()) === false) {
					$numberOfActiveUsers++;
				}
			}
		});
		return $numberOfActiveUsers;
	}
}
