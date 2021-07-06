<?php

namespace OCA\QNAP\Tests\Unit;

use OC\Helper\UserTypeHelper;
use OC\Template\Base as BaseTemplate;
use OCA\QNAP\AdminPanel;
use OCA\QNAP\QnapLicense;
use OCP\IUser;
use OCP\IUserManager;
use OCP\License\ILicenseManager;
use Test\TestCase;

class AdminPanelTest extends TestCase {
	/** @var AdminPanel */
	private $panel;

	/** @var ILicenseManager */
	private $licenseManager;

	/** @var IUserManager */
	private $userManager;

	/** @var UserTypeHelper */
	private $userTypeHelper;

	/** @var array */
	private $users;

	/** @var array */
	private $guestUsers;

	public function testPanelPriority(): void {
		self::assertEquals(17, $this->panel->getPriority());
	}

	public function testGetSectionID(): void {
		self::assertEquals('general', $this->panel->getSectionID());
	}

	public function testGetPanel(): void {
		$tmpl = new \ReflectionClass(BaseTemplate::class);
		$varsProp = $tmpl->getProperty('vars');
		$varsProp->setAccessible(true);

		$panel = $this->panel->getPanel();
		$vars = $varsProp->getValue($panel);

		$licenses = $vars["licenses"];
		$licensedUsers = $vars["licensed_users"];
		$activeUsers = $vars["active_users"];
		$activeGuestUsers = $vars["active_guest_users"];

		self::assertEquals(["test" => "test"], $licenses);
		self::assertEquals(10, $licensedUsers);
		self::assertEquals(1, $activeUsers);
		self::assertEquals(2, $activeGuestUsers);
	}

	private function defineUsers($users): void {
		foreach ($users as $id=>$user) {
			$u = $this->createMock(IUser::class);
			$u->method('getUID')->willReturn($id);
			$enabled=$user["enabled"];
			$u->method('isEnabled')->willReturn($enabled);
			$this->users[$id] = $u;
			$isGuest = $user['guest'];
			$this->guestUsers[$id] = $isGuest;
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$this->licenseManager = $this->createMock(ILicenseManager::class);
		$this->licenseManager->method("askLicenseFor")->will(
			$this->returnCallback(function ($arg1, $arg2) {
				if ($arg1 == 'core' && $arg2 == 'getLicenseClass') {
					return QnapLicense::class;
				} elseif ($arg1 == 'core' && $arg2 == 'getLicenses') {
					return ["test" => "test"];
				} elseif ($arg1 == 'core' && $arg2 == 'getUserAllowance') {
					return 10;
				} else {
					throw new Exception('Not implemented');
				}
			})
		);

		$this->userTypeHelper = $this->createMock(UserTypeHelper::class);
		$this->userTypeHelper->method("isGuestUser")->will(
			$this->returnCallback(function ($uid) {
				$isGuest = $this->guestUsers[$uid];
				return $isGuest;
			})
		);

		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method("callForAllUsers")->will(
			$this->returnCallback(function ($func) {
				foreach ((array) $this->users as $user) {
					$func($user);
				}
			})
		);

		$this->defineUsers(
			[
				0 => [
					'enabled' => true,
					'guest' => false,
				],
				1 => [
					'enabled' => true,
					'guest' => true,
				],
				2 => [
					'enabled' => true,
					'guest' => true,
				],
				3 => [
					'enabled' => false,
					'guest' => false,
				],
				4 => [
					'enabled' => false,
					'guest' => true,
				]
			]
		);

		$this->panel = new AdminPanel($this->licenseManager, $this->userManager, $this->userTypeHelper);
	}
}
