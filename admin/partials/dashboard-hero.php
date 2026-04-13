<?php
/**
 * Dashboard hero card with ALT status and credits.
 *
 * Expected parent scope from dashboard-body.php:
 * - $bbai_funnel_state, $bbai_state_missing_count, $bbai_state_weak_count,
 *   $bbai_state_optimized_count, $bbai_state_total_images, $bbai_state_coverage_percent,
 *   $bbai_state_credits_used, $bbai_state_credits_limit, $bbai_state_credits_remaining,
 *   $bbai_state_usage_percent, $bbai_library_url, $bbai_missing_library_url,
 *   $bbai_needs_review_library_url, $bbai_product_state_model, $bbai_is_anonymous_trial,
 *   $bbai_build_donut_background, $bbai_show_growth_plan_line, $bbai_growth_plan_reference_limit,
 *   $bbai_state_is_pro_plan, $bbai_has_connected_account
 *
 * @package BeepBeep_AI
 */

use BeepBeepAI\AltTextGenerator\Services\Dashboard_State;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_state = isset( $bbai_state ) && is_array( $bbai_state ) ? $bbai_state : [];
$bbai_state_missing_count = isset( $bbai_state_missing_count ) ? max( 0, (int) $bbai_state_missing_count ) : 0;
$bbai_state_weak_count = isset( $bbai_state_weak_count ) ? max( 0, (int) $bbai_state_weak_count ) : 0;
$bbai_state_optimized_count = isset( $bbai_state_optimized_count ) ? max( 0, (int) $bbai_state_optimized_count ) : 0;
$bbai_state_total_images = isset( $bbai_state_total_images ) ? max( 0, (int) $bbai_state_total_images ) : 0;
$bbai_state_coverage_percent = isset( $bbai_state_coverage_percent ) ? max( 0, (int) $bbai_state_coverage_percent ) : 0;
$bbai_state_credits_used = isset( $bbai_state_credits_used )
    ? max( 0, (int) $bbai_state_credits_used )
    : max( 0, (int) ( $bbai_state['credits_used'] ?? 0 ) );
$bbai_state_credits_limit = isset( $bbai_state_credits_limit )
    ? max( 1, (int) $bbai_state_credits_limit )
    : max( 1, (int) ( $bbai_state['credits_limit'] ?? 1 ) );
$bbai_state_credits_remaining = isset( $bbai_state_credits_remaining )
    ? (int) $bbai_state_credits_remaining
    : ( isset( $bbai_state['credits_remaining'] ) ? (int) $bbai_state['credits_remaining'] : 0 );
$bbai_state_usage_percent = isset( $bbai_state_usage_percent )
    ? max( 0, min( 100, (int) $bbai_state_usage_percent ) )
    : max( 0, min( 100, (int) round( ( $bbai_state_credits_used / max( 1, $bbai_state_credits_limit ) ) * 100 ) ) );

$bbai_hero_funnel      = isset( $bbai_funnel_state ) ? (string) $bbai_funnel_state : Dashboard_State::FUNNEL_NOT_SCANNED;
$bbai_locked_cta_mode  = isset( $bbai_product_state_model['cta']['locked_mode'] ) ? sanitize_key( (string) $bbai_product_state_model['cta']['locked_mode'] ) : '';
$bbai_has_scan_payload = ( $bbai_state_total_images > 0 ) || ( $bbai_state_missing_count > 0 ) || ( $bbai_state_weak_count > 0 ) || ( $bbai_state_optimized_count > 0 );
$bbai_is_guest_trial_user = ! empty( $bbai_is_anonymous_trial ) && empty( $bbai_has_connected_account );
$bbai_is_exhausted_trial_checkpoint = $bbai_is_guest_trial_user && $bbai_state_credits_remaining <= 0;
$bbai_actionable_state_model = isset( $bbai_product_state_model['actionable'] ) && is_array( $bbai_product_state_model['actionable'] )
    ? $bbai_product_state_model['actionable']
    : Dashboard_State::resolve_actionable_state(
        $bbai_state_missing_count,
        $bbai_state_weak_count,
        $bbai_state_total_images
    );

$bbai_build_action = static function ( string $label = '', array $config = [] ): array {
    return array_merge(
        [
            'label'       => $label,
            'href'        => '#',
            'action'      => '',
            'bbai_action' => '',
            'auth_tab'    => '',
            'analytics'      => '',
            'modal_context'  => '',
            'disabled'       => false,
            'aria_busy'      => false,
            'fix_dashboard'  => false,
            'review_dashboard' => false,
            'review_segment' => '',
            'hidden'      => '' === $label,
        ],
        $config
    );
};

