<?php

namespace OCA\QNAP\Tests\Unit;

use OCA\QNAP\LicenseParser;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class LicenseParserTest extends TestCase {
	/**
	 * @var LicenseParser
	 */
	private $parser;

	/**
	 * @dataProvider providesLicenses
	 * @param bool $expectedValid
	 * @param int $expectedUsers
	 * @param int $expectedExpiration
	 * @param string $licenseStr
	 */
	public function test(bool $expectedValid, int $expectedUsers, int $expectedExpiration, string $licenseStr): void {
		$this->parser->loadLicensesText($licenseStr);

		self::assertEquals($expectedValid, $this->parser->isValid());
		self::assertEquals($expectedUsers, $this->parser->getUserAllowance());
		self::assertEquals($expectedExpiration, $this->parser->getExpirationTime());
	}

	public function providesLicenses(): array {
		return [
			'no license' => [
				false, 0, 0, ''
			],
			'expired license' => [
				false, 0, 1615812628, self::buildLicenseString([
					[
						'valid_from' => '2020-03-15 12:50:28.845000',
						'valid_until' => '2021-03-15 12:50:28.845000',
						'users' => '10']
				])
			],
			'one valid license' => [
				true, 10, 1647348628, self::buildLicenseString([
					[
						'valid_from' => '2021-03-15 12:50:28.845000',
						'valid_until' => '2022-03-15 12:50:28.845000',
						'users' => '10']
				])
			],
			'two valid licenses' => [
				true, 25, 1647348628, self::buildLicenseString([
					[
						'valid_from' => '2021-03-15 12:50:28.845000',
						'valid_until' => '2022-03-15 12:50:28.845000',
						'users' => '10'
					], [
						'valid_from' => '2021-03-16 12:50:28.845000',
						'valid_until' => '2022-03-16 12:50:28.845000',
						'users' => '15'
					]
				])
			],
			'one expired and one valid' => [
			true, 15, 1647435028, self::buildLicenseString([
				[
					'valid_from' => '2020-03-15 12:50:28.845000',
					'valid_until' => '2021-03-15 12:50:28.845000',
					'users' => '10'
				], [
					'valid_from' => '2021-03-16 12:50:28.845000',
					'valid_until' => '2022-03-16 12:50:28.845000',
					'users' => '15'
				]
			])
		]
		];
	}

	private static function buildLicenseString(array $array): string {
		$licenses = [];

		foreach ($array as $lic) {
			$valid_from = $lic['valid_from'];
			$valid_until = $lic['valid_until'];
			$users = $lic['users'] ?? 5;
			$productType = $lic['product_type'] ?? 'ownCloud';

			$licStr = \json_encode([
				'sku' => 'LS-OWNCLOUD-5U-1Y-EI',
				'valid_until' => $valid_until,
				'app_internal_name' => 'ownCloud',
				'valid_from' => $valid_from,
				'product_type' => $productType,
				'name' => 'ownCloud Enterprise - 5 users - 1 year subscription',
				'license_name' => 'ownCloud Enterprise - 5 users - 1 year subscription (3)',
				'feature' => null,
				'applied_at' => '2021-03-15 12:51:32.564648',
				'categories' => ['ownCloud'],
				'owner' => ['602112fc48cfd0026d183c0a'],
				'attributes' => [
					'is_floating' => false,
					'app_display_name' => 'ownCloud',
					'product_class' => 'ownCloud',
					'upgradeable' => true,
					'immediate_activate' => true,
					'owncloud_account' => $users,
					'is_subscription' => true,
					'device_type' => 'NAS',
					'external_service' => false,
					'app_min_version' => '10.4.1',
					'online_activate_only' => true,
					'is_self_check' => false,
					'app_internal_name' => 'ownCloud',
					'is_perpetual' => 'false',
					'duration_year' => '1',
					'extendable' => true,
					'type' => 'device',
					'msrp_display_actual_price_type' => 'Use config',
					'grace_period_days' => '30',
					'quota_user' => '5',
					'is_bundle' => false,
					'is_grace' => true,
					'transferable' => true,
					'support_org' => false,
					'plan_title' => '1 Year',
					'quota_device' => '1'
				],
				'app_display_name' => 'ownCloud', 'channel' => null, 'product_id' => '2430'
			]);

			$licenses[] = [
				"id" => "95297e46-3da6-40e3-8013-f19ce7df8e12",
				"dif_id" => "5cdf22a7-8c33-4891-aa3a-ab75ede5bd18",
				"dif_signature" => "MGQCMFee3R1b6ZsyZo33WUDH0xeh1gToePU5ItUuRdBtTT2HBpoEZlG7oeA3u+ay8lsK6AIwcgyyWgq/rbrnQIRyBmBse/nN5cgmEk4z5M1Q2vqV2RwoMJcXfzMIvAJ3o4YBueNS",
				"license_id" => "604f5814055a537af9f45670",
				"mac" => "24:5e:be:37:91:3a",
				"hwsn" => "Q18CI07477",
				"suid" => "19f412604eb024ff9438ab57e31307cc",
				"model" => "TVS-472XT",
				"fw_build_version" => "4.5.2.1594 (20210302)",
				"license_info_json_str" => $licStr,
				"status" => "valid",
				"subscription_status" => "valid",
				"legacy" => 0,
				"apply_date" => "2021/03/15",
				"created_at" => "2021-03-15T13:51:31+00:00",
				"remaining_days" => 365,
				"api_check_date" => "2021-03-15T12:51:33Z",
				"floating_uuid" => "",
				"floating_token" => "",
				"license_check_period" => 43200,
				"is_partial_deactivated" => 0,
				"used_seats" => 1,
				"unsubscribed_used_seats" => 0,
				"unsubscribed_valid_until" => "",
				"api_check_status" => "success"
			];
		}

		return \base64_encode(\json_encode($licenses));
	}

	protected function setUp(): void {
		parent::setUp();
		$timeFactory = $this->createMock(ITimeFactory::class);
		# 2021-03-18T09:25:59+00:00
		$timeFactory->method('getTime')->willReturn(1616059559);
		$this->parser = new LicenseParser($timeFactory);
	}
}
