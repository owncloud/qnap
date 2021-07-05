<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\AdminPanel;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class AdminPanelTest extends TestCase {
	use UserTrait;

	/**
	 * @var AdminPanel
	 */
	private $panel;

	public function testPanelPriority(): void {
		self::assertEquals(17, $this->panel->getPriority());
	}

	public function testGetSectionID(): void {
		self::assertEquals('general', $this->panel->getSectionID());
	}

	public function testGetDefaultUserCount(): void {
		$class = new \ReflectionClass($this->panel);
		$method = $class->getMethod('getUserCount');
		$method->setAccessible(true);
		$res = $method->invokeArgs($this->panel, []);

		self::assertEquals(0, $res);
	}

	public function testGetFiveUserCount(): void {
		$class = new \ReflectionClass($this->panel);
		$getUserCount = $class->getMethod('getUserCount');
		$getUserCount->setAccessible(true);
		$getGuestUserCount = $class->getMethod('getGuestUserCount');
		$getGuestUserCount->setAccessible(true);

		$this->createUser('user1');
		$this->createUser('user2');
		$this->createUser('user3');
		$this->createUser('user4');
		$this->createUser('user5');

		// TODO: create guest users too

		self::assertEquals(5, $getUserCount->invokeArgs($this->panel, []));
		self::assertEquals(0, $getGuestUserCount->invokeArgs($this->panel, []));
	}

	protected function setUp(): void {
		parent::setUp();
		$this->panel = new AdminPanel();
	}
}