$bbai_render_action = static function ( array $action, string $class_name, string $data_attribute ): void {
    $bbai_action_label = isset( $action['label'] ) ? (string) $action['label'] : '';
    ?>
    <a
        href="<?php echo esc_url( (string) ( $action['href'] ?? '#' ) ); ?>"
        class="<?php echo esc_attr( $class_name ); ?>"
        <?php echo esc_attr( $data_attribute ); ?>
        <?php echo ! empty( $action['hidden'] ) ? 'hidden' : ''; ?>
        <?php if ( '' !== (string) ( $action['action'] ?? '' ) ) : ?>
            data-action="<?php echo esc_attr( (string) $action['action'] ); ?>"
        <?php endif; ?>
        <?php if ( '' !== (string) ( $action['bbai_action'] ?? '' ) ) : ?>
            data-bbai-action="<?php echo esc_attr( (string) $action['bbai_action'] ); ?>"
        <?php endif; ?>
        <?php if ( '' !== (string) ( $action['auth_tab'] ?? '' ) ) : ?>
            data-auth-tab="<?php echo esc_attr( (string) $action['auth_tab'] ); ?>"
        <?php endif; ?>
        <?php if ( '' !== (string) ( $action['analytics'] ?? '' ) ) : ?>
            data-bbai-analytics-upgrade="<?php echo esc_attr( (string) $action['analytics'] ); ?>"
        <?php endif; ?>
        <?php if ( '' !== (string) ( $action['modal_context'] ?? '' ) ) : ?>
            data-bbai-modal-context="<?php echo esc_attr( (string) $action['modal_context'] ); ?>"
        <?php endif; ?>
        <?php if ( ! empty( $action['fix_dashboard'] ) ) : ?>
            data-bbai-fix-dashboard="1"
        <?php endif; ?>
        <?php if ( ! empty( $action['review_dashboard'] ) ) : ?>
            data-bbai-review-dashboard="1"
        <?php endif; ?>
        <?php if ( '' !== (string) ( $action['review_segment'] ?? '' ) ) : ?>
            data-bbai-review-segment="<?php echo esc_attr( (string) $action['review_segment'] ); ?>"
        <?php endif; ?>
        <?php if ( ! empty( $action['disabled'] ) ) : ?>
            disabled
        <?php endif; ?>
        <?php if ( ! empty( $action['aria_busy'] ) ) : ?>
            aria-busy="true"
        <?php endif; ?>
    ><?php echo esc_html( $bbai_action_label ); ?></a>
    <?php
};

$bbai_format_fix_title = static function ( int $count ): string {
    if ( $count <= 0 ) {
        return __( 'Fix images in seconds', 'beepbeep-ai-alt-text-generator' );
    }

    return sprintf(
        /* translators: %s: number of images to fix. */
        _n( 'Fix %s image in seconds', 'Fix %s images in seconds', $count, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $count )
    );
};
$bbai_format_review_title = static function ( int $count ): string {
    $count = max( 0, $count );

    if ( $count <= 0 ) {
        return __( 'Images ready to review', 'beepbeep-ai-alt-text-generator' );
    }

    return sprintf(
        /* translators: %s: number of images ready to review. */
        _n( '%s image ready to review', '%s images ready to review', $count, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $count )
    );
};
$bbai_actionable_state = isset( $bbai_actionable_state_model['state'] ) ? sanitize_key( (string) $bbai_actionable_state_model['state'] ) : Dashboard_State::ACTIONABLE_STATE_COMPLETE;
$bbai_actionable_count = isset( $bbai_actionable_state_model['actionable_count'] )
    ? max( 0, (int) $bbai_actionable_state_model['actionable_count'] )
    : max( 0, $bbai_state_missing_count + $bbai_state_weak_count );
