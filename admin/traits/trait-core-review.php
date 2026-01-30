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
    private function get_review_snapshot(int $attachment_id, string $current_alt = ''): ?array {
        $score = intval(get_post_meta($attachment_id, '_bbai_review_score', true));
        if ($score <= 0) {
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
        $grade_raw  = get_post_meta($attachment_id, '_bbai_review_grade', true);
        $summary    = get_post_meta($attachment_id, '_bbai_review_summary', true);
        $model      = get_post_meta($attachment_id, '_bbai_review_model', true);
        $reviewed_at = get_post_meta($attachment_id, '_bbai_reviewed_at', true);

        $issues_raw = get_post_meta($attachment_id, '_bbai_review_issues', true);
        $issues = [];
        if ($issues_raw) {
            $decoded = json_decode($issues_raw, true);
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
            'grade'   => is_string($grade_raw) ? sanitize_text_field($grade_raw) : null,
            'summary' => is_string($summary) ? sanitize_text_field($summary) : '',
            'issues'  => $issues,
            'model'   => is_string($model) ? sanitize_text_field($model) : '',
            'reviewed_at' => is_string($reviewed_at) ? $reviewed_at : '',
            'hash_present' => !empty($stored_hash),
        ];
    }

    /**
     * Evaluate alt text health using heuristics
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
                'grade' => __('Missing', 'opptiai-alt'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'opptiai-alt')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'opptiai-alt'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'opptiai-alt')],
                ],
                'review' => null,
            ];
        }

        $score = 100;
        $issues = [];

        $normalized = strtolower(trim($alt));
        $placeholder_pattern = '/^(test|testing|sample|example|dummy|placeholder|alt(?:\s+text)?|image|photo|picture|n\/a|none|lorem)$/';
        if ($normalized === '' || preg_match($placeholder_pattern, $normalized)) {
            return [
                'score' => 0,
                'grade' => __('Critical', 'opptiai-alt'),
                'status' => 'critical',
                'issues' => [__('ALT text looks like placeholder content and must be rewritten.', 'opptiai-alt')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Critical', 'opptiai-alt'),
                    'status'=> 'critical',
                    'issues'=> [__('ALT text looks like placeholder content and must be rewritten.', 'opptiai-alt')],
                ],
                'review' => null,
            ];
        }

        $length = function_exists('mb_strlen') ? mb_strlen($alt) : strlen($alt);
        if ($length < 45) {
            $score -= 35;
            $issues[] = __('Too short – add a richer description (45+ characters).', 'opptiai-alt');
        } elseif ($length > 160) {
            $score -= 15;
            $issues[] = __('Very long – trim to keep the description concise (under 160 characters).', 'opptiai-alt');
        }

        if (preg_match('/\b(image|picture|photo|screenshot)\b/i', $alt)) {
            $score -= 10;
            $issues[] = __('Contains generic filler words like "image" or "photo".', 'opptiai-alt');
        }

        if (preg_match('/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt)) {
            $score = min($score - 80, 5);
            $issues[] = __('Contains placeholder wording such as "test" or "sample". Replace with a real description.', 'opptiai-alt');
        }

        $word_count = str_word_count($alt, 0, '0123456789');
        if ($word_count < 4) {
            $score -= 70;
            $score = min($score, 5);
            $issues[] = __('ALT text is extremely brief – add meaningful descriptive words.', 'opptiai-alt');
        } elseif ($word_count < 6) {
            $score -= 50;
            $score = min($score, 20);
            $issues[] = __('ALT text is too short to convey the subject in detail.', 'opptiai-alt');
        } elseif ($word_count < 8) {
            $score -= 35;
            $score = min($score, 40);
            $issues[] = __('ALT text could use a few more descriptive words.', 'opptiai-alt');
        }

        if ($score > 40 && $length < 30) {
            $score = min($score, 40);
            $issues[] = __('Expand the description with one or two concrete details.', 'opptiai-alt');
        }

        $normalize = static function($value) {
            $value = strtolower((string) $value);
            $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
            return trim(preg_replace('/\s+/', ' ', $value));
        };

        $normalized_alt = $normalize($alt);
        $title = get_the_title($attachment_id);
        if ($title && $normalized_alt !== '') {
            $normalized_title = $normalize($title);
            if ($normalized_title !== '' && $normalized_alt === $normalized_title) {
                $score -= 12;
                $issues[] = __('Matches the attachment title – add more unique detail.', 'opptiai-alt');
            }
        }

        $file = get_attached_file($attachment_id);
        if ($file && $normalized_alt !== '') {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $normalized_base = $normalize($base);
            if ($normalized_base !== '' && $normalized_alt === $normalized_base) {
                $score -= 20;
                $issues[] = __('Matches the file name – rewrite it to describe the image.', 'opptiai-alt');
            }
        }

        if (!preg_match('/[a-z]{4,}/i', $alt)) {
            $score -= 15;
            $issues[] = __('Lacks descriptive language – include meaningful nouns or adjectives.', 'opptiai-alt');
        }

        if (!preg_match('/\b[a-z]/i', $alt)) {
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        if ($status === 'review' && empty($issues)) {
            $issues[] = __('Give this ALT another look to ensure it reflects the image details.', 'opptiai-alt');
        } elseif ($status === 'critical' && empty($issues)) {
            $issues[] = __('ALT text should be rewritten for accessibility.', 'opptiai-alt');
        }

        $heuristic = [
            'score' => $score,
            'grade' => $grade,
            'status'=> $status,
            'issues'=> array_values(array_unique($issues)),
        ];

        $review = $this->get_review_snapshot($attachment_id, $alt);
        if ($review && empty($review['hash_present']) && $heuristic['score'] < $review['score']) {
            $review = null;
        }
        if ($review) {
            $final_score = min($heuristic['score'], $review['score']);
            $review_status = $review['status'] ?: $this->status_from_score($review['score']);
            $final_status = $this->worst_status($heuristic['status'], $review_status);
            $final_grade  = $review['grade'] ?: $this->grade_from_status($final_status);

            $combined_issues = [];
            if (!empty($review['summary'])) {
                $combined_issues[] = $review['summary'];
            }
            if (!empty($review['issues'])) {
                $combined_issues = array_merge($combined_issues, $review['issues']);
            }
            $combined_issues = array_merge($combined_issues, $heuristic['issues']);
            $combined_issues = array_values(array_unique(array_filter($combined_issues)));

            return [
                'score' => $final_score,
                'grade' => $final_grade,
                'status'=> $final_status,
                'issues'=> $combined_issues,
                'heuristic' => $heuristic,
                'review'    => $review,
            ];
        }

        return [
            'score' => $heuristic['score'],
            'grade' => $heuristic['grade'],
            'status'=> $heuristic['status'],
            'issues'=> $heuristic['issues'],
            'heuristic' => $heuristic,
            'review'    => null,
        ];
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
                return __('Excellent', 'opptiai-alt');
            case 'good':
                return __('Strong', 'opptiai-alt');
            case 'review':
                return __('Needs review', 'opptiai-alt');
            default:
                return __('Critical', 'opptiai-alt');
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
            return new \WP_Error('invalid_input', __('Invalid attachment ID or alt text.', 'opptiai-alt'));
        }

        if (!$force) {
            $existing = $this->get_review_snapshot($attachment_id, $alt_text);
            if ($existing && !empty($existing['hash_present'])) {
                return $existing;
            }
        }

        $prompt = sprintf(
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

        // Check if chat_completion method exists (may not be available in all API client versions)
        if (!method_exists($this->api_client, 'chat_completion')) {
            return new \WP_Error(
                'method_not_available',
                __('Alt text review is not available. The API client does not support chat completion.', 'opptiai-alt')
            );
        }

        $response = $this->api_client->chat_completion([
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 500,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        $parsed = $this->extract_json_object($content);

        if (!$parsed || !isset($parsed['score'])) {
            return new \WP_Error('parse_error', __('Unable to parse review response.', 'opptiai-alt'));
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
