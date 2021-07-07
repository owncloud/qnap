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
	private $users = [];

	/** @var array */
	private $guestUsers = [];

	/** @var array */
	private $licenses = [];

	/** @var int */
	private $userAllowance = 10;

	public function testPanelPriority(): void {
		self::assertEquals(17, $this->panel->getPriority());
	}

	public function testGetSectionID(): void {
		self::assertEquals('general', $this->panel->getSectionID());
	}

	public function testGetPanel(): void {
		$this->licenses = [
			[
				'license_id' => 'license1',
				'status' => 'valid',
				'license_info' => [
					'valid_from' => \date_create()->setTimestamp(1616059540),
					'valid_until' => \date_create()->setTimestamp(1616059640),
					'attributes' => [
						'owncloud_account' => 5
					]
				]
			],
			[
				'license_id' => 'license2',
				'status' => 'valid',
				'license_info' => [
					'valid_from' => \date_create()->setTimestamp(1616059559),
					'valid_until' => \date_create()->setTimestamp(1616059659),
					'attributes' => [
						'owncloud_account' => 5
					]
				]
			],
		];

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

		$this->userAllowance = 10;

		$page = $this->panel->getPanel()->fetchPage();
		self::assertStringContainsString("Active users: 1", $page);
		self::assertStringContainsString("Licensed users: 10", $page);
		self::assertStringContainsString("Active guest users: 2", $page);
		self::assertStringContainsString("Licensed guest users: unlimited", $page);

		self::assertStringContainsString("<td>license1</td>", $page);
		self::assertStringContainsString("<td>Thursday, 18-Mar-2021 09:25:40 UTC</td>", $page);
		self::assertStringContainsString("<td>Thursday, 18-Mar-2021 09:27:20 UTC</td>", $page);
		self::assertStringContainsString("<td>5</td>", $page);
		self::assertStringContainsString("<td>valid</td>", $page);

		self::assertStringContainsString("<td>license2</td>", $page);
		self::assertStringContainsString("<td>Thursday, 18-Mar-2021 09:25:59 UTC</td>", $page);
		self::assertStringContainsString("<td>Thursday, 18-Mar-2021 09:27:39 UTC</td>", $page);
		self::assertStringContainsString("<td>5</td>", $page);
		self::assertStringContainsString("<td>valid</td>", $page);
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
					return $this->licenses;
				} elseif ($arg1 == 'core' && $arg2 == 'getUserAllowance') {
					return $this->userAllowance;
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
				foreach ($this->users as $user) {
					$func($user);
				}
			})
		);
		$this->panel = new AdminPanel($this->licenseManager, $this->userManager, $this->userTypeHelper);
	}
}
