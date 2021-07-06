<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\AdminPanel;
use Test\TestCase;
use OCP\IUser;
use OCP\License\ILicenseManager;
use OC\Helper\UserTypeHelper;
use OCP\IUserManager;

class AdminPanelTest extends TestCase {
	/**
	 * @var AdminPanel
	 */
	private $panel;

	/**
	 * @var ILicenseManager
	 */
	private $licenseManager;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var UserTypeHelper
	 */
	private $userTypeHelper;

	/**
	 * @var array
	 */
	private $users;

	/**
	 * @var array
	 */
	private $guestUsers;

	public function testPanelPriority(): void {
		self::assertEquals(17, $this->panel->getPriority());
	}

	public function testGetSectionID(): void {
		self::assertEquals('general', $this->panel->getSectionID());
	}

	public function testGetPanel(): void {
		self::assertEquals('', $this->panel->getPanel());
	}

	public function testGetDefaultUserCount(): void {
		$class = new \ReflectionClass($this->panel);
		$getUserCount = $class->getMethod('getUserCount');
		$getUserCount->setAccessible(true);

		$users = $getUserCount->invokeArgs($this->panel, []);
		$guest = $users["guest"];
		$normal = $users["normal"];
		self::assertEquals(0, $guest);
		self::assertEquals(0, $normal);
	}

	public function testGetXUserCount(): void {
		$class = new \ReflectionClass($this->panel);
		$getUserCount = $class->getMethod('getUserCount');
		$getUserCount->setAccessible(true);

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
				]
			]
		);

		$users = $getUserCount->invokeArgs($this->panel, []);
		$guest = $users["guest"];
		$normal = $users["normal"];
		self::assertEquals(2, $guest);
		self::assertEquals(1, $normal);
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
					return;
				} elseif ($arg1 == 'core' && $arg2 == 'getLicenses') {
					return [];
				} elseif ($arg1 == 'core' && $arg2 == 'getUserAllowance') {
					return 0; // TODO: make configurable
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

		$this->defineUsers([]);

		$this->panel = new AdminPanel($this->licenseManager, $this->userManager, $this->userTypeHelper);
	}
}
