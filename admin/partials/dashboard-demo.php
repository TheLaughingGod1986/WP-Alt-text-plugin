<?php
/**
 * Dashboard demo content for non-authenticated users.
 */
if (!defined('ABSPATH')) { exit; }
?>
<div class="bbai-demo-preview">
                    <!-- Demo Badge Overlay -->
                            <div class="bbai-demo-badge-overlay">
                                <span class="bbai-demo-badge-text"><?php esc_html_e('DEMO PREVIEW', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </div>
                            
                            <!-- Usage Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-dashboard-card--featured bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('USAGE STATUS', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('0 of 50 image descriptions generated this month.', 'beepbeep-ai-alt-text-generator'); ?>
                                    </h2>
                                    <p style="margin: 12px 0 0 0; font-size: 14px; color: #6b7280;">
                                        <?php esc_html_e('Sign in to track your usage and access premium features.', 'beepbeep-ai-alt-text-generator'); ?>
                                    </p>
                                </div>
                                
                                <div class="bbai-dashboard-usage-bar">
                                    <div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="bbai-dashboard-usage-stats">
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Used', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value">50</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Resets', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value"><?php echo esc_html(date_i18n('F j, Y', strtotime('first day of next month'))); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Saved Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-time-saved-card bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('TIME SAVED', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">⏱️</span>
                                        <?php esc_html_e('Ready to optimize your images', 'beepbeep-ai-alt-text-generator'); ?>
                                    </h2>
                                    <p class="bbai-seo-impact" style="margin-top: 8px; font-size: 14px; color: #6b7280;"><?php esc_html_e('Start generating alt text to improve SEO and accessibility', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>

                            <!-- Image Optimization Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('IMAGE OPTIMIZATION', 'beepbeep-ai-alt-text-generator'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('Ready to optimize images', 'beepbeep-ai-alt-text-generator'); ?>
                                    </h2>
                                </div>
                                
                                <div class="bbai-dashboard-usage-bar">
                                    <div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="bbai-dashboard-usage-stats">
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value">—</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Total', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-dashboard-usage-value">—</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo CTA -->
                            <div class="bbai-demo-cta">
                                <p class="bbai-demo-cta-text"><?php esc_html_e('✨ Sign up now to start generating alt text for your images!', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <button type="button" class="bbai-btn-primary bbai-btn-icon" id="bbai-demo-signup-btn">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Generate 50 AI Alt Texts Free', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </button>
                            </div>
                        </div>

                    <?php 
                    // Check for stored token to determine if authenticated
                    $check_limit_token = get_option('beepbeepai_jwt_token', '');
                    $is_auth_for_limit = $this->api_client->is_authenticated() || !empty($check_limit_token);
                    ?>
                    <?php if ($is_auth_for_limit && ($usage_stats['remaining'] ?? 0) <= 0) : ?>
                        <div class="bbai-limit-reached">
                            <div class="bbai-limit-header-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3 class="bbai-limit-title"><?php esc_html_e('Monthly quota reached — upgrade to Pro for 1,000 generations per month', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-limit-description">
                                <?php
                                    $reset_label = $usage_stats['reset_date'] ?? '';
                                    printf(
                                        esc_html__('You\'ve used all %1$d free generations this month. Your quota resets on %2$s.', 'beepbeep-ai-alt-text-generator'),
                                        $usage_stats['limit'],
                                        esc_html($reset_label)
                                    );
                                ?>
                            </p>

                            <div class="bbai-countdown" data-countdown="<?php echo esc_attr($usage_stats['seconds_until_reset'] ?? 0); ?>" data-reset-timestamp="<?php echo esc_attr($usage_stats['reset_timestamp'] ?? 0); ?>">
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-days>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('days', 'beepbeep-ai-alt-text-generator'); ?></div>
                                </div>
                                <div class="bbai-countdown-separator">—</div>
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-hours>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('hours', 'beepbeep-ai-alt-text-generator'); ?></div>
                                </div>
                                <div class="bbai-countdown-separator">—</div>
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-minutes>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('mins', 'beepbeep-ai-alt-text-generator'); ?></div>
                                </div>
                            </div>

                            <div class="bbai-limit-cta">
                                <button type="button" class="bbai-limit-upgrade-btn bbai-limit-upgrade-btn--full" data-action="show-upgrade-modal" data-upgrade-source="upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                            </div>
                        </div>
<?php endif; ?>
