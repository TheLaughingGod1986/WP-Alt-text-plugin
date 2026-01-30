/**
 * Media library fallback handlers.
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

(function($) {
    'use strict';

    function getEnv() {
        return window.bbai_env || {};
    }

    function getAjaxUrl() {
        var env = getEnv();
        if (env && env.ajax_url) {
            return env.ajax_url;
        }
        if (window.bbai_ajax) {
            return window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || '';
        }
        return '';
    }

    function refreshDashboard() {
        if (!window.BBAI || !BBAI.restStats || !window.fetch) {
            return;
        }

        var nonce = (BBAI.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''));
        var headers = {
            'X-WP-Nonce': nonce,
            'Accept': 'application/json'
        };
        var statsUrl = BBAI.restStats + (BBAI.restStats.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';
        var usageUrl = BBAI.restUsage || '';

        Promise.all([
            fetch(statsUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }),
            usageUrl ? fetch(usageUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }) : Promise.resolve(null)
        ])
            .then(function(results){
                var stats = results[0], usage = results[1];
                if (!stats){ return; }
                if (typeof window.dispatchEvent === 'function'){
                    try {
                        window.dispatchEvent(new CustomEvent('bbai-stats-update', { detail: { stats: stats, usage: usage } }));
                    } catch(e){}
                }
            })
            .catch(function(){});
    }

    function restore(btn) {
        var original = btn.data('original-text');
        btn.text(original || 'Generate Alt');
        if (btn.is('button, input')) {
            btn.prop('disabled', false);
        }
    }

    function updateAltField(id, value, context) {
        var selectors = [
            '#attachment_alt',
            '#attachments-' + id + '-alt',
            '[data-setting="alt"] textarea',
            '[data-setting="alt"] input',
            '[name="attachments[' + id + '][alt]"]',
            '[name="attachments[' + id + '][_wp_attachment_image_alt]"]',
            '[name="attachments[' + id + '][image_alt]"]',
            'textarea[name="_wp_attachment_image_alt"]',
            'input[name="_wp_attachment_image_alt"]',
            'textarea[aria-label="Alternative Text"]',
            '.attachment-details textarea',
            '.attachment-details input[name*="_wp_attachment_image_alt"]'
        ];
        var field;
        selectors.some(function(sel){
            var scoped = context && context.length ? context.find(sel) : $(sel);
            if (scoped.length){
                field = scoped.first();
                return true;
            }
            return false;
        });
        if (!field || !field.length) {
            var fallback = $('#attachment_alt');
            if (!fallback.length){ fallback = $('textarea[name="_wp_attachment_image_alt"]'); }
            if (!fallback.length){ fallback = $('textarea[aria-label="Alternative Text"]'); }
            if (fallback.length){ field = fallback.first(); }
        }
        if (field && field.length) {
            field.val(value);
            field.text(value);
            field.attr('value', value);
            field.trigger('input').trigger('change');
        } else {
            try {
                var env = getEnv();
                var restRoot = env.rest_root || ((window.wp && window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : '');
                if (!restRoot) {
                    restRoot = '/wp-json/';
                }

                var nonce = env.nonce || ((window.BBAI && BBAI.nonce) ? BBAI.nonce : (window.wpApiSettings ? wpApiSettings.nonce : ''));
                if (restRoot && nonce) {
                    fetch(restRoot + 'wp/v2/media/' + id, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ alt_text: value })
                    }).then(function(){
                        var ctx = context && context.length ? context : $('.attachment-details');
                        var tf = ctx.find('textarea, input').filter('[name*="_wp_attachment_image_alt"], [aria-label="Alternative Text"], #attachment_alt').first();
                        if (tf && tf.length){ tf.val(value).text(value).attr('value', value).trigger('input').trigger('change'); }
                    }).catch(function(){});
                }
            } catch(e){}
        }

        if (window.wp && wp.media && typeof wp.media.attachment === 'function'){
            var attachment = wp.media.attachment(id);
            if (attachment){
                try { attachment.set('alt', value); } catch (err) {}
            }
        }
    }

    function pushNotice(type, message){
        if (window.wp && wp.data && wp.data.dispatch){
            try {
                wp.data.dispatch('core/notices').createNotice(type, message, { isDismissible: true });
                return;
            } catch(err) {}
        }
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        var $target = $('#wpbody-content').find('.wrap').first();
        if ($target.length){
            $target.prepend($notice);
        } else {
            $('#wpbody-content').prepend($notice);
        }
    }

    function canManageAccount(){
        return !!(window.BBAI && BBAI.canManage);
    }

    function handleLimitReachedNotice(payload){
        var message = (payload && payload.message) ? payload.message : 'Monthly limit reached. Please contact a site administrator.';
        pushNotice('warning', message);

        if (!canManageAccount()){
            return;
        }

        try {
            if (window.bbaiApp && bbaiApp.upgradeUrl) {
                window.open(bbaiApp.upgradeUrl, '_blank');
            } else if (window.BBAI && BBAI.upgradeUrl) {
                window.open(BBAI.upgradeUrl, '_blank');
            } else if (window.bbaiApp && bbaiApp.upgradeUrl) {
                window.open(bbaiApp.upgradeUrl, '_blank');
            }
        } catch(e){}

        if (jQuery('.bbai-upgrade-banner').length){
            jQuery('.bbai-upgrade-banner .show-upgrade-modal').trigger('click');
        }
    }

    $(document).on('click', '[data-action="regenerate-single"]', function(e){
        e.preventDefault();

        var btn = $(this);
        var btnElement = btn[0];
        var attachment_id = btnElement ? btnElement.getAttribute('data-attachment-id') : null;

        if (!attachment_id) {
            var parentRow = btn.closest('tr[data-attachment-id]');
            if (parentRow.length) {
                attachment_id = parentRow[0].getAttribute('data-attachment-id');
            }
        }

        if (!attachment_id) {
            console.warn('WARNING: Could not read attachment_id from HTML attribute, using jQuery fallback');
            attachment_id = btn.attr('data-attachment-id') || btn.data('attachment-id');
        }

        attachment_id = parseInt(attachment_id, 10);

        if (!attachment_id || isNaN(attachment_id) || attachment_id <= 0){
            console.error('ERROR: Invalid attachment ID:', attachment_id);
            return pushNotice('error', 'AI ALT: Invalid attachment ID. Please refresh the page and try again.');
        }

        if (typeof btn.data('original-text') === 'undefined'){
            btn.data('original-text', btn.text());
        }

        btn.text('Regenerating…').prop('disabled', true);

        var nonce = (window.BBAI && BBAI.nonce) ||
            (window.wpApiSettings && wpApiSettings.nonce) ||
            (window.bbai_ajax && window.bbai_ajax.nonce) ||
            jQuery('#license-nonce').val() ||
            '';

        var ajaxUrl = getAjaxUrl();
        if (!ajaxUrl){
            restore(btn);
            return pushNotice('error', 'AJAX endpoint unavailable.');
        }

        var ajaxData = {
            action: 'beepbeepai_regenerate_single',
            nonce: nonce,
            attachment_id: attachment_id,
            _timestamp: Date.now()
        };

        var final_check_id = null;
        if (btnElement) {
            final_check_id = btnElement.getAttribute('data-attachment-id') ||
                btnElement.getAttribute('data-image-id') ||
                btnElement.getAttribute('data-id');
        }

        if (!final_check_id) {
            var parentRowCheck = btn.closest('tr[data-attachment-id]')[0];
            if (parentRowCheck) {
                final_check_id = parentRowCheck.getAttribute('data-attachment-id');
            }
        }

        final_check_id = final_check_id ? parseInt(final_check_id, 10) : null;
        if (final_check_id && !isNaN(final_check_id) && final_check_id > 0) {
            attachment_id = final_check_id;
            ajaxData.attachment_id = attachment_id;
        }

        jQuery.post(ajaxUrl, ajaxData, function(response){
            restore(btn);

            if (response.success){
                var altText = (response.data && response.data.altText) ||
                    (response.data && response.data.alt_text) ||
                    (response.data && response.data.data && response.data.data.altText) ||
                    (response.data && response.data.data && response.data.data.alt_text) ||
                    (typeof response.data === 'string' ? response.data : '');

                if (altText && typeof altText === 'string' && altText.length > 0){
                    var existingModal = $('#bbai-regenerate-modal');
                    if (existingModal.length && existingModal.is(':visible')){
                        existingModal.find('#bbai-regenerate-content').html(
                            '<p style="color: #059669; padding: 10px; background: #d1fae5; border-radius: 4px;">' +
                            'Success! New alt text: ' + altText + '</p>'
                        );

                        existingModal.find('.bbai-btn-apply, [data-action="accept"]').off('click').on('click', function(){
                            var saveNonce = (window.BBAI && BBAI.nonce) || (window.wpApiSettings && wpApiSettings.nonce) || '';
                            var restAlt = (window.BBAI && BBAI.restAlt) || '';
                            if (!restAlt) {
                                updateAltField(attachment_id, altText, existingModal);
                                existingModal.hide();
                                refreshDashboard();
                                return;
                            }

                            fetch(restAlt + attachment_id, {
                                method: 'POST',
                                headers: {
                                    'X-WP-Nonce': saveNonce,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({alt_text: altText})
                            }).then(function(r){ return r.json(); }).then(function(saveData){
                                if (saveData && saveData.alt_text){
                                    pushNotice('success', 'Alt text updated successfully.');
                                    existingModal.hide();
                                    refreshDashboard();
                                } else {
                                    pushNotice('error', 'Failed to save alt text. Please try again.');
                                }
                            }).catch(function(){
                                pushNotice('error', 'Failed to save alt text. Please try again.');
                            });
                        });
                    } else {
                        pushNotice('success', 'Alt text regenerated successfully.');
                        updateAltField(attachment_id, altText, btn.closest('.attachment-details'));
                        refreshDashboard();
                    }
                } else {
                    pushNotice('error', 'Alt text was generated but the response format was invalid.');
                }
            } else {
                var errorMsg = (response.data && response.data.message) || 'Failed to regenerate alt text';
                pushNotice('error', errorMsg);

                var errorModal = $('#bbai-regenerate-modal');
                if (errorModal.length && errorModal.is(':visible')){
                    errorModal.find('#bbai-regenerate-content').html(
                        '<p style="color: #dc2626; padding: 10px; background: #fef2f2; border-radius: 4px;">' +
                        errorMsg + '</p>'
                    );
                }

                if (response.data && response.data.code === 'limit_reached'){
                    handleLimitReachedNotice(response.data);
                }
            }
        }).fail(function(xhr){
            restore(btn);
            var errorMsg = 'Request failed. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message){
                errorMsg = xhr.responseJSON.data.message;
            }
            pushNotice('error', errorMsg);
        });
    });
})(jQuery);
