/**
 * Admin panel helpers (API notice modal, admin login, tab switching)
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

(function($) {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;

    function getAjaxUrl() {
        if (window.bbai_ajax) {
            return window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || '';
        }
        return '';
    }

    function showAuthModal(mode) {
        if (typeof window.bbaiShowAuthModal === 'function') {
            window.bbaiShowAuthModal(mode);
            return;
        }

        if (typeof window.alttextaiShowModal === 'function') {
            window.alttextaiShowModal();
            return;
        }

        var authBannerBtn = document.getElementById('bbai-show-auth-banner-btn');
        if (authBannerBtn) {
            authBannerBtn.click();
            return;
        }

        var demoSignupBtn = document.getElementById('bbai-demo-signup-btn');
        if (demoSignupBtn) {
            demoSignupBtn.click();
            return;
        }

        var authBtn = document.querySelector('[data-action="show-auth-modal"], [data-action="show-login"], [data-bbai-auth]');
        if (authBtn) {
            authBtn.click();
            return;
        }

        if (typeof CustomEvent === 'function') {
            var event = new CustomEvent('bbai:show-auth', {
                detail: { mode: mode || 'login' },
                bubbles: true
            });
            document.dispatchEvent(event);
        }
    }

    function initLoggedOutActions() {
        var signinBtn = document.getElementById('bbai-logged-out-signin-btn');
        var signupLink = document.getElementById('bbai-logged-out-signup-link');

        if (signinBtn) {
            signinBtn.addEventListener('click', function(e) {
                e.preventDefault();
                showAuthModal('login');
            });
        }

        if (signupLink) {
            signupLink.addEventListener('click', function(e) {
                e.preventDefault();
                showAuthModal('register');
            });
        }
    }

    function dismissApiNotice() {
        var ajaxUrl = getAjaxUrl();
        if (!ajaxUrl || !window.bbai_ajax || !window.bbai_ajax.nonce) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'beepbeepai_dismiss_api_notice',
                nonce: window.bbai_ajax.nonce
            }
        });
    }

    function closeApiNoticeModal() {
        var $modal = $('#bbai-api-notice-modal');
        if (!$modal.length) {
            return;
        }

        $modal.animate({
            opacity: '0'
        }, 200, function() {
            dismissApiNotice();
            $modal.remove();
        });
    }

    window.bbaiCloseApiNotice = function() {
        closeApiNoticeModal();
    };

    $(document).ready(function() {
        var $modal = $('#bbai-api-notice-modal');
        if (!$modal.length) {
            initLoggedOutActions();
            return;
        }

        setTimeout(function() {
            $modal.css({
                display: 'flex',
                opacity: '0'
            }).animate({
                opacity: '1'
            }, 300);
        }, 500);

        initLoggedOutActions();
    });

    $(document).on('click', '#bbai-api-notice-modal.bbai-modal-backdrop', function(e) {
        if (e.target === this) {
            e.preventDefault();
            e.stopPropagation();
            closeApiNoticeModal();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            var $modal = $('#bbai-api-notice-modal');
            if ($modal.length && $modal.is(':visible')) {
                closeApiNoticeModal();
            }
        }
    });

    $(document).on('submit', '#bbai-admin-login-form', function(e) {
        e.preventDefault();

        var ajaxUrl = getAjaxUrl();
        if (!ajaxUrl) {
            return;
        }

        var $form = $(this);
        var $status = $('#bbai-admin-login-status');
        var $btn = $('#admin-login-submit-btn');
        var $btnText = $btn.find('.bbai-btn__text');
        var $btnSpinner = $btn.find('.bbai-btn__spinner');

        var email = $('#admin-login-email').val().trim();
        var password = $('#admin-login-password').val();

        $btn.prop('disabled', true);
        $btnText.hide();
        $btnSpinner.show();
        $status.hide();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'bbai_admin_login',
                nonce: window.bbai_ajax ? window.bbai_ajax.nonce : '',
                email: email,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    $status.removeClass('error').addClass('success').text(response.data.message || __('Successfully logged in', 'beepbeep-ai-alt-text-generator')).show();
                    setTimeout(function() {
                        window.location.href = response.data.redirect || window.location.href;
                    }, 1000);
                } else {
                    $status.removeClass('success').addClass('error').text(response.data && response.data.message ? response.data.message : __('Login failed', 'beepbeep-ai-alt-text-generator')).show();
                    $btn.prop('disabled', false);
                    $btnText.show();
                    $btnSpinner.hide();
                }
            },
            error: function() {
                $status.removeClass('success').addClass('error').text(__('Network error. Please try again.', 'beepbeep-ai-alt-text-generator')).show();
                $btn.prop('disabled', false);
                $btnText.show();
                $btnSpinner.hide();
            }
        });
    });

    $(document).on('click', '.bbai-admin-tab', function(e) {
        e.preventDefault();

        var $tab = $(this);
        var tabName = $tab.data('admin-tab');

        $('.bbai-admin-tab').removeClass('active');
        $tab.addClass('active');

        $('.bbai-admin-tab-content').hide();
        $('.bbai-admin-tab-content[data-admin-tab-content="' + tabName + '"]').show();

        if (tabName === 'settings' && typeof window.loadLicenseSiteUsage === 'function') {
            setTimeout(function() {
                window.loadLicenseSiteUsage();
            }, 100);
        }

        if (history.pushState) {
            history.pushState(null, null, '#' + tabName);
        }
    });

    $(document).ready(function() {
        var hash = window.location.hash.replace('#', '');
        if (hash === 'debug' || hash === 'settings') {
            $('.bbai-admin-tab[data-admin-tab="' + hash + '"]').trigger('click');
        }
    });

    $(document).on('click', '#bbai-admin-logout-btn', function(e) {
        e.preventDefault();

        var confirmMessage = window.bbai_ajax && window.bbai_ajax.admin_logout_confirm
            ? window.bbai_ajax.admin_logout_confirm
            : __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator');

        if (!confirm(confirmMessage)) {
            return;
        }

        var ajaxUrl = getAjaxUrl();
        if (!ajaxUrl) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'bbai_admin_logout',
                nonce: window.bbai_ajax ? window.bbai_ajax.nonce : ''
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || window.location.href;
                } else if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                    window.bbaiModal.error((response.data && response.data.message) || __('Logout failed', 'beepbeep-ai-alt-text-generator'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                    window.bbaiModal.error(__('Network error. Please try again.', 'beepbeep-ai-alt-text-generator'));
                }
                $btn.prop('disabled', false);
            }
        });
    });
})(jQuery);
