<?php

namespace OCA\QNAP\Tests\Unit;

use OC\Helper\UserTypeHelper;
use OC\Mail\Message;
use OCA\QNAP\Command\CheckActiveUsers;
use OCA\QNAP\QnapLicense;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\License\ILicenseManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class CheckActiveusersTest extends TestCase {
	/** @var CommandTester */
	private $commandTester;

	/** @var IUserManager */
	private $userManager;

	/** @var UserTypeHelper */
	private $userTypeHelper;

	/** @var IMailer */
	private $mailer;

	/** @var IL10N */
	private $l10n;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IManager */
	private $notificationManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ILicenseManager */
	private $licenseManager;

	/** @var array */
	private $users = [];
	private $enabledUsers = [];
	private $guestUsers = [];
	private $adminUsers = [];

	/** @var int */
	private $userAllowance = 0;

	/** @var array */
	public const ENABLED_ADMIN_USER = ['enabled' => true, 'admin' => true, 'guest' => false];
	public const ENABLED_GUEST_USER = ['enabled' => true, 'admin' => false, 'guest' => true];
	public const DISABLED_GUEST_USER = ['enabled' => false, 'admin' => false, 'guest' => true];
	public const ENABLED_NORMAL_USER = ['enabled' => true, 'admin' => false, 'guest' => false];
	public const DISABLED_NORMAL_USER = ['enabled' => false, 'admin' => false, 'guest' => false];

	/**
	 * @dataProvider providesUserAllowance
	 * @param array $users
	 * @param int $userAllowance
	 * @param int $expectedUsersBefore
	 * @param int $expectedGuests
	 * @param int $expectedNotifications
	 */
	public function testCommand(array $users, array $alwaysActiveUsers, array $disabledUsers, int $userAllowance, int $expectedUsersBefore, int $expectedGuests, int $expectedNotifications, int $expectedEmails): void {
		ksort($users);
		$this->defineUsers($users);
		$this->userAllowance = $userAllowance;

		self::assertEquals($expectedUsersBefore, $this->getActiveUsers());
		self::assertEquals($expectedGuests, $this->getActiveGuests());

		$this->notificationManager->expects($this->exactly($expectedNotifications))->method("notify"); // each admin user gets its own notification
		$this->mailer->expects($this->exactly($expectedEmails))->method("send"); // mail is send to all admins together -> only once

		$usersBefore = $this->getActiveUsers();

		$this->commandTester->execute([]);

		self::assertLessThanOrEqual($this->userAllowance, $this->getActiveUsers());
		if ($usersBefore >= $this->userAllowance) {
			self::assertEquals($this->userAllowance, $this->getActiveUsers());
		}
		self::assertEquals($expectedGuests, $this->getActiveGuests());

		foreach ($alwaysActiveUsers as $user) {
			self::assertTrue($this->enabledUsers[$user], $user);
		}

		foreach ($disabledUsers as $user) {
			self::assertFalse($this->enabledUsers[$user], $user);
		}
	}

	public function providesUserAllowance(): array {
		return [
			'exceeded user allowance' => [
				[
					'0000-guest' => self::ENABLED_GUEST_USER,
					'0001-guest' => self::ENABLED_GUEST_USER,
					'0002-guest' => self::ENABLED_GUEST_USER,
					'001-guest' => self::ENABLED_GUEST_USER,
					'001-user' => self::DISABLED_NORMAL_USER,
					'002-guest' => self::DISABLED_GUEST_USER,
					'002-user' => self::ENABLED_NORMAL_USER,
					'003-guest' => self::ENABLED_GUEST_USER,
					'003-user' => self::ENABLED_NORMAL_USER,
					'004-guest' => self::ENABLED_GUEST_USER,
					'004-user' => self::ENABLED_NORMAL_USER,
					'005-guest' => self::ENABLED_GUEST_USER,
					'005-user' => self::ENABLED_NORMAL_USER,
					'006-guest' => self::ENABLED_GUEST_USER,
					'006-user' => self::ENABLED_NORMAL_USER,
					'admin' => self::ENABLED_ADMIN_USER,
					'user-001' => self::ENABLED_NORMAL_USER,
					'user-002' => self::ENABLED_NORMAL_USER,
					'user-003' => self::ENABLED_NORMAL_USER,
					'user-004' => self::ENABLED_NORMAL_USER,
					'user-005' => self::ENABLED_NORMAL_USER,
					'zzz-admin' => self::ENABLED_ADMIN_USER,
				],
				[
					'0000-guest',
					'0001-guest',
					'0002-guest',
					'001-guest',
					'002-user',
					'003-guest',
					'003-user',
					'004-guest',
					'004-user',
					'005-guest',
					'006-guest',
					'admin',
					'zzz-admin',
				],
				[
					'001-user',
					'002-guest',
					'005-user',
					'006-user',
					'user-001',
					'user-002',
					'user-003',
					'user-004',
					'user-005',

				],
				5,
				12,
				8,
				2, // there are two admin users which receive notifications
				1,
			],
			'within user allowance' => [
				[
					'001-guest' => self::ENABLED_GUEST_USER,
					'001-user' => self::DISABLED_NORMAL_USER,
					'002-guest' => self::DISABLED_GUEST_USER,
					'002-user' => self::ENABLED_NORMAL_USER,
					'003-guest' => self::ENABLED_GUEST_USER,
					'003-user' => self::ENABLED_NORMAL_USER,
					'004-guest' => self::ENABLED_GUEST_USER,
					'004-user' => self::ENABLED_NORMAL_USER,
					'005-guest' => self::ENABLED_GUEST_USER,
					'006-guest' => self::ENABLED_GUEST_USER,
					'007-guest' => self::ENABLED_GUEST_USER,
					'008-guest' => self::ENABLED_GUEST_USER,
					'009-guest' => self::ENABLED_GUEST_USER,
					'admin' => self::ENABLED_ADMIN_USER,
				],
				[
					'001-guest',
					'002-user',
					'003-guest',
					'003-user',
					'004-guest',
					'004-user',
					'005-guest',
					'006-guest',
					'007-guest',
					'008-guest',
					'009-guest',
					'admin',
				],
				[
					'001-user',
					'002-guest',
				],
				5,
				4,
				8,
				0, // there is one admin user, but no user gets disabled
				0,
			],
			'only admins but exceeding user limit' => [
				[
					'001-admin' => self::ENABLED_ADMIN_USER,
					'002-admin' => self::ENABLED_ADMIN_USER,
					'003-admin' => self::ENABLED_ADMIN_USER,
					'004-admin' => self::ENABLED_ADMIN_USER,
					'005-admin' => self::ENABLED_ADMIN_USER,
					'006-admin' => self::ENABLED_ADMIN_USER,
					'007-admin' => self::ENABLED_ADMIN_USER,
				],
				[
					'003-admin',
					'004-admin',
					'005-admin',
					'006-admin',
					'007-admin',
				],
				[
					'001-admin',
					'002-admin',
				],
				5,
				7,
				0,
				7, // there are 7 admin users which receive notifications
				1,
			],

		];
	}

	private function getActiveUsers(): int {
		$res = [];
		foreach ($this->enabledUsers as $i=>$enabled) {
			$res[$i] = $enabled && !$this->guestUsers[$i];
		}
		return \count(\array_filter($res));
	}

	private function getActiveGuests(): int {
		$res = [];
		foreach ($this->enabledUsers as $i=>$enabled) {
			$res[$i] = $enabled && $this->guestUsers[$i];
		}
		return \count(\array_filter($res));
	}

	private function defineUsers($users): void {
		foreach ($users as $id=>$user) {
			$u = $this->createMock(IUser::class);
			$u->method("setEnabled")->will(
				$this->returnCallback(function ($enabled) use ($id) {
					$this->enabledUsers[$id] = $enabled;
				})
			);
			$u->method("isEnabled")->will(
				$this->returnCallback(function () use ($id) {
					return $this->enabledUsers[$id];
				})
			);
			$u->method('getUID')->willReturn($id);

			$this->users[$id] = $u;

			$enabled = $user['enabled'];
			$this->enabledUsers[$id] = $enabled;

			$isGuest = $user['guest'];
			$this->guestUsers[$id] = $isGuest;

			$isAdmin = $user['admin'];
			$this->adminUsers[$id] = $isAdmin;
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method("callForUsers")->will(
			$this->returnCallback(function ($func) {
				foreach ($this->users as $user) {
					$func($user);
				}
			})
		);
		$this->userManager->method("callForAllUsers")->will(
			$this->returnCallback(function ($func) {
				foreach ($this->users as $user) {
					$func($user);
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

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method("isAdmin")->will(
			$this->returnCallback(function ($uid) {
				$isAdmin = $this->adminUsers[$uid];
				return $isAdmin;
			})
		);

		$this->licenseManager = $this->createMock(ILicenseManager::class);
		$this->licenseManager->method("askLicenseFor")->will(
			$this->returnCallback(function ($arg1, $arg2) {
				if ($arg1 == 'core' && $arg2 == 'getLicenseClass') {
					return QnapLicense::class;
				} elseif ($arg1 == 'core' && $arg2 == 'getUserAllowance') {
					return $this->userAllowance;
				} else {
					throw new Exception('Not implemented');
				}
			})
		);

		$this->notificationManager = $this->createMock(IManager::class);
		$this->notificationManager->method("createNotification")->will(
			$this->returnCallback(function () {
				return $this->createMock(INotification::class);
			})
		);

		$this->mailer = $this->createMock(IMailer::class);
		$this->mailer->method("createMessage")->will(
			$this->returnCallback(function () {
				return $this->createMock(Message::class);
			})
		);

		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$command = new CheckActiveUsers(
			$this->userManager,
			$this->mailer,
			$this->l10n,
			$this->groupManager,
			$this->notificationManager,
			$this->urlGenerator,
			$this->licenseManager,
			$this->userTypeHelper
		);
		$this->commandTester = new CommandTester($command);
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->userAllowance = 0;
		$this->users = [];
		$this->guestUsers = [];
		$this->adminUsers = [];
		$this->enabledUsers = [];
	}
}
