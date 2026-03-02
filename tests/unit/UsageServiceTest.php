<?php
declare(strict_types=1);

use BeepBeep\AltText\Services\Usage_Service;
use PHPUnit\Framework\TestCase;

final class UsageServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['bbai_test_options'] = [];
		$GLOBALS['bbai_test_transients'] = [];
	}

	public function test_record_usage_updates_local_usage_totals(): void {
		$api_client = new BbAI_API_Client_V2();
		$service = new Usage_Service($api_client);

		$service->record_usage(
			array(
				'prompt' => 4,
				'completion' => 6,
				'total' => 10,
			)
		);

		$options = get_option('beepbeepai_settings', []);
		self::assertIsArray($options);
		self::assertSame(10, $options['usage']['total']);
		self::assertSame(1, $options['usage']['requests']);
		self::assertSame('2026-01-01 00:00:00', $options['usage']['last_request']);
	}

	public function test_refresh_usage_returns_tracker_stats_on_success(): void {
		$api_client = new BbAI_API_Client_V2();
		$api_client->usage = [
			'used' => 5,
			'limit' => 50,
			'remaining' => 45,
		];
		BbAI_Usage_Tracker::$statsDisplay = [
			'used' => 5,
			'limit' => 50,
			'remaining' => 45,
		];

		$service = new Usage_Service($api_client);
		$result = $service->refresh_usage();

		self::assertTrue($result['success']);
		self::assertSame(5, $result['stats']['used']);
		self::assertSame(45, $result['stats']['remaining']);
	}
}
