<?php

namespace OCA\QNAP;

use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/**
	 * @var IFactory
	 */
	private $l10NFactory;

	public function __construct(IFactory $l10NFactory) {
		$this->l10NFactory = $l10NFactory;
	}

	public function prepare(INotification $notification, $languageCode) {
		if ($notification->getApp() !== 'qnap') {
			throw new \InvalidArgumentException();
		}

		if ($notification->getSubject() !== 'qnap-license-exceeded') {
			return $notification;
		}
		$l = $this->l10NFactory->get('qnap', $languageCode);

		$message = (string)$l->t('unfortunately, you exceeded the maximum number of users within your current license.');
		$message .= PHP_EOL;
		$message .= (string)$l->t('To upgrade the number of users please visit: https://software.qnap.com/owncloud.html');

		$notification->setParsedSubject($l->t('Action Required: Your ownCloud User Licenses are exceeded'));
		$notification->setParsedMessage($message);

		return $notification;
	}
}
