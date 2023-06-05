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

		$notification->setParsedSubject($l->t('User limit exceeded. To add more users click here.'));

		return $notification;
	}
}
