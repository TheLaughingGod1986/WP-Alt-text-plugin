<?php
/**
 * Phase 14 — Retention loops, lifecycle triggers, re-entry UX (detection + progress + CTAs).
 *
 * Metrics (Phase 12 alignment): client events `retention_reentry_strip_viewed`, `retention_strip_cta_clicked`,
 * `retention_library_nudge_viewed` via bbai-telemetry.js; server may hook `bbai_retention_triggers_evaluated`.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const BBAI_RETENTION_META_PREV_MEDIA_TOTAL = 'bbai_retention_prev_media_total';
const BBAI_RETENTION_META_COVERAGE_HIST    = 'bbai_retention_coverage_history';

/**
 * Estimate new image attachments since the last BB admin visit that ran retention snapshot (not exact uploads).
 */
function bbai_retention_new_uploads_estimate(int $user_id, int $current_total): int {
    if ($user_id <= 0) {
        return 0;
    }
    $prev = (int) get_user_meta($user_id, BBAI_RETENTION_META_PREV_MEDIA_TOTAL, true);
    if ($prev <= 0) {
        return 0;
    }
    return max(0, $current_total - $prev);
}

/**
 * After the current response, store attachment total so the next visit can diff "new" images.
 */
function bbai_retention_schedule_snapshot_update(int $user_id, int $current_total): void {
    if ($user_id <= 0) {
        return;
    }
    static $scheduled = false;
    if ($scheduled) {
        return;
    }
    $scheduled = true;
    add_action(
        'shutdown',
        static function () use ($user_id, $current_total): void {
            update_user_meta($user_id, BBAI_RETENTION_META_PREV_MEDIA_TOTAL, max(0, $current_total));
        },
        30
    );
}

/**
 * One row per calendar day (UTC Ymd) for coarse trend / comparison copy.
 *
 * @return list<array{d:string,p:int,m:int,w:int,t:int}>
 */
function bbai_retention_load_history(int $user_id): array {
    if ($user_id <= 0) {
        return [];
    }
    $raw = get_user_meta($user_id, BBAI_RETENTION_META_COVERAGE_HIST, true);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'd' => (string) ($row['d'] ?? ''),
            'p' => max(0, min(100, (int) ($row['p'] ?? 0))),
            'm' => max(0, (int) ($row['m'] ?? 0)),
            'w' => max(0, (int) ($row['w'] ?? 0)),
            't' => max(0, (int) ($row['t'] ?? 0)),
        ];
    }
    return $out;
}

/**
 * @param list<array{d:string,p:int,m:int,w:int,t:int}> $hist
 */
function bbai_retention_save_history(int $user_id, array $hist): void {
    if ($user_id <= 0) {
        return;
    }
    if (count($hist) > 21) {
        $hist = array_slice($hist, -21);
    }
    update_user_meta($user_id, BBAI_RETENTION_META_COVERAGE_HIST, wp_json_encode($hist));
}

/**
 * Append or replace today's snapshot (UTC).
 *
 * @param list<array{d:string,p:int,m:int,w:int,t:int}> $hist
 *
 * @return list<array{d:string,p:int,m:int,w:int,t:int}>
 */
function bbai_retention_merge_today_snapshot(array $hist, int $coverage_pct, int $missing, int $weak, int $total): array {
    $today = gmdate('Ymd');
    $entry = [
        'd' => $today,
        'p' => max(0, min(100, $coverage_pct)),
        'm' => max(0, $missing),
        'w' => max(0, $weak),
        't' => max(0, $total),
    ];
    $n = count($hist);
    if ($n > 0 && isset($hist[ $n - 1 ]['d']) && (string) $hist[ $n - 1 ]['d'] === $today) {
        $hist[ $n - 1 ] = $entry;
        return $hist;
    }
    $hist[] = $entry;
    return $hist;
}

/**
 * Oldest snapshot at least $min_days old (by UTC Ymd), for "since last week" style copy.
 *
 * @param list<array{d:string,p:int,m:int,w:int,t:int}> $hist
 */
function bbai_retention_baseline_percent(array $hist, int $min_days): ?int {
    if ($hist === []) {
        return null;
    }
    $cutoff = (int) gmdate('Ymd', strtotime('-' . max(1, $min_days) . ' days'));
    $best = null;
    foreach ($hist as $row) {
        $d = (int) ($row['d'] ?? 0);
        if ($d > 0 && $d <= $cutoff) {
            $best = max(0, min(100, (int) ($row['p'] ?? 0)));
        }
    }
    return $best;
}

