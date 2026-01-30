<?php
/**
 * SEO Quality Checker
 *
 * Validates alt text against SEO best practices for Google Images optimization
 *
 * @package BeepBeepAI_AltText
 * @since 4.2.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class BBAI_SEO_Quality_Checker {

    /**
     * Check if alt text starts with redundant phrases
     *
     * @param string $text The alt text to check
     * @return bool
     */
    public static function has_redundant_prefix($text) {
        if (empty($text)) {
            return false;
        }

        $lower_text = strtolower(trim($text));
        $redundant_prefixes = [
            'image of',
            'picture of',
            'photo of',
            'photograph of',
            'graphic of',
            'illustration of',
            'image showing',
            'picture showing',
            'photo showing',
        ];

        foreach ($redundant_prefixes as $prefix) {
            if (str_starts_with($lower_text, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if alt text is just a filename
     *
     * @param string $text The alt text to check
     * @return bool
     */
    public static function is_just_filename($text) {
        if (empty($text)) {
            return false;
        }

        $trimmed = trim($text);

        // Check for common filename patterns
        $filename_patterns = [
            '/^IMG[-_]\d+/i',           // IMG_1234, IMG-5678
            '/^DSC[-_]\d+/i',           // DSC_1234, DSC-5678
            '/^\d{8}[-_]\d+/i',         // 20230101_123456
            '/^screenshot[-_]/i',       // screenshot_2023
            '/^image[-_]\d+/i',         // image_001, image-02
            '/\.(jpg|jpeg|png|gif|webp)$/i',  // ends with file extension
        ];

        foreach ($filename_patterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if alt text has meaningful descriptive content
     *
     * @param string $text The alt text to check
     * @return bool
     */
    public static function has_descriptive_content($text) {
        if (empty($text)) {
            return false;
        }

        // Should have at least 3 words for meaningful description
        $words = preg_split('/\s+/', trim($text));
        if (count($words) < 3) {
            return false;
        }

        // Check if at least one word is substantial (>3 characters)
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate SEO quality score for alt text
     *
     * @param string $text The alt text to check
     * @return array {
     *     @type int    $score  Quality score (0-100)
     *     @type string $grade  Letter grade (A-F)
     *     @type array  $issues List of quality issues
     *     @type string $badge  Badge type (excellent|good|fair|poor|needs-work|missing)
     * }
     */
    public static function calculate_quality($text) {
        $issues = [];
        $score = 100;

        if (empty($text) || trim($text) === '') {
            return [
                'score' => 0,
                'grade' => 'F',
                'issues' => [__('No alt text provided', 'opptiai-alt')],
                'badge' => 'missing',
            ];
        }

        $text_length = mb_strlen($text);

        // Check length (125 chars recommended for Google Images)
        if ($text_length > 125) {
            $issues[] = sprintf(
                /* translators: 1: character count */
                __('Too long (%d chars). Aim for ≤125 for optimal Google Images SEO', 'opptiai-alt'),
                $text_length
            );
            $score -= 25;
        }

        // Check for redundant prefixes
        if (self::has_redundant_prefix($text)) {
            $issues[] = __('Starts with "image of" or similar. Remove redundant prefix', 'opptiai-alt');
            $score -= 20;
        }

        // Check if it's just a filename
        if (self::is_just_filename($text)) {
            $issues[] = __('Appears to be a filename. Use descriptive text instead', 'opptiai-alt');
            $score -= 30;
        }

        // Check for descriptive content
        if (!self::has_descriptive_content($text)) {
            $issues[] = __('Too short or lacks descriptive keywords', 'opptiai-alt');
            $score -= 15;
        }

        // Determine grade and badge
        $score = max(0, $score);

        if ($score >= 90) {
            $grade = 'A';
            $badge = 'excellent';
        } elseif ($score >= 75) {
            $grade = 'B';
            $badge = 'good';
        } elseif ($score >= 60) {
            $grade = 'C';
            $badge = 'fair';
        } elseif ($score >= 40) {
            $grade = 'D';
            $badge = 'poor';
        } else {
            $grade = 'F';
            $badge = 'needs-work';
        }

        return [
            'score' => $score,
            'grade' => $grade,
            'issues' => $issues,
            'badge' => $badge,
        ];
    }

    /**
     * Generate SEO quality badge HTML with detailed tooltip breakdown
     *
     * @param string $text The alt text to check
     * @return string HTML for quality badge
     */
    public static function create_badge($text) {
        $quality = self::calculate_quality($text);

        if ($quality['badge'] === 'missing') {
            return '';
        }

        $char_count = mb_strlen($text);
        $badge_class = 'bbai-seo-badge bbai-seo-badge--' . esc_attr($quality['badge']);

        // Build detailed tooltip content
        $tooltip = self::build_tooltip_content($text, $quality, $char_count);

        return sprintf(
            '<span class="%s" data-bbai-tooltip="%s" data-bbai-tooltip-position="top">SEO: %s</span>',
            $badge_class,
            esc_attr($tooltip),
            esc_html($quality['grade'])
        );
    }

    /**
     * Build detailed tooltip content for SEO quality badge
     *
     * @param string $text The alt text
     * @param array $quality Quality data from calculate_quality()
     * @param int $char_count Character count
     * @return string Formatted tooltip content
     */
    private static function build_tooltip_content($text, $quality, $char_count) {
        $lines = [];

        // Header with grade and score
        $lines[] = sprintf(
            /* translators: 1: grade, 2: score */
            __('SEO Score: %1$s (%2$d/100)', 'opptiai-alt'),
            $quality['grade'],
            $quality['score']
        );
        $lines[] = '';

        // Character length check
        $length_status = $char_count <= 125 ? '✓' : '✗';
        $length_label = $char_count <= 125
            ? __('Optimal length', 'opptiai-alt')
            : __('Too long', 'opptiai-alt');
        $lines[] = sprintf('%s %s (%d/125)', $length_status, $length_label, $char_count);

        // Redundant prefix check
        $has_prefix = self::has_redundant_prefix($text);
        $prefix_status = $has_prefix ? '✗' : '✓';
        $prefix_label = $has_prefix
            ? __('Remove "image of" prefix', 'opptiai-alt')
            : __('No redundant prefix', 'opptiai-alt');
        $lines[] = sprintf('%s %s', $prefix_status, $prefix_label);

        // Filename check
        $is_filename = self::is_just_filename($text);
        $filename_status = $is_filename ? '✗' : '✓';
        $filename_label = $is_filename
            ? __('Looks like a filename', 'opptiai-alt')
            : __('Descriptive text', 'opptiai-alt');
        $lines[] = sprintf('%s %s', $filename_status, $filename_label);

        // Descriptive content check
        $is_descriptive = self::has_descriptive_content($text);
        $descriptive_status = $is_descriptive ? '✓' : '✗';
        $descriptive_label = $is_descriptive
            ? __('Good keyword content', 'opptiai-alt')
            : __('Needs more detail', 'opptiai-alt');
        $lines[] = sprintf('%s %s', $descriptive_status, $descriptive_label);

        // Add tip for non-A grades
        if ($quality['grade'] !== 'A') {
            $lines[] = '';
            $lines[] = __('Tip: Regenerate for better SEO', 'opptiai-alt');
        }

        return implode("\n", $lines);
    }

    /**
     * Generate unified SEO quality badge HTML combining grade and character count
     *
     * This is the preferred method for displaying SEO quality - it shows a single
     * badge with the grade and character count, with a detailed tooltip breakdown.
     *
     * @param string $text The alt text to check
     * @return string HTML for unified quality badge
     * @since 5.0.0
     */
    public static function create_unified_badge($text) {
        $quality = self::calculate_quality($text);

        // For missing alt text, return a dash indicator
        if ($quality['badge'] === 'missing') {
            return '<span class="bbai-seo-unified-badge bbai-seo-unified-badge--empty" data-bbai-tooltip="' . esc_attr__('No alt text', 'opptiai-alt') . '">—</span>';
        }

        $char_count = mb_strlen($text);
        $badge_class = 'bbai-seo-unified-badge bbai-seo-unified-badge--' . esc_attr($quality['badge']);

        // Build detailed tooltip content
        $tooltip = self::build_tooltip_content($text, $quality, $char_count);

        // Format: "A (84)" - grade with character count
        return sprintf(
            '<span class="%s" data-bbai-tooltip="%s" data-bbai-tooltip-position="top">%s <span class="bbai-seo-unified-badge__chars">(%d)</span></span>',
            $badge_class,
            esc_attr($tooltip),
            esc_html($quality['grade']),
            $char_count
        );
    }
}
