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
	private $guestUsers = [];

	/** @var array */
	private $adminUsers = [];

	/** @var array */
	private $notifications = [];

	/** @var array */
	private $mails = [];

	public function testExecute(): void {
		$input = $this->createMock(InputInterface::class);
		$output = $this->createMock(OutputInterface::class);

		$cmd = new \ReflectionClass(CheckActiveUsers::class);
		$exec = $cmd->getMethod('execute');
		$exec->setAccessible(true);

		$exec->invokeArgs($this->check, [$input, $output]);

		// one user more active then allowed -> deactivation notifications have been created
		self::assertEquals(1, \count($this->notifications));
		self::assertEquals(1, \count($this->mails));

		// reset notifications
		// $this->notifications = [];
		// $this->mails = [];

		// $exec->invokeArgs($this->check, [$input, $output]);

		// excess user has been deactivated before -> no deactivation notifications will be created
		// self::assertEquals(0, count($this->mails));
		// self::assertEquals(0, count($this->notifications));
	}

	private function defineUsers($users): void {
		foreach ($users as $id=>$user) {
			$u = $this->createMock(IUser::class);
			$u->method("setEnabled")->will(
				$this->returnCallback(function ($enabled) use ($id) {
					$this->users[$id]->method('isEnabled')->willReturn($enabled);
				})
			);
			$u->method('getUID')->willReturn($id);
			$enabled=$user["enabled"];
			$u->method('isEnabled')->willReturn($enabled);
			$this->users[$id] = $u;

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
					return 5;
				} else {
					throw new Exception('Not implemented');
				}
			})
		);

		$this->defineUsers(
			[
				0 => [
					'enabled' => true,
					'admin' => true,
					'guest' => false,
				],
				1 => [
					'enabled' => true,
					'admin' => false,
					'guest' => true,
				],
				2 => [
					'enabled' => true,
					'admin' => false,
					'guest' => true,
				],
				3 => [
					'enabled' => false,
					'admin' => false,
					'guest' => false,
				],
				4 => [
					'enabled' => false,
					'admin' => false,
					'guest' => true,
				],
				5 => [
					'enabled' => true,
					'admin' => false,
					'guest' => false,
				],
				6 => [
					'enabled' => true,
					'admin' => false,
					'guest' => false,
				],
				8 => [
					'enabled' => true,
					'admin' => false,
					'guest' => false,
				],
				9 => [
					'enabled' => true,
					'admin' => false,
					'guest' => false,
				],
				10 => [
					'enabled' => true,
					'admin' => false,
					'guest' => false,
				],
			]
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
