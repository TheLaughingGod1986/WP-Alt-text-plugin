<?php
declare(strict_types=1);

use BeepBeep\AltText\Core\Event_Bus;
use BeepBeep\AltText\Services\Authentication_Service;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['bbai_test_options'] = [];
		$GLOBALS['bbai_test_transients'] = [];
	}

	public function test_register_returns_free_plan_exists_for_already_linked_free_account(): void {
		$api_client = new BbAI_API_Client_V2();
		$api_client->token = 'existing-site-token';
		$api_client->usage = [
			'plan' => 'free',
			'used' => 1,
			'limit' => 50,
			'remaining' => 49,
		];

		$service = new Authentication_Service($api_client, new Event_Bus());
		$result = $service->register('owner@example.com', 'strong-password');

		self::assertFalse($result['success']);
		self::assertSame('free_plan_exists', $result['code']);
	}

	public function test_login_emits_user_logged_in_event_and_returns_user(): void {
		$api_client = new BbAI_API_Client_V2();
		$api_client->loginResult = [
			'user' => [
				'email' => 'editor@example.com',
			],
		];

		$event_bus = new Event_Bus();
		$received = null;
		$event_bus->on(
			'user_logged_in',
			static function ($payload) use (&$received): void {
				$received = $payload;
			}
		);

		$service = new Authentication_Service($api_client, $event_bus);
		$result = $service->login('editor@example.com', 'secret');

		self::assertTrue($result['success']);
		self::assertSame('editor@example.com', $result['user']['email']);
		self::assertIsArray($received);
		self::assertSame('editor@example.com', $received['user']['email']);
	}
}
