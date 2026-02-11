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
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- SQL identifiers are controlled by plugin schema; runtime values are prepared.

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
				'message' => __('Credit usage migration already completed.', 'opptiai-alt'),
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
				/* translators: 1: number of usage records */
				__('Migration completed. Migrated %d usage records.', 'opptiai-alt'),
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
		$credit_table = Credit_Usage_Logger::table();

		// Find generation log entries
		$alt_text_like = '%' . $wpdb->esc_like('alt text') . '%';
		$alt_text_like_cap = '%' . $wpdb->esc_like('Alt text') . '%';
		$attachment_id_like = '%' . $wpdb->esc_like('attachment_id') . '%';
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, message, context, source, user_id, created_at FROM `' . $wpdb->prefix . 'bbai_logs` WHERE (source = %s OR message LIKE %s OR message LIKE %s) AND context LIKE %s ORDER BY created_at ASC LIMIT %d',
				'generation',
				$alt_text_like,
				$alt_text_like_cap,
				$attachment_id_like,
				1000
			),
			ARRAY_A
		);

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

			// Check if already migrated.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . $wpdb->prefix . 'bbai_credit_usage` WHERE attachment_id = %d AND generated_at = %s',
					$attachment_id,
					$log['created_at']
				)
			);

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
			$posts_table = esc_sql($wpdb->posts);
			$postmeta_table = esc_sql($wpdb->postmeta);

		// Find attachments with AI-generated alt text metadata (check both old and new keys)
		$image_mime_like = $wpdb->esc_like('image/') . '%';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table names are escaped with esc_sql(); queries are prepared.
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value, post_author, post_date FROM `{$posts_table}` p INNER JOIN `{$postmeta_table}` pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND (pm.meta_key = %s OR pm.meta_key = %s) AND pm.meta_value IS NOT NULL AND pm.meta_value != %s LIMIT %d",
				'attachment',
				$image_mime_like,
				'_beepbeepai_generated_at',
				'_ai_alt_generated_at',
				'',
				1000
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

		if (empty($attachments)) {
			return 0;
		}

		$migrated = 0;

		foreach ($attachments as $attachment) {
			$attachment_id = absint($attachment['post_id']);
			if ($attachment_id <= 0) {
				continue;
			}

			// Check if already migrated.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM `' . $wpdb->prefix . 'bbai_credit_usage` WHERE attachment_id = %d',
					$attachment_id
				)
			);

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
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter
}
