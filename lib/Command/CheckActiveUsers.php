<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
 * @license GPL-2.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\QNAP\Command;

use OC\Helper\UserTypeHelper;
use OCA\QNAP\QnapLicense;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\License\Exceptions\LicenseManagerException;
use OCP\License\ILicenseManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use OCP\Template;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckActiveUsers extends Command {
	/** @var ILicenseManager */
	private $licenseManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IMailer */
	private $mailer;

	/** @var IL10N */
	private $l10n;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IUser[] */
	private $adminUsers = [];
	private $normalUsers = [];

	/** @var int */
	private $numberOfActiveUsers = 0;

	/** @var IManager */
	private $notificationManager;

	/** @var UserTypeHelper */
	private $userTypeHelper;

	public function __construct(IUserManager $userManager, IMailer $mailer, IL10N $l10n, IGroupManager $groupManager, IManager $notificationManager, IURLGenerator $urlGenerator, ILicenseManager $licenseManager, UserTypeHelper $userTypeHelper) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->mailer = $mailer;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->urlGenerator = $urlGenerator;
		$this->licenseManager = $licenseManager;
		$this->userTypeHelper = $userTypeHelper;
	}

	protected function configure() {
		$this->setName('qnap:check-active-users')
			->setDescription('Check if the number of allowed users is exceeded and disables those who are not allowed to be used');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->prepare();

		$licensedUsers = $this->getLicensedUsers();
		if ($output->isVerbose()) {
			$output->writeln("After preparation: {$this->numberOfActiveUsers} active users, $licensedUsers licensed users");
		}

		if ($this->numberOfActiveUsers <= $licensedUsers) {
			if ($output->isVerbose()) {
				$output->writeln('Enough licenses available.');
			}
			return 0;
		}
		$this->sendNotification($output);
		$this->sendEMailToAdmin($output);
		$this->disableExceededUsers($output, $licensedUsers);

		return 0;
	}

	private function getLicensedUsers() : int {
		try {
			$classname = $this->licenseManager->askLicenseFor('core', 'getLicenseClass');
			$isQNAP = $classname === QnapLicense::class;
		} catch (LicenseManagerException $ex) {
			$isQNAP = false;
		}

		if ($isQNAP) {
			return $this->licenseManager->askLicenseFor('core', 'getUserAllowance');
		}
		return QnapLicense::MIN_USER_ALLOWANCE;
	}

	private function sendEMailToAdmin(OutputInterface $output): void {
		// prepare list of all admins with email
		$recipients = $this->buildRecipients();

		//  build body
		$template = new Template('qnap', 'mail/exceeded/plain', '', false);
		$plainBody = $template->fetchPage();

		// send it out now
		$message = $this->mailer->createMessage();
		$message->setTo($recipients);
		$message->setSubject((string) $this->l10n->t('Action required: Your ownCloud user limit is exceeded'));
		$message->setPlainBody($plainBody);
		try {
			$this->mailer->send($message);
			if ($output->isVerbose()) {
				$output->writeln('License exceed notification has been sent to admins.');
			}
		} catch (\Exception $e) {
			$output->writeln('Error sending email: ' . $e->getMessage());
		}
	}

	private function disableExceededUsers(OutputInterface $output, int $licensedUsers): int {
		if ($output->isVerbose()) {
			$output->writeln('Disabling user without license:');
		}
		$activeUsers = 0;
		$usergroups = [\array_reverse($this->adminUsers), $this->normalUsers];

		foreach ($usergroups as $users) {
			foreach ($users as $user) {
				if ($user->isEnabled()) {
					$activeUsers++;
					if ($activeUsers > $licensedUsers) {
						$user->setEnabled(false);
						if ($output->isVerbose()) {
							$output->writeln($user->getUID());
						}
					}
				}
			}
		}

		return $activeUsers;
	}

	private function prepare() :void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			if ($user->isEnabled() && $this->userTypeHelper->isGuestUser($user->getUID()) === false) {
				$this->numberOfActiveUsers++;
				if ($this->groupManager->isAdmin($user->getUID())) {
					$this->adminUsers[]= $user;
				} else {
					$this->normalUsers[]= $user;
				}
			}
		});
	}

	private function buildRecipients(): array {
		/** @var IUser[] $adminsWithEMail */
		$adminsWithEMail = \array_filter($this->adminUsers, static function (IUser $user) {
			return $user->getEMailAddress() !== null && $user->getEMailAddress() !== '';
		});

		$recipients = [];
		foreach ($adminsWithEMail as $a) {
			$recipients[$a->getEMailAddress()] = $a->getDisplayName();
		}

		return $recipients;
	}

	private function sendNotification(OutputInterface $output): void {
		$link = $this->urlGenerator->linkTo('', 'index.php/settings/admin?sectionid=general');

		$time = \time();
		foreach ($this->adminUsers as $a) {
			$rnd = \random_int(PHP_INT_MIN, PHP_INT_MAX);
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('qnap');
			$notification->setObject('qnap', "$time-$rnd");
			$notification->setUser($a->getUserName());
			$notification->setLink($link);
			$notification->setSubject('qnap-license-exceeded');
			$notification->setDateTime(new \DateTime());

			$this->notificationManager->notify($notification);
		}
	}
}
