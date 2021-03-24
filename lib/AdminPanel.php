<?php

namespace OCA\QNAP;

use OCP\IUser;
use OCP\Settings\ISettings;
use OCP\Template;

class AdminPanel implements ISettings {
	public function getPriority() {
		return 10;
	}

	public function getPanel() {
		$license = new QnapLicense();
		$tmpl = new Template('qnap', 'settings-admin');
		$tmpl->assign('licenses', $license->getLicenses());
		$tmpl->assign('licensed_users', $license->getUserAllowance());
		$tmpl->assign('active_users', $this->getUserCount());
#		foreach ($params as $key => $value) {
#			$tmpl->assign($key, $value);
#		}
		return $tmpl;
	}

	public function getSectionID() {
		return 'security';
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
