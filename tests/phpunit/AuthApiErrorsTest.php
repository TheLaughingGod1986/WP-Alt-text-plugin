<?php
/**
 * Regression coverage for auth endpoint error mapping using mocked HTTP responses.
 */
declare(strict_types=1);

use BeepBeepAI\AltTextGenerator\API_Client_V2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WP_Error {
	public function __construct(private $code, private $message = '', private $data = null) {}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error($value) {
	return $value instanceof WP_Error;
}

function __($text, $domain = '') {
	return $text;
}

function absint($value) {
	return abs((int) $value);
}

function sanitize_email($value) {
	return $value;
}

function sanitize_text_field($value) {
	return $value;
}

function sanitize_key($value) {
	return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value));
}

function trailingslashit($value) {
	return rtrim($value, '/') . '/';
}

function wp_parse_url($url, $component = -1) {
	return parse_url($url, $component);
}

function wp_json_encode($value) {
	return json_encode($value);
}

function get_bloginfo($key) {
	return '6.8';
}

function wp_remote_request($url, $args) {
	return $GLOBALS['bbai_test_http_response'];
}

function wp_remote_retrieve_response_code($response) {
	return $response['status'];
}

function wp_remote_retrieve_body($response) {
	return json_encode($response['body']);
}

function esc_url_raw($value) {
	return $value;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';

final class AuthApiErrorsTest extends TestCase {
	private API_Client_V2 $api;
	private array $saved_options;

	protected function setUp(): void {
		$this->saved_options = $GLOBALS['bbai_test_options'];
		$GLOBALS['bbai_test_options']['beepbeepai_site_id'] = 'test_site_install_id_0123456789ab';
		$GLOBALS['bbai_test_options']['beepbeepai_site_fingerprint'] = 'test-fingerprint';
		$reflection = new ReflectionClass(API_Client_V2::class);
		$this->api = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty('api_url')->setValue($this->api, 'https://example.test/api');
	}

	protected function tearDown(): void {
		$GLOBALS['bbai_test_options'] = $this->saved_options;
		unset($GLOBALS['bbai_test_http_response']);
	}

	public function test_unknown_email_and_wrong_password_have_the_same_message(): void {
		$GLOBALS['bbai_test_http_response'] = ['status' => 401, 'body' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'Invalid credentials']];
		foreach (['unknown@example.test', 'known@example.test'] as $email) {
			$error = $this->api->login($email, 'wrong-password');
			$this->assertSame('invalid_credentials', $error->get_error_code());
			$this->assertSame("That email and password don't match an account. New here?", $error->get_error_message());
			$this->assertSame('INVALID_CREDENTIALS', $error->get_error_data()['backend_code']);
		}
	}

	public static function auth_errors(): array {
		return [
			['login', 401, 'NO_PASSWORD', 'no_password'],
			['login', 403, 'ACCOUNT_INACTIVE', 'account_inactive'],
			['login', 409, 'SITE_HAS_LICENSE', 'site_has_license'],
			['login', 403, 'INVITE_REQUIRED', 'invite_required'],
			['register', 409, 'EMAIL_EXISTS', 'user_exists'],
			['register', 403, 'WEAK_PASSWORD', 'registration_failed'],
			['register', 409, 'SITE_HAS_LICENSE', 'site_has_license'],
			['register', 403, 'INVITE_REQUIRED', 'invite_required'],
		];
	}

	#[DataProvider('auth_errors')]
	public function test_auth_errors_keep_their_mapping_and_raw_code(string $method, int $status, string $raw_code, string $mapped_code): void {
		$GLOBALS['bbai_test_http_response'] = ['status' => $status, 'body' => ['code' => $raw_code]];
		$error = $this->api->$method('known@example.test', 'password');
		$this->assertSame($mapped_code, $error->get_error_code());
		$this->assertSame($raw_code, $error->get_error_data()['backend_code']);
		$this->assertSame($status, $error->get_error_data()['status_code']);
		$this->assertNotEmpty($error->get_error_message());
	}

	public function test_register_maps_http_200_backend_failures(): void {
		foreach (['USER_EXISTS' => 'user_exists', 'INVALID_REQUEST' => 'registration_failed'] as $raw_code => $mapped_code) {
			$GLOBALS['bbai_test_http_response'] = ['status' => 200, 'body' => ['success' => false, 'code' => $raw_code]];
			$error = $this->api->register('known@example.test', 'password');
			$this->assertInstanceOf(WP_Error::class, $error);
			$this->assertSame($mapped_code, $error->get_error_code());
			$this->assertSame($raw_code, $error->get_error_data()['backend_code']);
			$this->assertSame(200, $error->get_error_data()['status_code']);
			if ($raw_code === 'USER_EXISTS') {
				$this->assertSame('An account with that email already exists. Log in instead.', $error->get_error_message());
			}
		}
	}

	public function test_non_auth_endpoints_still_require_authentication(): void {
		$GLOBALS['bbai_test_http_response'] = ['status' => 401, 'body' => ['code' => 'INVALID_CREDENTIALS']];
		$method = new ReflectionMethod(API_Client_V2::class, 'make_request');
		$error = $method->invoke($this->api, '/usage');
		$this->assertSame('auth_required', $error->get_error_code());
	}
}
