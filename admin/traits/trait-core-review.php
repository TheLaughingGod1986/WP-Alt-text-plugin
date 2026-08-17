<?php
/**
 * Core Review Trait
 * Handles alt text health evaluation and review scoring
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Core_Review {

    /**
     * Hash alt text for comparison
     *
     * @param string $alt Alt text to hash
     * @return string Hash
     */
    private function hash_alt_text(string $alt): string {
        $alt = strtolower(trim((string) $alt));
        $alt = preg_replace('/\s+/', ' ', $alt);
        return wp_hash($alt);
    }

    /**
     * Purge review meta from attachment
     *
     * @param int $attachment_id Attachment ID
     */
    private function purge_review_meta(int $attachment_id): void {
        $keys = [
            '_bbai_review_score',
            '_bbai_review_status',
            '_bbai_review_grade',
            '_bbai_review_summary',
            '_bbai_review_issues',
            '_bbai_review_model',
            '_bbai_reviewed_at',
            '_bbai_review_alt_hash',
            '_bbai_review_version',
        ];
        foreach ($keys as $key) {
            delete_post_meta($attachment_id, $key);
        }
    }

    /**
     * Get stored review snapshot
     *
     * @param int    $attachment_id Attachment ID
     * @param string $current_alt   Current alt text for comparison
     * @return array|null Review data or null
     */
    /**
     * Scoring system version. Bump this to invalidate all cached reviews
     * scored under a previous (weaker) rubric.
     *
     * Trait constants are unsupported on older PHP runtimes, so keep this as a method.
     */
    private function review_scoring_version(): int {
        return 3;
    }

    private function get_review_snapshot(int $attachment_id, string $current_alt = ''): ?array {
        $score = intval(get_post_meta($attachment_id, '_bbai_review_score', true));
        if ($score <= 0) {
            return null;
        }

        // Purge reviews scored under an older scoring system version.
        $stored_version = intval(get_post_meta($attachment_id, '_bbai_review_version', true));
        if ($stored_version < $this->review_scoring_version()) {
            $this->purge_review_meta($attachment_id);
            return null;
        }

        $stored_hash = get_post_meta($attachment_id, '_bbai_review_alt_hash', true);
        if ($current_alt !== '') {
            $current_hash = $this->hash_alt_text($current_alt);
            if ($stored_hash && !hash_equals($stored_hash, $current_hash)) {
                $this->purge_review_meta($attachment_id);
                return null;
            }
        }

        $status     = sanitize_key(get_post_meta($attachment_id, '_bbai_review_status', true));
        $grade_input  = get_post_meta($attachment_id, '_bbai_review_grade', true);
        $summary    = get_post_meta($attachment_id, '_bbai_review_summary', true);
        $model      = get_post_meta($attachment_id, '_bbai_review_model', true);
        $reviewed_at = get_post_meta($attachment_id, '_bbai_reviewed_at', true);

        $issues_input = get_post_meta($attachment_id, '_bbai_review_issues', true);
        $issues = [];
        if ($issues_input) {
            $decoded = json_decode($issues_input, true);
            if (is_array($decoded)) {
                foreach ($decoded as $issue) {
                    if (is_string($issue)) {
                        $issue = sanitize_text_field($issue);
                        if ($issue !== '') {
                            $issues[] = $issue;
                        }
                    }
                }
            }
        }

        return [
            'score'   => max(0, min(100, $score)),
            'status'  => $status ?: null,
            'grade'   => is_string($grade_input) ? sanitize_text_field($grade_input) : null,
            'summary' => is_string($summary) ? sanitize_text_field($summary) : '',
            'issues'  => $issues,
            'model'   => is_string($model) ? sanitize_text_field($model) : '',
            'reviewed_at' => is_string($reviewed_at) ? $reviewed_at : '',
            'hash_present' => !empty($stored_hash),
        ];
    }

    /**
     * Evaluate alt text health using the unified scoring engine.
     *
     * Delegates to BBAI_Alt_Quality_Scorer for deterministic scoring,
     * then merges with any cached LLM review (taking the stricter score).
     *
     * @param int    $attachment_id Attachment ID
     * @param string $alt           Alt text to evaluate
     * @return array Health evaluation result
     */
    private function evaluate_alt_health(int $attachment_id, string $alt): array {
        $alt = trim((string) $alt);
        if ($alt === '') {
            return [
                'score' => 0,
                'grade' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'beepbeep-ai-alt-text-generator')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'beepbeep-ai-alt-text-generator')],
                ],
                'review' => null,
            ];
        }

        // Build context from attachment metadata.
        $context = [];
        $title = get_the_title($attachment_id);
        if ($title) {
            $context['title'] = $title;
        }
        $file = get_attached_file($attachment_id);
        if ($file) {
            $context['filename'] = basename($file);
        }
        $context['attachment_id'] = $attachment_id;

        // Load unified scorer.
        if (!class_exists('BBAI_Alt_Quality_Scorer')) {
            $scorer_path = defined('BEEPBEEP_AI_PLUGIN_DIR')
                ? BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-alt-quality-scorer.php'
                : dirname(dirname(__FILE__)) . '/../includes/class-alt-quality-scorer.php';
            if (file_exists($scorer_path)) {
                require_once $scorer_path;
            }
        }

        // Get deterministic score from unified engine.
        if (class_exists('BBAI_Alt_Quality_Scorer')) {
            $scored = \BBAI_Alt_Quality_Scorer::score($alt, $context);
        } else {
            // Fallback: minimal scoring — never mark as optimized-eligible without the real engine.
            $scored = [
                'score' => 50,
                'label' => bbai_copy_score_needs_improvement(),
                'grade' => 'C',
                'issues' => [],
                'suggestions' => [],
                'hard_fail' => false,
                'optimized_eligible' => false,
                'breakdown' => null,
            ];
        }

        $score  = (int) $scored['score'];
        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        $heuristic = [
            'score'  => $score,
            'grade'  => $grade,
            'status' => $status,
            'issues' => $scored['issues'] ?? [],
        ];

        // Merge with cached LLM review if available.
        $review = $this->get_review_snapshot($attachment_id, $alt);
        if ($review && empty($review['hash_present']) && $heuristic['score'] < $review['score']) {
            $review = null;
        }
        if ($review) {
            $final_score   = min($heuristic['score'], $review['score']);
            $review_status = $review['status'] ?: $this->status_from_score($review['score']);
            $final_status  = $this->worst_status($heuristic['status'], $review_status);
            $final_grade   = $review['grade'] ?: $this->grade_from_status($final_status);

            $combined_issues = [];
            if (!empty($review['summary'])) {
                $combined_issues[] = $review['summary'];
            }
            if (!empty($review['issues'])) {
                $combined_issues = array_merge($combined_issues, $review['issues']);
            }
            $combined_issues = array_merge($combined_issues, $heuristic['issues']);
            $combined_issues = array_values(array_unique(array_filter($combined_issues)));

            $hard_fail = !empty($scored['hard_fail']);
            $optimized_eligible = class_exists( '\BBAI_Alt_Quality_Scorer' )
                ? \BBAI_Alt_Quality_Scorer::passes_optimized_row_gates(
                    $alt,
                    $final_score,
                    isset( $scored['breakdown'] ) && is_array( $scored['breakdown'] ) ? $scored['breakdown'] : null,
                    $hard_fail
                )
                : false;

            $result = [
                'score'              => $final_score,
                'grade'              => $final_grade,
                'status'             => $final_status,
                'issues'             => $combined_issues,
                'heuristic'          => $heuristic,
                'review'             => $review,
                'breakdown'          => $scored['breakdown'] ?? null,
                'suggestions'        => $scored['suggestions'] ?? [],
                'hard_fail'          => $hard_fail,
                'optimized_eligible' => $optimized_eligible,
            ];

            return $this->apply_user_approval_to_alt_health_result($result, $attachment_id, $alt);
        }

        $hard_fail = !empty($scored['hard_fail']);
        $optimized_eligible = class_exists( '\BBAI_Alt_Quality_Scorer' )
            ? \BBAI_Alt_Quality_Scorer::passes_optimized_row_gates(
                $alt,
                $score,
                isset( $scored['breakdown'] ) && is_array( $scored['breakdown'] ) ? $scored['breakdown'] : null,
                $hard_fail
            )
            : false;

        $result = [
            'score'              => $heuristic['score'],
            'grade'              => $heuristic['grade'],
            'status'             => $heuristic['status'],
            'issues'             => $heuristic['issues'],
            'heuristic'          => $heuristic,
            'review'             => null,
            'breakdown'          => $scored['breakdown'] ?? null,
            'suggestions'        => $scored['suggestions'] ?? [],
            'hard_fail'          => $hard_fail,
            'optimized_eligible' => $optimized_eligible,
        ];

        return $this->apply_user_approval_to_alt_health_result($result, $attachment_id, $alt);
    }

    /**
     * Attach user approval metadata without inflating low-quality scores to “optimized”.
     *
     * @param array  $result        Alt health array.
     * @param int    $attachment_id Attachment ID.
     * @param string $alt           Current alt text.
     * @return array
     */
    private function apply_user_approval_to_alt_health_result(array $result, int $attachment_id, string $alt): array {
        $approval = $this->get_user_approval_snapshot($attachment_id, $alt);
        if (!$approval) {
            return $result;
        }

        $result['user_approved'] = true;
        $result['approved_at'] = $approval['approved_at'];

        if (!empty($result['review']) && is_array($result['review'])) {
            $result['review'] = array_merge($result['review'], [
                'summary'       => __('Reviewed and approved in the ALT Library.', 'beepbeep-ai-alt-text-generator'),
                'user_approved' => true,
                'approved_at'   => $approval['approved_at'],
            ]);
        } else {
            $result['review'] = [
                'summary'       => __('Reviewed and approved in the ALT Library.', 'beepbeep-ai-alt-text-generator'),
                'user_approved' => true,
                'approved_at'   => $approval['approved_at'],
            ];
        }

        return $result;
    }

    /**
     * Get status from score
     *
     * @param int $score Score value
     * @return string Status key
     */
    private function status_from_score(int $score): string {
        if ($score >= 90) {
            return 'great';
        }
        if ($score >= 75) {
            return 'good';
        }
        if ($score >= 60) {
            return 'review';
        }
        return 'critical';
    }

    /**
     * Get grade from status
     *
     * @param string $status Status key
     * @return string Grade label
     */
    private function grade_from_status(string $status): string {
        switch ($status) {
            case 'great':
                return bbai_copy_score_excellent();
            case 'good':
                return bbai_copy_score_good();
            case 'review':
                return bbai_copy_score_needs_improvement();
            default:
                return bbai_copy_score_critical();
        }
    }

    /**
     * Get worst status of two
     *
     * @param string $first  First status
     * @param string $second Second status
     * @return string Worst status
     */
    private function worst_status(string $first, string $second): string {
        $weights = [
            'great' => 1,
            'good' => 2,
            'review' => 3,
            'critical' => 4,
        ];
        $first_weight = $weights[$first] ?? 2;
        $second_weight = $weights[$second] ?? 2;
        return $first_weight >= $second_weight ? $first : $second;
    }

    /**
     * Review alt text with AI model
     *
     * @param int    $attachment_id Attachment ID
     * @param string $alt_text      Alt text to review
     * @param bool   $force         Force review even if cached
     * @return array|WP_Error Review result or error
     */
    public function review_alt_text_with_model($attachment_id, $alt_text, $force = false) {
        $attachment_id = intval($attachment_id);
        $alt_text = sanitize_text_field($alt_text);

        if ($attachment_id <= 0 || $alt_text === '') {
            return new \WP_Error('invalid_input', __('Invalid attachment ID or alt text.', 'beepbeep-ai-alt-text-generator'));
        }

        if (!$force) {
            $existing = $this->get_review_snapshot($attachment_id, $alt_text);
            if ($existing && !empty($existing['hash_present'])) {
                return $existing;
            }
        }

        // Build context for the LLM review.
        $image_context = [];
        $title = get_the_title($attachment_id);
        if ($title) {
            $image_context['title'] = $title;
        }
        $file = get_attached_file($attachment_id);
        if ($file) {
            $image_context['filename'] = basename($file);
        }

        // Use the strict system prompt from the unified scorer.
        $system_prompt = '';
        $user_prompt   = sprintf(
            'Review the following ALT text for accessibility quality. Score it 0-100 and identify issues. ALT text: "%s"

Return JSON only:
{
  "score": <0-100>,
  "grade": "<Excellent|Strong|Needs review|Critical>",
  "status": "<great|good|review|critical>",
  "summary": "<one sentence summary>",
  "issues": ["<issue 1>", "<issue 2>"]
}',
            $alt_text
        );

        if (class_exists('BBAI_Alt_Quality_Scorer')) {
            $system_prompt = \BBAI_Alt_Quality_Scorer::get_review_system_prompt();
            $user_prompt   = \BBAI_Alt_Quality_Scorer::get_review_user_prompt($alt_text, $image_context);
        }

        // Check if chat_completion method exists (may not be available in all API client versions)
        if (!method_exists($this->api_client, 'chat_completion')) {
            return new \WP_Error(
                'method_not_available',
                __('Alt text review is not available. The API client does not support chat completion.', 'beepbeep-ai-alt-text-generator')
            );
        }

        $messages = [];
        if ($system_prompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $user_prompt];

        $response = $this->api_client->chat_completion([
            'messages'   => $messages,
            'max_tokens' => 500,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        $parsed = $this->extract_json_object($content);

        if (!$parsed || !isset($parsed['score'])) {
            return new \WP_Error('parse_error', __('Unable to parse review response.', 'beepbeep-ai-alt-text-generator'));
        }

        $score = max(0, min(100, intval($parsed['score'])));
        $status = $parsed['status'] ?? $this->status_from_score($score);
        $grade = $parsed['grade'] ?? $this->grade_from_status($status);
        $summary = sanitize_text_field($parsed['summary'] ?? '');
        $issues = [];
        if (isset($parsed['issues']) && is_array($parsed['issues'])) {
            foreach ($parsed['issues'] as $issue) {
                if (is_string($issue)) {
                    $issues[] = sanitize_text_field($issue);
                }
            }
        }

        // Hard-fail guard: the LLM must not override deterministic hard-fail caps.
        if (class_exists('BBAI_Alt_Quality_Scorer')) {
            $deterministic = \BBAI_Alt_Quality_Scorer::score($alt_text, $image_context);
            if ($deterministic['hard_fail'] && $score > $deterministic['cap']) {
                $score  = $deterministic['cap'];
                $status = $this->status_from_score($score);
                $grade  = $this->grade_from_status($status);
                $issues = array_merge($deterministic['issues'], $issues);
                $issues = array_values(array_unique($issues));
            }
        }

        $model = $response['model'] ?? 'unknown';
        $hash = $this->hash_alt_text($alt_text);

        update_post_meta($attachment_id, '_bbai_review_score', $score);
        update_post_meta($attachment_id, '_bbai_review_status', sanitize_key($status));
        update_post_meta($attachment_id, '_bbai_review_grade', $grade);
        update_post_meta($attachment_id, '_bbai_review_summary', $summary);
        update_post_meta($attachment_id, '_bbai_review_issues', wp_json_encode($issues));
        update_post_meta($attachment_id, '_bbai_review_model', sanitize_text_field($model));
        update_post_meta($attachment_id, '_bbai_reviewed_at', current_time('mysql'));
        update_post_meta($attachment_id, '_bbai_review_alt_hash', $hash);
        update_post_meta($attachment_id, '_bbai_review_version', $this->review_scoring_version());

        return [
            'score' => $score,
            'status' => $status,
            'grade' => $grade,
            'summary' => $summary,
            'issues' => $issues,
            'model' => $model,
            'reviewed_at' => current_time('mysql'),
            'hash_present' => true,
        ];
    }
}