/**
 * Build dashboard retention strip model. Returns null when suppressed (FTUE, empty site, nothing to say).
 *
 * @param array<string,mixed> $ctx Keys: user_id, ftue_show_hero, ftue_pre_scan, has_scan_history, is_out_of_credits,
 *                                  is_pro, missing, weak, optimized, total, coverage_pct, credits_used,
 *                                  missing_library_url, needs_review_library_url, dashboard_url.
 *
 * @return array<string,mixed>|null
 */
function bbai_retention_build_strip_model(array $ctx): ?array {
    $uid = (int) ($ctx['user_id'] ?? 0);
    $ftue_hero = !empty($ctx['ftue_show_hero']);
    $ftue_pre = !empty($ctx['ftue_pre_scan']);
    $has_scan = !empty($ctx['has_scan_history']);
    $missing = max(0, (int) ($ctx['missing'] ?? 0));
    $weak = max(0, (int) ($ctx['weak'] ?? 0));
    $optimized = max(0, (int) ($ctx['optimized'] ?? 0));
    $total = max(0, (int) ($ctx['total'] ?? 0));
    $coverage_pct = max(0, min(100, (int) ($ctx['coverage_pct'] ?? 0)));
    $is_out = !empty($ctx['is_out_of_credits']);
    $is_pro = !empty($ctx['is_pro']);
    $credits_used = max(0, (int) ($ctx['credits_used'] ?? 0));

    $missing_lib = (string) ($ctx['missing_library_url'] ?? '');
    $review_lib = (string) ($ctx['needs_review_library_url'] ?? '');

    if ($ftue_hero || $ftue_pre) {
        return null;
    }
    if (!$has_scan && $total <= 0) {
        return null;
    }

    $issues = $missing + $weak;
    $new_uploads = bbai_retention_new_uploads_estimate($uid, $total);

    $hist = bbai_retention_load_history($uid);
    $hist = bbai_retention_merge_today_snapshot($hist, $coverage_pct, $missing, $weak, $total);
    bbai_retention_save_history($uid, $hist);

    $baseline = bbai_retention_baseline_percent($hist, 7);
    $delta_line = '';
    if (null !== $baseline && $total > 0) {
        $delta = $coverage_pct - $baseline;
        if ($delta > 0) {
            $delta_line = sprintf(
                /* translators: %d: percentage point change */
                __('Up %d pts on coverage vs last week', 'beepbeep-ai-alt-text-generator'),
                $delta
            );
        } elseif ($delta < 0) {
            $delta_line = sprintf(
                /* translators: %d: percentage point change (absolute) */
                __('Coverage slipped %d pts vs last week — worth a quick refresh', 'beepbeep-ai-alt-text-generator'),
                abs($delta)
            );
        }
    }

    $days_inactive = 0;
    if (class_exists(\BeepBeepAI\AltTextGenerator\BBAI_Telemetry::class)) {
        $days_inactive = (int) \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::inactive_days_at_session_start();
    }

    $processed_line = $total > 0
        ? sprintf(
            /* translators: 1: optimised count, 2: total images */
            __('%1$s / %2$s images optimised', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($optimized),
            number_format_i18n($total)
        )
        : '';

    $remaining_pct = $total > 0 ? max(0, 100 - $coverage_pct) : 0;
    $near_complete = $coverage_pct >= 85 && $issues > 0 && $issues <= max(3, (int) ceil(0.1 * $total));
    $near_ninety = $coverage_pct >= 90 && $issues > 0;

    $trigger = '';
    $headline = '';
    $supporting = '';

    $primary = [
        'label'       => '',
        'href'        => '#',
        'action'      => '',
        'bbai_action' => '',
        'aria_label'  => '',
    ];
    $secondary = [
        'label'       => '',
        'href'        => '#',
        'action'      => '',
        'bbai_action' => '',
        'aria_label'  => '',
    ];

    if ($near_ninety) {
        $trigger = 'near_complete_90';
        $headline = sprintf(
            /* translators: 1: coverage %, 2: remaining % */
            __('You\'re %1$d%% optimised — finish the last %2$d%%', 'beepbeep-ai-alt-text-generator'),
            $coverage_pct,
            $remaining_pct
        );
        $supporting = $issues === 1
            ? __('One image still needs attention.', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: %d: issue count */
                __('%s images still need attention.', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($issues)
            );
        if ($missing > 0) {
            $primary = bbai_retention_action_generate_missing($is_out);
            $secondary = bbai_retention_action_link(
                __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                $missing_lib,
                __('Open ALT Library', 'beepbeep-ai-alt-text-generator')
            );
        } else {
            $primary = bbai_retention_action_link(
                __('Review now', 'beepbeep-ai-alt-text-generator'),
                $review_lib,
                __('Review descriptions', 'beepbeep-ai-alt-text-generator')
            );
            $secondary = bbai_retention_action_scan();
        }
    } elseif ($near_complete) {
        $trigger = 'near_complete';
        $headline = sprintf(
            /* translators: %d: approximate percent of library left to fix */
            __('Almost there — polish the last ~%d%%', 'beepbeep-ai-alt-text-generator'),
            $remaining_pct
        );
        $supporting = __('Close the gap to protect rankings and accessibility.', 'beepbeep-ai-alt-text-generator');
        if ($missing > 0) {
            $primary = bbai_retention_action_generate_missing($is_out);
            $secondary = bbai_retention_action_link(
                __('Review queue', 'beepbeep-ai-alt-text-generator'),
                $review_lib,
                __('Open needs review', 'beepbeep-ai-alt-text-generator')
            );
        } else {
            $primary = bbai_retention_action_link(
                __('Review now', 'beepbeep-ai-alt-text-generator'),
                $review_lib,
                __('Review descriptions', 'beepbeep-ai-alt-text-generator')
            );
            $secondary = bbai_retention_action_scan();
        }
    } elseif ($days_inactive >= 3 && $issues > 0) {
        $trigger = 'inactivity_return';
        $headline = __('Welcome back — your library moved on without you', 'beepbeep-ai-alt-text-generator');
        $supporting = __('You have unfinished optimisations. Pick up where you left off.', 'beepbeep-ai-alt-text-generator');
        if ($missing > 0) {
            $primary = bbai_retention_action_generate_missing($is_out);
            $secondary = bbai_retention_action_link(
                __('Review issues', 'beepbeep-ai-alt-text-generator'),
                $missing_lib,
                __('View missing ALT', 'beepbeep-ai-alt-text-generator')
            );
        } else {
            $primary = bbai_retention_action_link(
                __('Review now', 'beepbeep-ai-alt-text-generator'),
                $review_lib,
                __('Review descriptions', 'beepbeep-ai-alt-text-generator')
            );
            $secondary = bbai_retention_action_scan();
        }
    } elseif ($new_uploads > 0 && $missing > 0) {
        $trigger = 'new_media_missing';
        $n = max(1, min($new_uploads, $missing));
        $headline = sprintf(
            /* translators: %d: count */
            _n('%d new image needs ALT text', '%d new images need ALT text', $n, 'beepbeep-ai-alt-text-generator'),
            $n
        );
        $supporting = __('Fresh uploads are easy to miss — generate before they hurt SEO.', 'beepbeep-ai-alt-text-generator');
        $primary = bbai_retention_action_generate_missing($is_out);
        $secondary = bbai_retention_action_link(
            __('See in library', 'beepbeep-ai-alt-text-generator'),
            $missing_lib,
            __('Open missing filter', 'beepbeep-ai-alt-text-generator')
        );
    } elseif ($new_uploads > 0 && $weak > 0 && 0 === $missing) {
        $trigger = 'new_media_review';
        $headline = sprintf(
            /* translators: %d: count */
            _n('%d new image needs review', '%d new images need review', $weak, 'beepbeep-ai-alt-text-generator'),
            $weak
        );
        $supporting = __('New media can still ship with weak descriptions.', 'beepbeep-ai-alt-text-generator');
        $primary = bbai_retention_action_link(
            __('Review now', 'beepbeep-ai-alt-text-generator'),
            $review_lib,
            __('Review descriptions', 'beepbeep-ai-alt-text-generator')
        );
        $secondary = bbai_retention_action_scan();
    } elseif ($missing > 0) {
        $trigger = 'missing_alt';
        $headline = sprintf(
            /* translators: %d: missing count */
            _n('%d image is missing ALT text', '%d images are missing ALT text', $missing, 'beepbeep-ai-alt-text-generator'),
            $missing
        );
        $supporting = __('Generate descriptions in one pass, then spot-check in the library.', 'beepbeep-ai-alt-text-generator');
        $primary = bbai_retention_action_generate_missing($is_out);
        $secondary = bbai_retention_action_link(
            __('Open library', 'beepbeep-ai-alt-text-generator'),
            $missing_lib,
            __('Open missing filter', 'beepbeep-ai-alt-text-generator')
        );
    } elseif ($weak > 0) {
        $trigger = 'needs_review';
        $headline = sprintf(
            /* translators: %d: count */
            _n('%d image needs review', '%d images need review', $weak, 'beepbeep-ai-alt-text-generator'),
            $weak
        );
        $supporting = __('Descriptions that need review still count as risk for quality and compliance.', 'beepbeep-ai-alt-text-generator');
        $primary = bbai_retention_action_link(
            __('Review now', 'beepbeep-ai-alt-text-generator'),
            $review_lib,
            __('Review descriptions', 'beepbeep-ai-alt-text-generator')
        );
        $secondary = bbai_retention_action_scan();
    } elseif ($new_uploads > 0) {
        $trigger = 'new_library_only';
        $headline = sprintf(
            /* translators: %d: new image estimate */
            _n('%d new image in your library', '%d new images in your library', $new_uploads, 'beepbeep-ai-alt-text-generator'),
            $new_uploads
        );
        $supporting = __('Run a quick scan so coverage stats stay accurate.', 'beepbeep-ai-alt-text-generator');
        $primary = bbai_retention_action_scan();
        $secondary = bbai_retention_action_link(
            __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
            (string) ($ctx['library_url'] ?? $missing_lib),
            __('Open ALT Library', 'beepbeep-ai-alt-text-generator')
        );
    } elseif (!$is_pro && $credits_used >= 15 && $total > 5) {
        $trigger = 'upgrade_momentum';
        $headline = sprintf(
            /* translators: %d: images optimised this cycle (credits used as proxy) */
            __('You\'ve optimised %d images this month', 'beepbeep-ai-alt-text-generator'),
            $credits_used
        );
        $supporting = __('Unlock unlimited optimisation and automation on Growth.', 'beepbeep-ai-alt-text-generator');
        $primary = [
            'label'       => __('See Growth', 'beepbeep-ai-alt-text-generator'),
            'href'        => '#',
            'action'      => 'show-upgrade-modal',
            'bbai_action' => '',
            'aria_label'  => __('See Growth plans', 'beepbeep-ai-alt-text-generator'),
        ];
        $secondary = bbai_retention_action_scan();
    } else {
        return null;
    }

    $upgrade_hint = '';
    if (!$is_pro && $trigger !== 'upgrade_momentum' && $issues > 0 && $credits_used >= 8) {
        $upgrade_hint = __('Remove monthly limits with Growth — keep every new upload optimised.', 'beepbeep-ai-alt-text-generator');
    }

    $model = [
        'show'           => true,
        'trigger'        => $trigger,
        'headline'       => $headline,
        'supporting'     => $supporting,
        'progress_pct'   => $coverage_pct,
        'processed_line' => $processed_line,
        'delta_line'     => $delta_line,
        'primary'        => $primary,
        'secondary'      => $secondary,
        'upgrade_hint'   => $upgrade_hint,
        'telemetry'      => [
            'trigger'        => $trigger,
            'missing'        => $missing,
            'needs_review'   => $weak,
            'new_estimate'   => $new_uploads,
            'coverage_pct'   => $coverage_pct,
            'days_inactive'  => $days_inactive,
            'issues'         => $issues,
        ],
    ];

    /**
     * Optional email / CRM hooks (no core mail). Implementers can queue digests when triggers fire.
     *
     * @param array<string,mixed> $model
     */
    do_action('bbai_retention_triggers_evaluated', $model);

    if ($days_inactive >= 7 && $issues > 0) {
        do_action('bbai_retention_maybe_email_digest', $model);
    }

    return $model;
}

/**
 * Compact library nudge (subset of triggers). Null = omit row.
 *
 * @param array<string,mixed> $ctx Keys: user_id, missing, weak, total, coverage_pct, missing_library_url,
 *                                  needs_review_library_url, dashboard_url, is_out_of_credits.
 *
 * @return array<string,mixed>|null
 */
function bbai_retention_build_library_nudge(array $ctx): ?array {
    $uid = (int) ($ctx['user_id'] ?? 0);
    $missing = max(0, (int) ($ctx['missing'] ?? 0));
    $weak = max(0, (int) ($ctx['weak'] ?? 0));
    $total = max(0, (int) ($ctx['total'] ?? 0));
    $coverage_pct = max(0, min(100, (int) ($ctx['coverage_pct'] ?? 0)));
    $is_out = !empty($ctx['is_out_of_credits']);
    $issues = $missing + $weak;
    $new_uploads = bbai_retention_new_uploads_estimate($uid, $total);

    $days_inactive = 0;
    if (class_exists(\BeepBeepAI\AltTextGenerator\BBAI_Telemetry::class)) {
        $days_inactive = (int) \BeepBeepAI\AltTextGenerator\BBAI_Telemetry::inactive_days_at_session_start();
    }

    if ($issues <= 0 && $new_uploads <= 0 && $days_inactive < 5) {
        return null;
    }

    $missing_lib = (string) ($ctx['missing_library_url'] ?? '');
    $review_lib = (string) ($ctx['needs_review_library_url'] ?? '');

    $line = '';
    $primary = bbai_retention_action_scan();

    if ($missing > 0) {
        $line = sprintf(
            /* translators: %d: count */
            _n('%d image needs ALT text', '%d images need ALT text', $missing, 'beepbeep-ai-alt-text-generator'),
            $missing
        );
        $primary = bbai_retention_action_generate_missing($is_out);
    } elseif ($weak > 0) {
        $line = sprintf(
            /* translators: %d: count */
            _n('%d image needs review', '%d images need review', $weak, 'beepbeep-ai-alt-text-generator'),
            $weak
        );
        $primary = bbai_retention_action_link(
            __('Review now', 'beepbeep-ai-alt-text-generator'),
            $review_lib,
            __('Review descriptions', 'beepbeep-ai-alt-text-generator')
        );
    } elseif ($new_uploads > 0) {
        $line = sprintf(
            /* translators: %d: count */
            _n('%d new image since your last visit — refresh coverage', '%d new images since your last visit — refresh coverage', $new_uploads, 'beepbeep-ai-alt-text-generator'),
            $new_uploads
        );
        $primary = bbai_retention_action_scan();
    } elseif ($days_inactive >= 5 && $coverage_pct < 100) {
        $line = __('You have unfinished optimisations in this library.', 'beepbeep-ai-alt-text-generator');
    } else {
        return null;
    }

    return [
        'line'        => $line,
        'meta'        => sprintf(
            /* translators: %d: coverage percent */
            __('%d%% optimised', 'beepbeep-ai-alt-text-generator'),
            $coverage_pct
        ),
        'primary'     => $primary,
        'telemetry'   => [
            'surface'      => 'alt_library',
            'missing'      => $missing,
            'needs_review' => $weak,
            'new_estimate' => $new_uploads,
        ],
    ];
}

/**
 * @return array{label:string,href:string,action:string,bbai_action:string,aria_label:string}
 */
function bbai_retention_action_generate_missing(bool $is_out): array {
    if ($is_out) {
        return [
            'label'       => bbai_copy_cta_upgrade_growth(),
            'href'        => '#',
            'action'      => 'show-upgrade-modal',
            'bbai_action' => '',
            'aria_label'  => bbai_copy_cta_upgrade_growth(),
        ];
    }
    return [
        'label'       => bbai_copy_cta_generate_missing_images(),
        'href'        => '#',
        'action'      => 'generate-missing',
        'bbai_action' => '',
        'aria_label'  => bbai_copy_cta_generate_missing_images(),
    ];
}

/**
 * @return array{label:string,href:string,action:string,bbai_action:string,aria_label:string}
 */
function bbai_retention_action_link(string $label, string $url, string $aria): array {
    return [
        'label'       => $label,
        'href'        => esc_url($url),
        'action'      => '',
        'bbai_action' => '',
        'aria_label'  => $aria,
    ];
}

/**
 * @return array{label:string,href:string,action:string,bbai_action:string,aria_label:string}
 */
function bbai_retention_action_scan(): array {
    return [
        'label'       => bbai_copy_cta_scan_media_library(),
        'href'        => '#',
        'action'      => '',
        'bbai_action' => 'scan-opportunity',
        'aria_label'  => bbai_copy_cta_scan_media_library(),
    ];
}
