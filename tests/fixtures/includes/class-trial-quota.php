<?php
declare(strict_types=1);

namespace BeepBeepAI\AltTextGenerator;

/**
 * Test fixture for trial quota checks used by service unit tests.
 */
class Trial_Quota {
	public static bool $is_trial_user = false;
	public static bool $is_exhausted = false;
	public static int $limit = 10;
	public static int $remaining = 10;

	public static function is_trial_user(): bool {
		return self::$is_trial_user;
	}

	public static function is_exhausted(): bool {
		return self::$is_exhausted;
	}

	/**
	 * @return array{limit:int,remaining:int}
	 */
	public static function get_status(): array {
		return array(
			'limit' => self::$limit,
			'remaining' => self::$remaining,
		);
	}
}
