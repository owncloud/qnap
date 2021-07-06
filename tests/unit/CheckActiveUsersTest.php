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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class CheckActiveusersTest extends TestCase {
	/** @var CheckActiveUsers */
	private $check;

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

	/** @var array */
	private $notifications = [];

	/** @var array */
	private $mails = [];

	/** @var int */
	const USER_ALLOWANCE = 5;

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

		$input = $this->createMock(InputInterface::class);
		$output = $this->createMock(OutputInterface::class);

		$cmd = new \ReflectionClass(CheckActiveUsers::class);
		$exec = $cmd->getMethod('execute');
		$exec->setAccessible(true);

		self::assertEquals(6, $this->getActiveUsers());
		self::assertEquals(8, $this->getActiveGuests());

		$exec->invokeArgs($this->check, [$input, $output]);

		// one user more active then allowed
		// -> excess users were deactivated
		self::assertEquals(self::USER_ALLOWANCE, $this->getActiveUsers());
		// -> guests haven't been touched
		self::assertEquals(8, $this->getActiveGuests());
		// -> deactivation notifications have been created
		self::assertEquals(1, \count($this->notifications));
		self::assertEquals(1, \count($this->mails));
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

		$input = $this->createMock(InputInterface::class);
		$output = $this->createMock(OutputInterface::class);

		$cmd = new \ReflectionClass(CheckActiveUsers::class);
		$exec = $cmd->getMethod('execute');
		$exec->setAccessible(true);

		self::assertEquals(4, $this->getActiveUsers());
		self::assertEquals(8, $this->getActiveGuests());

		$exec->invokeArgs($this->check, [$input, $output]);

		// one user less active then allowed
		// -> no user has been touched
		self::assertEquals(4, $this->getActiveUsers());
		// -> guests haven't been touched
		self::assertEquals(8, $this->getActiveGuests());
		// -> deactivation notifications have been created
		self::assertEquals(0, \count($this->notifications));
		self::assertEquals(0, \count($this->mails));
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
					return self::USER_ALLOWANCE;
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
		$this->notificationManager->method("notify")->will(
			$this->returnCallback(function ($notification) {
				\array_push($this->notifications, $notification);
			})
		);

		$this->mailer = $this->createMock(IMailer::class);
		$this->mailer->method("createMessage")->will(
			$this->returnCallback(function () {
				return $this->createMock(Message::class);
			})
		);
		$this->mailer->method("send")->will(
			$this->returnCallback(function ($mail) {
				\array_push($this->mails, $mail);
			})
		);

		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->check = new CheckActiveUsers(
			$this->userManager, $this->mailer, $this->l10n,
			$this->groupManager, $this->notificationManager,
			$this->urlGenerator, $this->licenseManager,
			$this->userTypeHelper
		);
	}
}
