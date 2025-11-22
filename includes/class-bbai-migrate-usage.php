<?php
/**
 * Migration Utility for Credit Usage
 * Backfills existing usage data with user attribution where possible
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
	exit;
}

class Migrate_Usage {
	const MIGRATION_FLAG = 'beepbeepai_usage_migrated';

	/**
	 * Run migration to backfill credit usage data.
	 *
	 * @return array Migration results.
	 */
	public static function migrate() {
		// Check if already migrated
		if (get_option(self::MIGRATION_FLAG, false)) {
			return [
				'success' => true,
				'message' => __('Credit usage migration already completed.', 'beepbeep-ai-alt-text-generator'),
				'migrated' => 0,
			];
		}

		require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';

		// Ensure credit usage table exists
		if (!Credit_Usage_Logger::table_exists()) {
			Credit_Usage_Logger::create_table();
		}

		$migrated_count = 0;

		// Strategy 1: Check debug log entries for generation events
		$migrated_from_logs = self::migrate_from_debug_logs();
		$migrated_count += $migrated_from_logs;

		// Strategy 2: Check attachment meta for AI generation metadata
		$migrated_from_meta = self::migrate_from_attachment_meta();
		$migrated_count += $migrated_from_meta;

		// Mark as migrated
		if ($migrated_count > 0 || $migrated_from_logs >= 0) {
			update_option(self::MIGRATION_FLAG, true, false);
		}

		return [
			'success' => true,
			'message' => sprintf(
				__('Migration completed. Migrated %d usage records.', 'beepbeep-ai-alt-text-generator'),
				$migrated_count
			),
			'migrated' => $migrated_count,
		];
	}

	/**
	 * Migrate from debug log entries.
	 *
	 * @return int Number of records migrated.
	 */
	private static function migrate_from_debug_logs() {
		if (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
			return 0;
		}

		global $wpdb;
		$logs_table = Debug_Log::table();
		$logs_table_escaped = esc_sql($logs_table);
		$credit_table = Credit_Usage_Logger::table();
		$credit_table_escaped = esc_sql($credit_table);

		// Find generation log entries
		$query = "SELECT id, message, context, source, user_id, created_at 
			FROM `{$logs_table_escaped}` 
			WHERE (source = 'generation' OR message LIKE '%alt text%' OR message LIKE '%Alt text%')
			AND context LIKE '%attachment_id%'
			ORDER BY created_at ASC
			LIMIT 1000";

		$logs = $wpdb->get_results($query, ARRAY_A);

		if (empty($logs)) {
			return 0;
		}

		$migrated = 0;

		foreach ($logs as $log) {
			// Parse context for attachment_id
			$context = json_decode($log['context'], true);
			if (!is_array($context) || empty($context['attachment_id'])) {
				continue;
			}

			$attachment_id = absint($context['attachment_id']);
			if ($attachment_id <= 0) {
				continue;
			}

			// Check if already migrated
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM `{$credit_table_escaped}` WHERE attachment_id = %d AND generated_at = %s",
				$attachment_id,
				$log['created_at']
			));

			if ($exists > 0) {
				continue; // Already migrated
			}

			// Determine user ID from log
			$user_id = 0;
			if (!empty($log['user_id']) && $log['user_id'] > 0) {
				$user_id = absint($log['user_id']);
			} elseif (!empty($context['user_id'])) {
				$user_id = absint($context['user_id']);
			} else {
				// Try to get attachment author
				$attachment = get_post($attachment_id);
				if ($attachment && $attachment->post_author > 0) {
					$user_id = absint($attachment->post_author);
				}
			}

			// Determine source from context
			$source = 'manual';
			if (!empty($context['source'])) {
				$source = sanitize_key($context['source']);
			} elseif (!empty($log['source']) && $log['source'] !== 'generation') {
				$source = sanitize_key($log['source']);
			}

			// Estimate credits used (default to 1 if not specified)
			$credits_used = 1;
			if (!empty($context['usage']) && is_array($context['usage'])) {
				$usage = $context['usage'];
				if (isset($usage['total_tokens'])) {
					$credits_used = absint($usage['total_tokens']);
				}
			}

			// Get model from context or meta (check new key first, then migrate old key)
			$model = '';
			if (!empty($context['model'])) {
				$model = sanitize_text_field($context['model']);
			} else {
				$model_meta = get_post_meta($attachment_id, '_beepbeepai_model', true);
				if (!$model_meta) {
					// Try old key and migrate if found
					$model_meta = get_post_meta($attachment_id, '_ai_alt_model', true);
					if ($model_meta) {
						update_post_meta($attachment_id, '_beepbeepai_model', $model_meta);
						delete_post_meta($attachment_id, '_ai_alt_model');
					}
				}
				if ($model_meta) {
					$model = sanitize_text_field($model_meta);
				}
			}

			// Log the usage
			$logged = Credit_Usage_Logger::log_usage(
				$attachment_id,
				$user_id,
				$credits_used,
				null, // token_cost not available in old logs
				$model,
				$source
			);

			// Update generated_at to match log timestamp
			if ($logged && !empty($log['created_at'])) {
				$wpdb->update(
					$credit_table,
					['generated_at' => $log['created_at']],
					['id' => $logged],
					['%s'],
					['%d']
				);
			}

			if ($logged) {
				$migrated++;
			}
		}

		return $migrated;
	}

	/**
	 * Migrate from attachment meta data.
	 *
	 * @return int Number of records migrated.
	 */
	private static function migrate_from_attachment_meta() {
		global $wpdb;
		$credit_table = Credit_Usage_Logger::table();
		$credit_table_escaped = esc_sql($credit_table);

		// Find attachments with AI-generated alt text metadata (check both old and new keys)
		$query = $wpdb->prepare(
			"SELECT post_id, meta_value, post_author, post_date 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%%'
			AND (pm.meta_key = %s OR pm.meta_key = %s)
			AND pm.meta_value IS NOT NULL
			AND pm.meta_value != ''
			LIMIT 1000",
			'_beepbeepai_generated_at',
			'_ai_alt_generated_at'
		);

		$attachments = $wpdb->get_results($query, ARRAY_A);

		if (empty($attachments)) {
			return 0;
		}

		$migrated = 0;

		foreach ($attachments as $attachment) {
			$attachment_id = absint($attachment['post_id']);
			if ($attachment_id <= 0) {
				continue;
			}

			// Check if already migrated
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM `{$credit_table_escaped}` WHERE attachment_id = %d",
				$attachment_id
			));

			if ($exists > 0) {
				continue; // Already migrated
			}

			// Get user ID from attachment author
			$user_id = absint($attachment['post_author']);
			if ($user_id <= 0) {
				$user_id = 0; // System/unknown
			}

			// Get source from meta (check new key first, migrate old key if found)
			$source_meta = get_post_meta($attachment_id, '_beepbeepai_source', true);
			if (!$source_meta) {
				$source_meta = get_post_meta($attachment_id, '_ai_alt_source', true);
				if ($source_meta) {
					update_post_meta($attachment_id, '_beepbeepai_source', $source_meta);
					delete_post_meta($attachment_id, '_ai_alt_source');
				}
			}
			$source = !empty($source_meta) ? sanitize_key($source_meta) : 'auto';

			// Get model from meta (check new key first, migrate old key if found)
			$model = get_post_meta($attachment_id, '_beepbeepai_model', true);
			if (!$model) {
				$model = get_post_meta($attachment_id, '_ai_alt_model', true);
				if ($model) {
					update_post_meta($attachment_id, '_beepbeepai_model', $model);
					delete_post_meta($attachment_id, '_ai_alt_model');
				}
			}
			$model = !empty($model) ? sanitize_text_field($model) : '';

			// Estimate credits (default to 1)
			$credits_used = 1;
			$usage_meta = get_post_meta($attachment_id, '_beepbeepai_usage', true);
			if (!$usage_meta) {
				$usage_meta = get_post_meta($attachment_id, '_ai_alt_usage', true);
				if ($usage_meta) {
					update_post_meta($attachment_id, '_beepbeepai_usage', $usage_meta);
					delete_post_meta($attachment_id, '_ai_alt_usage');
				}
			}
			if (is_array($usage_meta) && isset($usage_meta['total_tokens'])) {
				$credits_used = absint($usage_meta['total_tokens']);
			}

			// Get generated timestamp
			$generated_at = !empty($attachment['meta_value']) ? $attachment['meta_value'] : $attachment['post_date'];

			// Log the usage
			$logged = Credit_Usage_Logger::log_usage(
				$attachment_id,
				$user_id,
				$credits_used,
				null, // token_cost not available
				$model,
				$source
			);

			// Update generated_at to match meta timestamp
			if ($logged && !empty($generated_at)) {
				$wpdb->update(
					$credit_table,
					['generated_at' => $generated_at],
					['id' => $logged],
					['%s'],
					['%d']
				);
			}

			if ($logged) {
				$migrated++;
			}
		}

		return $migrated;
	}

	/**
	 * Check if migration has been completed.
	 *
	 * @return bool
	 */
	public static function is_migrated() {
		return (bool) get_option(self::MIGRATION_FLAG, false);
	}

	/**
	 * Reset migration flag (for testing/debugging).
	 */
	public static function reset_migration_flag() {
		delete_option(self::MIGRATION_FLAG);
	}
}

