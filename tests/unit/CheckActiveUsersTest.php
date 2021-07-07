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

	/**
	 * @dataProvider providesUserAllowance
	 * @param array $users
	 * @param int $userAllowance
	 * @param int $expectedUsersBefore
	 * @param int $expectedGuests
	 * @param int $expectedNotifications
	 */
	public function testCommand(array $users, int $userAllowance, int $expectedUsersBefore, int $expectedGuests, int $expectedNotifications): void {
		$this->defineUsers($users);
		$this->userAllowance = $userAllowance;

		self::assertEquals($expectedUsersBefore, $this->getActiveUsers());
		self::assertEquals($expectedGuests, $this->getActiveGuests());

		$this->notificationManager->expects($this->exactly($expectedNotifications))->method("notify");
		$this->mailer->expects($this->exactly($expectedNotifications))->method("send");

		$usersBefore = $this->getActiveUsers();

		$this->commandTester->execute([]);

		self::assertLessThanOrEqual($this->userAllowance, $this->getActiveUsers());
		if ($usersBefore >= $this->userAllowance) {
			self::assertEquals($this->userAllowance, $this->getActiveUsers());
		}
		self::assertEquals($expectedGuests, $this->getActiveGuests());
	}

	public function providesUserAllowance(): array {
		return [
			'exceeded user allowance' => [
				[
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
				],
				5,
				6,
				8,
				1,
			],
			'within user allowance' => [
				[
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
				],
				5,
				4,
				8,
				0,
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
