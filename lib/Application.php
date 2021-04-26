<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\QNAP;

use OCP\AppFramework\App;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('qnap', $urlParams);
	}

	/**
	 * Registers the notifier
	 */
	public function registerNotifier() {
		$manager = $this->getContainer()->getServer()->getNotificationManager();
		$manager->registerNotifier(function () {
			return $this->getContainer()->query(Notifier::class);
		}, function () {
			$l = \OC::$server->getL10N('qnap');
			return [
				'id' => 'qnap',
				'name' => $l->t('QNAP'),
			];
		});
	}
}
