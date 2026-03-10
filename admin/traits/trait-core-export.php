<?php
/**
 * Core Export Trait
 * Handles usage and debug log CSV exports
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Debug_Log;

trait Core_Export {

    /**
     * Sanitize and neutralize CSV cell values to prevent spreadsheet formula injection.
     *
     * @param mixed $value
     * @param bool  $allow_html Whether to allow safe post HTML via wp_kses_post().
     * @return string
     */
    private function bbai_csv_safe_cell($value, bool $allow_html = false): string {
        if ($value === null) {
            $value = '';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $value = (string) $value;
        } else {
            $encoded = wp_json_encode($value);
            $value = $encoded === false ? '' : (string) $encoded;
        }

        // CSV isn't HTML, but output is user-controlled; sanitize to avoid exporting unsafe content.
        $value = $allow_html ? wp_kses_post($value) : sanitize_text_field($value);

        // Prefix every non-empty value to prevent CSV injection in spreadsheet software.
        if ($value !== '' && strpos($value, "\t") !== 0) {
            $value = "\t" . $value;
        }

        return $value;
    }

    /**
     * Build a single CSV row (RFC4180-style) without using file handles.
     *
     * @param array $fields
     * @return string
     */
    private function bbai_csv_row(array $fields): string {
        $delimiter = ',';
        $enclosure = '"';

        $out = [];
        foreach ($fields as $field) {
            if ($field === null) {
                $field = '';
            } elseif (is_bool($field)) {
                $field = $field ? '1' : '0';
            } elseif (is_scalar($field)) {
                $field = (string) $field;
            } else {
                $encoded = wp_json_encode($field);
                $field = $encoded === false ? '' : (string) $encoded;
            }

            $needs_enclosure = strpbrk($field, $delimiter . $enclosure . "\r\n\t\v\0") !== false;
            if ($needs_enclosure) {
                $field = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
            }
            $out[] = $field;
        }

        return implode($delimiter, $out) . "\n";
    }

    /**
     * Stream raw CSV/text bytes to the HTTP response.
     *
     * @param string $contents
     * @return void
     */
    private function bbai_output_contents(string $contents): void {
        // Streaming CSV/text downloads; escaping would corrupt the file content.
        echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Clean active output buffers before streaming file downloads.
     *
     * @return void
     */
    private function bbai_prepare_download_stream(): void {
        while (ob_get_level() > 0) {
            $status = ob_get_status();
            $flags = isset($status['flags']) ? (int) $status['flags'] : 0;
            if (($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                break;
            }
            ob_end_clean();
        }
    }

    public function handle_usage_export() {
        check_admin_referer('bbai_usage_export');
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('You do not have permission to export usage data.', 'beepbeep-ai-alt-text-generator'));
        }

        $rows = $this->get_usage_rows(10, true);
        $filename = 'bbai-usage-' . gmdate('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $this->bbai_prepare_download_stream();
        $this->bbai_output_contents($this->bbai_csv_row(['Attachment ID', 'Title', 'ALT Text', 'Source', 'Model', 'Generated At']));
        foreach ($rows as $row) {
            $this->bbai_output_contents($this->bbai_csv_row([
                $this->bbai_csv_safe_cell($row['id'] ?? ''),
                $this->bbai_csv_safe_cell($row['title'] ?? ''),
                $this->bbai_csv_safe_cell($row['alt'] ?? ''),
                $this->bbai_csv_safe_cell($row['source'] ?? ''),
                $this->bbai_csv_safe_cell($row['tokens'] ?? ''),
                $this->bbai_csv_safe_cell($row['prompt'] ?? ''),
                $this->bbai_csv_safe_cell($row['completion'] ?? ''),
                $this->bbai_csv_safe_cell($row['model'] ?? ''),
                '',
            ]));
        }
        exit;
    }

    public function handle_debug_log_export() {
        check_admin_referer('bbai_debug_export');
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('You do not have permission to export debug logs.', 'beepbeep-ai-alt-text-generator'));
        }

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            wp_die(esc_html__('Debug logging is not available.', 'beepbeep-ai-alt-text-generator'));
        }

        global $wpdb;
        $table = esc_sql(Debug_Log::table());
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                "SELECT * FROM `{$table}` WHERE %d = %d ORDER BY created_at DESC",
                1,
                1
            ),
            ARRAY_A
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bbai-debug-logs-' . gmdate('Ymd-His') . '.csv');

        $this->bbai_prepare_download_stream();
        $this->bbai_output_contents($this->bbai_csv_row(['Timestamp', 'Level', 'Message', 'Source', 'Context']));
        foreach ($rows as $row) {
            $context = $row['context'] ?? '';
            $this->bbai_output_contents($this->bbai_csv_row([
                $this->bbai_csv_safe_cell($row['created_at'] ?? ''),
                $this->bbai_csv_safe_cell($row['level'] ?? ''),
                $this->bbai_csv_safe_cell($row['message'] ?? ''),
                $this->bbai_csv_safe_cell($row['source'] ?? ''),
                $this->bbai_csv_safe_cell($context),
            ]));
        }
        exit;
    }
}
