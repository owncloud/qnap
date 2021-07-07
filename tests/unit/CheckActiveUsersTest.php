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

	/** @var array */
	private $enabledUsers = [];

	/** @var array */
	private $guestUsers = [];

	/** @var array */
	private $adminUsers = [];

	/** @var int */
	private $userAllowance = 5;

	/** @var array */
	const ENABLED_ADMIN_USER = ['enabled' => true, 'admin' => true, 'guest' => false];
	const ENABLED_GUEST_USER = ['enabled' => true, 'admin' => false, 'guest' => true];
	const DISABLED_GUEST_USER = ['enabled' => false, 'admin' => false, 'guest' => true];
	const ENABLED_NORMAL_USER = ['enabled' => true, 'admin' => false, 'guest' => false];
	const DISABLED_NORMAL_USER = ['enabled' => false, 'admin' => false, 'guest' => false];

	public function testExecuteExceededUserAllowance(): void {
		$this->defineUsers([
			0 => self::ENABLED_ADMIN_USER,
			1 => self::ENABLED_GUEST_USER,
			2 => self::DISABLED_GUEST_USER,
			3 => self::DISABLED_NORMAL_USER,
			4 => self::ENABLED_GUEST_USER,
			5 => self::ENABLED_GUEST_USER,
			6 => self::ENABLED_NORMAL_USER,
			8 => self::ENABLED_NORMAL_USER,
			9 => self::ENABLED_NORMAL_USER,
			10 => self::ENABLED_NORMAL_USER,
			11 => self::ENABLED_NORMAL_USER,
			12 => self::ENABLED_GUEST_USER,
			13 => self::ENABLED_GUEST_USER,
			14 => self::ENABLED_GUEST_USER,
			15 => self::ENABLED_GUEST_USER,
			16 => self::ENABLED_GUEST_USER,
		]);

		$this->userAllowance = 5;

		self::assertEquals(6, $this->getActiveUsers());
		self::assertEquals(8, $this->getActiveGuests());

		// deactivation notifications will be created
		// -> 1 admin user, so only 1 notification and 1 mail
		$this->notificationManager->expects($this->once())->method("notify");
		$this->mailer->expects($this->once())->method("send");

		$this->commandTester->execute([]);

		// more users active then allowed
		// -> excess users were deactivated
		self::assertEquals($this->userAllowance, $this->getActiveUsers());
		// -> guests haven't been touched
		self::assertEquals(8, $this->getActiveGuests());

	}

	public function testExecuteWithinUserAllowance(): void {
		$this->defineUsers([
			0 => self::ENABLED_ADMIN_USER,
			1 => self::ENABLED_GUEST_USER,
			2 => self::DISABLED_GUEST_USER,
			3 => self::DISABLED_NORMAL_USER,
			4 => self::ENABLED_GUEST_USER,
			5 => self::ENABLED_GUEST_USER,
			6 => self::ENABLED_NORMAL_USER,
			8 => self::ENABLED_NORMAL_USER,
			9 => self::ENABLED_NORMAL_USER,
			10 => self::ENABLED_GUEST_USER,
			11 => self::ENABLED_GUEST_USER,
			12 => self::ENABLED_GUEST_USER,
			13 => self::ENABLED_GUEST_USER,
			14 => self::ENABLED_GUEST_USER,
		]);

		$this->userAllowance = 5;

		self::assertEquals(4, $this->getActiveUsers());
		self::assertEquals(8, $this->getActiveGuests());

		// deactivation notifications will not be created
		$this->notificationManager->expects($this->never())->method("notify");
		$this->mailer->expects($this->never())->method("send");

		$this->commandTester->execute([]);

		// less users active then allowed
		// -> no user has been touched
		self::assertEquals(4, $this->getActiveUsers());
		// -> guests haven't been touched
		self::assertEquals(8, $this->getActiveGuests());
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
			$this->userManager, $this->mailer, $this->l10n,
			$this->groupManager, $this->notificationManager,
			$this->urlGenerator, $this->licenseManager,
			$this->userTypeHelper
		);
		$this->commandTester = new CommandTester($command);
	}
}