$bbai_locked_trial_remaining_count = $bbai_state_missing_count > 0 ? $bbai_state_missing_count : $bbai_actionable_count;
$bbai_locked_trial_cta_label = sprintf(
    /* translators: %s: remaining image count. */
    __( 'Fix your %s remaining images', 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( max( 0, $bbai_locked_trial_remaining_count ) )
);
$bbai_locked_trial_cta_context = $bbai_locked_trial_remaining_count > 0
    ? sprintf(
        /* translators: %s: remaining image count. */
        _n( 'You’re %s image away from full optimisation', 'You’re %s images away from full optimisation', $bbai_locked_trial_remaining_count, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_locked_trial_remaining_count )
    )
    : __( 'Continue fixing your images and unlock full access', 'beepbeep-ai-alt-text-generator' );
$bbai_review_count = isset( $bbai_actionable_state_model['review_count'] )
    ? max( 0, (int) $bbai_actionable_state_model['review_count'] )
    : $bbai_state_weak_count;
$bbai_hero_credits_remaining_line = ! empty( $bbai_is_anonymous_trial )
    ? sprintf(
        /* translators: %s: remaining free generations. */
        _n( 'You have %s free generation remaining', 'You have %s free generations remaining', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_state_credits_remaining )
    )
    : sprintf(
        /* translators: %s: remaining generations. */
        _n( 'You have %s generation remaining', 'You have %s generations remaining', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_state_credits_remaining )
    );
$bbai_hero_credits_usage_line = sprintf(
    /* translators: 1: used credits, 2: total credits. */
    __( '%1$s / %2$s used', 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_state_credits_used ),
    number_format_i18n( $bbai_state_credits_limit )
);
$bbai_hero_credits_comparison_line = '';
$bbai_hero_credits_upgrade_url     = '';
$bbai_hero_credits_upgrade_label   = '';
$bbai_hero_credits_progress = max( 0, min( 100, (int) $bbai_state_usage_percent ) );
$bbai_hero_credits_state = 'default';
$bbai_credit_soft_limit = 2;
$bbai_is_low_credit_state = ! $bbai_state_is_pro_plan && $bbai_state_credits_remaining > 0 && $bbai_state_credits_remaining <= $bbai_credit_soft_limit;
$bbai_is_exhausted_credit_state = ! $bbai_state_is_pro_plan && $bbai_state_credits_remaining <= 0;

if ( $bbai_is_exhausted_credit_state ) {
    $bbai_hero_credits_state = 'exhausted';
} elseif ( $bbai_is_low_credit_state ) {
    $bbai_hero_credits_state = 'low';
}

$bbai_build_unlock_action = static function () use (
    $bbai_build_action,
    $bbai_is_guest_trial_user,
    $bbai_locked_trial_cta_label
): array {
    if ( $bbai_is_guest_trial_user ) {
        return $bbai_build_action(
            $bbai_locked_trial_cta_label,
            [
                'action'        => 'show-dashboard-auth',
                'auth_tab'      => 'register',
                'analytics'     => 'hero_continue_optimizing_signup',
                'modal_context' => 'fix',
                'hidden'        => false,
            ]
        );
    }

    return $bbai_build_action(
        __( 'Unlock full ALT optimisation', 'beepbeep-ai-alt-text-generator' ),
        [
            'action' => 'show-upgrade-modal',
            'hidden' => false,
        ]
    );
};
$bbai_build_login_action = static function () use ( $bbai_build_action ): array {
    return $bbai_build_action(
        __( 'Already have an account? Sign in', 'beepbeep-ai-alt-text-generator' ),
        [
            'action'        => 'show-dashboard-auth',
            'auth_tab'      => 'login',
            'analytics'     => 'hero_exhausted_trial_login',
            'modal_context' => 'login',
            'hidden'        => false,
        ]
    );
};

if ( $bbai_is_guest_trial_user ) {
    if ( $bbai_is_exhausted_credit_state ) {
        $bbai_hero_credits_remaining_line = sprintf(
            /* translators: %s: total free trial generations. */
            __( 'You’ve used all %s free generations', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_limit )
        );
        $bbai_hero_credits_comparison_line = sprintf(
            /* translators: %s: free monthly account allowance. */
            __( 'Create a free account to unlock %s generations per month', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( 50 )
        );
    } elseif ( $bbai_is_low_credit_state ) {
        $bbai_hero_credits_remaining_line = sprintf(
            /* translators: %s: remaining free generations. */
            _n( 'You’re running low — %s free generation left', 'You’re running low — %s free generations left', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_remaining )
        );
    }
} elseif ( ! $bbai_state_is_pro_plan ) {
    if ( $bbai_is_exhausted_credit_state ) {
        $bbai_hero_credits_remaining_line = sprintf(
            /* translators: %s: monthly credit limit. */
            __( 'You’ve used all %s generations this cycle', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_limit )
        );
        $bbai_hero_credits_comparison_line = __( 'Unlock full ALT optimisation to keep improving your SEO.', 'beepbeep-ai-alt-text-generator' );
    } elseif ( $bbai_is_low_credit_state ) {
        $bbai_hero_credits_remaining_line = sprintf(
            /* translators: %s: remaining monthly generations. */
            _n( 'You’re running low — %s generation left', 'You’re running low — %s generations left', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_remaining )
        );
    }
}

$bbai_hero_credits_upgrade_url = '';
$bbai_hero_credits_upgrade_label = '';

$bbai_conversion_prompt_visible = false;
$bbai_conversion_prompt_tone = 'soft';
$bbai_conversion_prompt_title = '';
$bbai_conversion_prompt_copy = '';
$bbai_conversion_prompt_note = '';
$bbai_conversion_prompt_action = $bbai_build_action();

if ( $bbai_is_exhausted_credit_state ) {
    $bbai_conversion_prompt_visible = true;
    $bbai_conversion_prompt_tone = 'exhausted';
    if ( $bbai_is_guest_trial_user ) {
        $bbai_conversion_prompt_title = sprintf(
            /* translators: %s: total free trial generations. */
            __( 'You’ve used all %s free generations', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_limit )
        );
	        $bbai_conversion_prompt_copy = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
        $bbai_conversion_prompt_note = __( 'Free account required to unlock full library access', 'beepbeep-ai-alt-text-generator' );
    } elseif ( ! $bbai_state_is_pro_plan ) {
        $bbai_conversion_prompt_title = sprintf(
            /* translators: %s: monthly generation limit. */
            __( 'You’ve used all %s generations this cycle', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_state_credits_limit )
        );
        $bbai_conversion_prompt_copy = __( 'Unlock full ALT optimisation to continue improving your SEO.', 'beepbeep-ai-alt-text-generator' );
        $bbai_conversion_prompt_note = __( 'Higher monthly usage and bulk optimisation unlock on paid plans.', 'beepbeep-ai-alt-text-generator' );
    }
} elseif ( $bbai_is_low_credit_state ) {
    $bbai_conversion_prompt_visible = true;
    $bbai_conversion_prompt_tone = 'low';
    if ( $bbai_is_guest_trial_user ) {
        $bbai_conversion_prompt_title = __( 'You’re almost out of free generations', 'beepbeep-ai-alt-text-generator' );
	        $bbai_conversion_prompt_copy = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
        $bbai_conversion_prompt_note = __( 'Unlock 50 generations per month', 'beepbeep-ai-alt-text-generator' );
    } elseif ( ! $bbai_state_is_pro_plan ) {
        $bbai_conversion_prompt_title = __( 'You’re running low on generations', 'beepbeep-ai-alt-text-generator' );
        $bbai_conversion_prompt_copy = __( 'Unlock full ALT optimisation to keep improving your SEO.', 'beepbeep-ai-alt-text-generator' );
        $bbai_conversion_prompt_note = __( 'More monthly usage and bulk optimisation unlock next.', 'beepbeep-ai-alt-text-generator' );
    }
    $bbai_conversion_prompt_action = $bbai_build_unlock_action();
}

$bbai_busy_title          = sprintf(
    /* translators: %s: number of images currently being processed. */
    _n( 'Fixing %s image...', 'Fixing %s images...', max( 1, $bbai_actionable_count ), 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( max( 1, $bbai_actionable_count ) )
);
$bbai_build_fix_action = static function () use (
    $bbai_build_action,
    $bbai_is_guest_trial_user,
    $bbai_state_credits_remaining,
    $bbai_hero_funnel,
    $bbai_locked_cta_mode,
    $bbai_actionable_count,
    $bbai_locked_trial_cta_label
): array {
    if ( $bbai_is_guest_trial_user && (int) $bbai_state_credits_remaining <= 0 ) {
        return $bbai_build_action(
            $bbai_locked_trial_cta_label,
            [
                'action'        => 'show-dashboard-auth',
                'auth_tab'      => 'register',
                'analytics'     => 'hero_fix_all_images_signup',
                'modal_context' => 'fix',
                'hidden'        => false,
            ]
        );
    }

    if ( $bbai_actionable_count > 0 && Dashboard_State::FUNNEL_LIMIT_REACHED === $bbai_hero_funnel && '' !== $bbai_locked_cta_mode ) {
        return $bbai_build_action(
            __( 'Unlock full ALT optimisation', 'beepbeep-ai-alt-text-generator' ),
            [
                'action' => 'show-upgrade-modal',
                'hidden' => false,
            ]
        );
    }

    return $bbai_build_action(
        __( 'Fix all images', 'beepbeep-ai-alt-text-generator' ),
        [
            'action'        => 'generate-missing',
            'bbai_action'   => 'generate_missing',
            'fix_dashboard' => true,
            'hidden'        => false,
        ]
    );
};
$bbai_build_review_action = static function () use (
    $bbai_build_action,
    $bbai_has_connected_account,
    $bbai_needs_review_library_url,
    $bbai_state_credits_remaining,
    $bbai_is_guest_trial_user,
    $bbai_locked_trial_cta_label
): array {
    if ( $bbai_is_guest_trial_user && $bbai_state_credits_remaining <= 0 ) {
        return $bbai_build_action(
            $bbai_locked_trial_cta_label,
            [
                'action'        => 'show-dashboard-auth',
                'auth_tab'      => 'register',
                'analytics'     => 'hero_review_images_signup',
                'modal_context' => 'fix',
                'hidden'        => false,
            ]
        );
    }

    if ( empty( $bbai_has_connected_account ) ) {
        return $bbai_build_action(
            __( 'Review images', 'beepbeep-ai-alt-text-generator' ),
            [
                'action'           => 'review-dashboard-results',
                'review_dashboard' => true,
                'review_segment'   => 'weak',
                'hidden'    => false,
            ]
        );
    }

    return $bbai_build_action(
        __( 'Review images', 'beepbeep-ai-alt-text-generator' ),
        [
            'href'              => $bbai_needs_review_library_url,
            'action'            => 'review-dashboard-results',
            'review_dashboard'  => true,
            'review_segment'    => 'weak',
            'hidden'            => false,
        ]
    );
};
$bbai_build_complete_action = static function () use (
    $bbai_build_action,
    $bbai_has_connected_account,
    $bbai_library_url
): array {
    if ( ! empty( $bbai_has_connected_account ) ) {
        return $bbai_build_action(
            __( 'View results', 'beepbeep-ai-alt-text-generator' ),
            [
                'href'   => $bbai_library_url,
                'hidden' => false,
            ]
        );
    }

    return $bbai_build_action(
        __( 'View results', 'beepbeep-ai-alt-text-generator' ),
        [
            'action'           => 'review-dashboard-results',
            'review_dashboard' => true,
            'review_segment'   => 'optimized',
            'hidden'           => false,
        ]
    );
};

$bbai_hero_state = 'not_scanned';
if ( Dashboard_State::FUNNEL_SCANNING === $bbai_hero_funnel ) {
    $bbai_hero_state = 'scanning';
} elseif ( $bbai_has_scan_payload ) {
    $bbai_hero_state = ( $bbai_state_missing_count > 0 || $bbai_state_weak_count > 0 || Dashboard_State::FUNNEL_FIXING === $bbai_hero_funnel || Dashboard_State::FUNNEL_LIMIT_REACHED === $bbai_hero_funnel )
        ? 'scanned_has_issues'
        : 'scanned_clean';
}

$bbai_donut_background = 'conic-gradient(#d7dee8 0deg 360deg)';
if ( 'scanned_has_issues' === $bbai_hero_state || 'scanned_clean' === $bbai_hero_state ) {
    if ( 'scanned_clean' === $bbai_hero_state ) {
        $bbai_donut_background = 'conic-gradient(#22c55e 0deg 360deg)';
    } elseif ( is_callable( $bbai_build_donut_background ) ) {
        $bbai_donut_background = $bbai_build_donut_background(
            $bbai_state_optimized_count,
            $bbai_state_weak_count,
            $bbai_state_missing_count,
            max( 1, $bbai_state_total_images )
        );
    }
}

$bbai_fix_description    = __( 'Automatically generate SEO-friendly ALT text', 'beepbeep-ai-alt-text-generator' );
$bbai_review_description = __( 'Quickly review generated ALT text before publishing', 'beepbeep-ai-alt-text-generator' );
$bbai_donut_value        = '0%';
$bbai_donut_center_label = __( 'OPTIMIZED', 'beepbeep-ai-alt-text-generator' );
$bbai_donut_tone         = 'neutral';
$bbai_title              = __( 'Scan your images in seconds', 'beepbeep-ai-alt-text-generator' );
$bbai_description        = $bbai_fix_description;
$bbai_status_label       = __( 'Alt text progress', 'beepbeep-ai-alt-text-generator' );
$bbai_status_detail      = '';
$bbai_support_line       = '';
$bbai_cta_context        = '';
$bbai_secondary_action   = $bbai_build_action();
$bbai_primary_action     = $bbai_build_action(
    __( 'Scan images', 'beepbeep-ai-alt-text-generator' ),
    [
        'bbai_action' => 'scan-opportunity',
        'hidden'      => false,
    ]
);

if ( 'scanning' === $bbai_hero_state ) {
    $bbai_donut_value        = '0';
    $bbai_donut_center_label = __( 'TO FIX', 'beepbeep-ai-alt-text-generator' );
    $bbai_donut_tone         = 'scanning';
    $bbai_title              = __( 'Scanning your images', 'beepbeep-ai-alt-text-generator' );
    $bbai_description        = $bbai_fix_description;
    $bbai_status_label       = __( 'Scanning library', 'beepbeep-ai-alt-text-generator' );
    $bbai_primary_action     = $bbai_build_action(
        __( 'Scanning images...', 'beepbeep-ai-alt-text-generator' ),
        [
            'disabled'  => true,
            'aria_busy' => true,
            'hidden'    => false,
        ]
    );
} elseif ( 'scanned_has_issues' === $bbai_hero_state ) {
    $bbai_donut_value        = number_format_i18n(
        Dashboard_State::ACTIONABLE_STATE_REVIEW === $bbai_actionable_state
            ? max( 1, $bbai_review_count )
            : max( 1, $bbai_actionable_count )
    );
    $bbai_donut_center_label = Dashboard_State::ACTIONABLE_STATE_REVIEW === $bbai_actionable_state
        ? __( 'TO REVIEW', 'beepbeep-ai-alt-text-generator' )
        : __( 'TO FIX', 'beepbeep-ai-alt-text-generator' );
    $bbai_donut_tone         = Dashboard_State::FUNNEL_FIXING === $bbai_hero_funnel ? 'scanning' : 'problem';
    $bbai_status_label       = Dashboard_State::ACTIONABLE_STATE_REVIEW === $bbai_actionable_state
        ? sprintf(
            /* translators: %s: number of images to review. */
            _n( '%s image to review', '%s images to review', max( 1, $bbai_review_count ), 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( max( 1, $bbai_review_count ) )
        )
        : sprintf(
            /* translators: %s: number of images to fix. */
            _n( '%s image to fix', '%s images to fix', max( 1, $bbai_actionable_count ), 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( max( 1, $bbai_actionable_count ) )
        );

    if ( Dashboard_State::FUNNEL_FIXING === $bbai_hero_funnel ) {
        $bbai_title          = $bbai_busy_title;
        $bbai_description    = $bbai_fix_description;
        $bbai_primary_action = $bbai_build_action(
            __( 'Fixing images...', 'beepbeep-ai-alt-text-generator' ),
            [
                'disabled'  => true,
                'aria_busy' => true,
                'hidden'    => false,
            ]
        );
    } elseif ( Dashboard_State::ACTIONABLE_STATE_REVIEW === $bbai_actionable_state && $bbai_is_guest_trial_user && $bbai_state_credits_remaining <= 0 ) {
        $bbai_title          = $bbai_format_review_title( $bbai_review_count );
        $bbai_description    = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
        $bbai_primary_action = $bbai_build_review_action();
    } elseif ( Dashboard_State::ACTIONABLE_STATE_REVIEW === $bbai_actionable_state ) {
        $bbai_title          = $bbai_format_review_title( $bbai_review_count );
        $bbai_description    = $bbai_review_description;
        $bbai_primary_action = $bbai_build_review_action();
    } elseif ( 'create_account' === $bbai_locked_cta_mode || ( ! empty( $bbai_is_anonymous_trial ) && Dashboard_State::FUNNEL_LIMIT_REACHED === $bbai_hero_funnel ) ) {
        $bbai_title          = $bbai_format_fix_title( $bbai_actionable_count );
        $bbai_description    = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
        $bbai_primary_action = $bbai_build_fix_action();
    } elseif ( '' !== $bbai_locked_cta_mode && Dashboard_State::FUNNEL_LIMIT_REACHED === $bbai_hero_funnel ) {
        $bbai_title          = $bbai_format_fix_title( $bbai_actionable_count );
        $bbai_description    = __( 'Unlock full ALT optimisation to continue from the dashboard.', 'beepbeep-ai-alt-text-generator' );
        $bbai_primary_action = $bbai_build_fix_action();
    } else {
        $bbai_title          = $bbai_format_fix_title( $bbai_actionable_count );
        $bbai_description    = $bbai_fix_description;
        $bbai_primary_action = $bbai_build_fix_action();
    }
} elseif ( 'scanned_clean' === $bbai_hero_state ) {
    $bbai_donut_value        = '100%';
    $bbai_donut_center_label = __( 'OPTIMIZED', 'beepbeep-ai-alt-text-generator' );
    $bbai_donut_tone         = 'healthy';
    $bbai_title              = __( 'Your images are fully optimised', 'beepbeep-ai-alt-text-generator' );
    $bbai_description        = __( 'Your latest ALT text is ready to view and review.', 'beepbeep-ai-alt-text-generator' );
    $bbai_status_label       = __( '100% optimised', 'beepbeep-ai-alt-text-generator' );
    $bbai_primary_action     = $bbai_build_complete_action();
}

if ( $bbai_is_exhausted_trial_checkpoint && 'scanning' !== $bbai_hero_state ) {
    $bbai_donut_value        = number_format_i18n( max( 0, $bbai_state_missing_count ) );
    $bbai_donut_center_label = '';
    $bbai_donut_tone         = $bbai_state_missing_count > 0 ? 'problem' : 'neutral';
    $bbai_title              = sprintf(
        /* translators: %s: exhausted free generation limit. */
        __( 'You’ve used all %s free generations', 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_state_credits_limit )
    );
    $bbai_description        = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
    $bbai_primary_action     = $bbai_build_unlock_action();
    $bbai_secondary_action   = $bbai_build_login_action();
    $bbai_cta_context        = $bbai_locked_trial_cta_context;
    $bbai_status_label       = '';
    $bbai_support_line       = __( 'No credit card required', 'beepbeep-ai-alt-text-generator' );
}
?>
<section
    class="bbai-funnel-hero bbai-funnel-hero--hero-card<?php echo $bbai_is_exhausted_trial_checkpoint ? ' bbai-funnel-hero--trial-exhausted' : ''; ?>"
    data-bbai-funnel-hero
    data-bbai-funnel-hero-state="<?php echo esc_attr( $bbai_hero_state ); ?>"
    data-bbai-hero-ui-state="<?php echo esc_attr( $bbai_hero_state ); ?>"
    aria-labelledby="bbai-funnel-hero-title"
>
    <div class="bbai-dashboard-hero-action<?php echo $bbai_is_exhausted_trial_checkpoint ? ' bbai-dashboard-hero-action--exhausted-trial' : ''; ?>" data-bbai-dashboard-hero-action>
        <div class="bbai-dashboard-hero-action__main">
            <div class="bbai-dashboard-hero-action__status">
                <div class="bbai-dashboard-hero-action__donut-wrap">
                    <div
                        class="bbai-command-donut bbai-command-donut--funnel bbai-dashboard-hero-action__donut bbai-command-donut--<?php echo esc_attr( $bbai_donut_tone ); ?><?php echo 'not_scanned' === $bbai_hero_state ? ' bbai-dashboard-hero-action__donut--idle' : ''; ?>"
                        data-bbai-status-donut
                        data-bbai-donut-optimized="<?php echo esc_attr( $bbai_state_optimized_count ); ?>"
                        data-bbai-donut-weak="<?php echo esc_attr( $bbai_state_weak_count ); ?>"
                        data-bbai-donut-missing="<?php echo esc_attr( $bbai_state_missing_count ); ?>"
                        data-bbai-donut-total="<?php echo esc_attr( $bbai_state_total_images ); ?>"
                        aria-hidden="true"
                        style="background: <?php echo esc_attr( $bbai_donut_background ); ?>;"
                    >
                        <span class="bbai-command-donut__inner"></span>
                        <span class="bbai-command-donut__center" data-bbai-funnel-donut-center>
                            <span class="bbai-command-donut__center-value bbai-command-donut__center-value--<?php echo esc_attr( $bbai_donut_tone ); ?>" data-bbai-funnel-donut-value>
                                <?php echo esc_html( $bbai_donut_value ); ?>
                            </span>
                            <span class="bbai-command-donut__center-label" data-bbai-funnel-donut-label<?php echo '' !== $bbai_donut_center_label ? '' : ' hidden'; ?>>
                                <?php echo esc_html( $bbai_donut_center_label ); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bbai-dashboard-hero-action__content">
                <h1 id="bbai-funnel-hero-title" class="bbai-dashboard-hero-action__title" data-bbai-funnel-hero-title>
                    <?php echo esc_html( $bbai_title ); ?>
                </h1>

                <div class="bbai-dashboard-hero-action__content-flow">
                    <p class="bbai-dashboard-hero-action__description" data-bbai-funnel-hero-desc>
                        <?php echo esc_html( $bbai_description ); ?>
                    </p>

                    <p
                        class="bbai-dashboard-hero-action__progress-line bbai-dashboard-hero-action__cta-context"
                        data-bbai-funnel-hero-cta-context
                        <?php echo '' !== $bbai_cta_context ? '' : 'hidden'; ?>
                    >
                        <?php echo esc_html( $bbai_cta_context ); ?>
                    </p>

                    <div class="bbai-dashboard-hero-action__cta-group" data-bbai-funnel-hero-cta-group>
                        <div class="bbai-dashboard-hero-action__actions">
                            <?php
                            $bbai_render_action(
                                $bbai_primary_action,
                                'bbai-command-action bbai-command-action--primary bbai-btn bbai-btn-primary bbai-dashboard-hero-action__cta bbai-dashboard-hero-action__cta--primary',
                                'data-bbai-funnel-hero-cta data-bbai-funnel-hero-cta-primary data-bbai-funnel-hero-primary'
                            );
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="bbai-dashboard-hero-action__status-copy"
                data-bbai-hero-status-block
                <?php echo ( '' !== $bbai_status_label || '' !== $bbai_status_detail ) ? '' : 'hidden'; ?>
            >
                <p class="bbai-dashboard-hero-action__status-label" data-bbai-hero-status-label>
                    <?php echo esc_html( $bbai_status_label ); ?>
                </p>
                <p
                    class="bbai-dashboard-hero-action__status-detail"
                    data-bbai-hero-status-detail
                    <?php echo '' !== $bbai_status_detail ? '' : 'hidden'; ?>
                >
                    <?php echo esc_html( $bbai_status_detail ); ?>
                </p>
            </div>

            <div class="bbai-dashboard-hero-action__secondary">
                <div class="bbai-dashboard-hero-action__secondary-actions">
                    <?php
                    $bbai_render_action(
                        $bbai_secondary_action,
                        'bbai-command-action bbai-command-action--secondary bbai-btn bbai-btn-secondary bbai-dashboard-hero-action__cta bbai-dashboard-hero-action__cta--secondary',
                        'data-bbai-funnel-hero-cta data-bbai-funnel-hero-cta-secondary data-bbai-funnel-hero-secondary'
                    );
                    ?>
                </div>
                <p
                    class="bbai-dashboard-hero-action__support"
                    data-bbai-funnel-hero-support
                    <?php echo '' !== $bbai_support_line ? '' : 'hidden'; ?>
                >
                    <?php echo esc_html( $bbai_support_line ); ?>
                </p>
            </div>
        </div>

        <?php if ( ! $bbai_is_exhausted_trial_checkpoint ) : ?>
            <div class="bbai-dashboard-hero-action__extras">
                <?php
                $bbai_hero_credits_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-credits-block.php';
                if ( is_readable( $bbai_hero_credits_partial ) ) {
                    include $bbai_hero_credits_partial;
                }
                ?>

                <section
                    class="bbai-dashboard-conversion-prompt bbai-dashboard-conversion-prompt--<?php echo esc_attr( $bbai_conversion_prompt_tone ); ?>"
                    data-bbai-hero-conversion-prompt
                    data-bbai-hero-conversion-tone="<?php echo esc_attr( $bbai_conversion_prompt_tone ); ?>"
                    <?php echo $bbai_conversion_prompt_visible ? '' : 'hidden'; ?>
                >
                    <div class="bbai-dashboard-conversion-prompt__copy-wrap">
                        <p class="bbai-dashboard-conversion-prompt__title" data-bbai-hero-conversion-title>
                            <?php echo esc_html( $bbai_conversion_prompt_title ); ?>
                        </p>
                        <p class="bbai-dashboard-conversion-prompt__copy" data-bbai-hero-conversion-copy>
                            <?php echo esc_html( $bbai_conversion_prompt_copy ); ?>
                        </p>
                        <p
                            class="bbai-dashboard-conversion-prompt__note"
                            data-bbai-hero-conversion-note
                            <?php echo '' !== $bbai_conversion_prompt_note ? '' : 'hidden'; ?>
                        >
                            <?php echo esc_html( $bbai_conversion_prompt_note ); ?>
                        </p>
                    </div>
                    <div
                        class="bbai-dashboard-conversion-prompt__actions"
                        data-bbai-hero-conversion-actions
                        <?php echo ! empty( $bbai_conversion_prompt_action['label'] ) ? '' : 'hidden'; ?>
                    >
                        <?php
                        $bbai_render_action(
                            $bbai_conversion_prompt_action,
                            'bbai-btn bbai-btn-secondary bbai-dashboard-conversion-prompt__cta',
                            'data-bbai-hero-conversion-cta'
                        );
                        ?>
                    </div>
                </section>

                <ul class="bbai-dashboard-hero-action__microcopy" data-bbai-hero-microcopy aria-label="<?php esc_attr_e( 'Why use automatic fixes', 'beepbeep-ai-alt-text-generator' ); ?>">
                    <li class="bbai-dashboard-hero-action__microcopy-item">
                        <span class="bbai-dashboard-hero-action__microcopy-icon" aria-hidden="true">&#10003;</span>
                        <span><?php esc_html_e( 'Takes ~5 seconds', 'beepbeep-ai-alt-text-generator' ); ?></span>
                    </li>
                    <li class="bbai-dashboard-hero-action__microcopy-item">
                        <span class="bbai-dashboard-hero-action__microcopy-icon" aria-hidden="true">&#10003;</span>
                        <span><?php esc_html_e( 'No manual work', 'beepbeep-ai-alt-text-generator' ); ?></span>
                    </li>
                    <li class="bbai-dashboard-hero-action__microcopy-item">
                        <span class="bbai-dashboard-hero-action__microcopy-icon" aria-hidden="true">&#10003;</span>
                        <span><?php esc_html_e( 'Improves SEO instantly', 'beepbeep-ai-alt-text-generator' ); ?></span>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bbai-dashboard-hero-action__footer">
            <?php
            $bbai_status_row_variant = 'hero';
            $bbai_status_row_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-status-row.php';
            if ( is_readable( $bbai_status_row_partial ) ) {
                include $bbai_status_row_partial;
            }
            ?>
        </div>
    </div>
</section>
