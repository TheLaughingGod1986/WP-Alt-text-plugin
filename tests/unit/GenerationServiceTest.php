<?php
declare(strict_types=1);

use BeepBeep\AltText\Core\Event_Bus;
use BeepBeep\AltText\Services\Generation_Service;
use BeepBeep\AltText\Services\Usage_Service;
use BeepBeepAI\AltTextGenerator\Trial_Quota;
use PHPUnit\Framework\TestCase;

final class GenerationServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Trial_Quota::$is_trial_user = false;
		Trial_Quota::$is_exhausted = false;
		Trial_Quota::$limit = 10;
		Trial_Quota::$remaining = 10;
	}

	public function test_regenerate_single_success_emits_event_and_returns_alt_text(): void {
		$api_client = new BbAI_API_Client_V2();
		$usage_service = new Usage_Service($api_client);
		$event_bus = new Event_Bus();
		$core = new BbAI_Core();
		$core->nextGenerateResult = 'A scenic mountain lake at sunrise.';

		$emitted = null;
		$event_bus->on(
			'alt_text_generated',
			static function ($payload) use (&$emitted): void {
				$emitted = $payload;
			}
		);

		$service = new Generation_Service($api_client, $usage_service, $event_bus, $core);
		$result = $service->regenerate_single(42);

		self::assertTrue($result['success']);
		self::assertSame('A scenic mountain lake at sunrise.', $result['alt_text']);
		self::assertSame(42, $result['attachment_id']);
		self::assertIsArray($emitted);
		self::assertSame(42, $emitted['attachment_id']);
	}

	public function test_regenerate_single_maps_timeout_to_user_friendly_message(): void {
		$api_client = new BbAI_API_Client_V2();
		$usage_service = new Usage_Service($api_client);
		$event_bus = new Event_Bus();
		$core = new BbAI_Core();
		$core->nextGenerateResult = new WP_Error('api_timeout', 'Gateway timeout');

		$service = new Generation_Service($api_client, $usage_service, $event_bus, $core);
		$result = $service->regenerate_single(77);

		self::assertFalse($result['success']);
		self::assertSame('api_timeout', $result['code']);
		self::assertStringContainsString('timed out', strtolower($result['message']));
	}
}
