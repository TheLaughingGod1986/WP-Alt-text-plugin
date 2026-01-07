/**
 * Countdown Timer
 * Usage limit reset countdown
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

/**
 * Initialize and update countdown timer for limit reset
 */
function initCountdownTimer() {
    var countdownElement = document.querySelector('.bbai-countdown[data-countdown]');
    if (!countdownElement) {
        if (alttextaiDebug) console.log('[AltText AI] Countdown element not found');
        return;
    }

    var totalSeconds = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;

    if (totalSeconds <= 0) {
        if (alttextaiDebug) console.log('[AltText AI] Countdown has zero or invalid seconds:', totalSeconds);
        return;
    }

    var daysEl = countdownElement.querySelector('[data-days]');
    var hoursEl = countdownElement.querySelector('[data-hours]');
    var minutesEl = countdownElement.querySelector('[data-minutes]');

    if (!daysEl || !hoursEl || !minutesEl) {
        if (alttextaiDebug) console.warn('[AltText AI] Countdown elements not found');
        return;
    }

    // Store initial seconds and start time for accurate countdown
    countdownElement.setAttribute('data-initial-seconds', totalSeconds.toString());
    countdownElement.setAttribute('data-start-time', (Date.now() / 1000).toString());

    if (alttextaiDebug) {
        console.log('[AltText AI] Countdown initialized:', {
            totalSeconds: totalSeconds,
            days: Math.floor(totalSeconds / 86400),
            hours: Math.floor((totalSeconds % 86400) / 3600),
            minutes: Math.floor((totalSeconds % 3600) / 60)
        });
    }

    function updateCountdown() {
        // Try to use reset timestamp first (most accurate)
        var resetTimestamp = parseInt(countdownElement.getAttribute('data-reset-timestamp'), 10) || 0;
        var remaining = 0;

        if (resetTimestamp > 0) {
            var currentTime = Math.floor(Date.now() / 1000);
            remaining = Math.max(0, resetTimestamp - currentTime);
        } else {
            var initialSeconds = parseInt(countdownElement.getAttribute('data-initial-seconds'), 10) || 0;

            if (initialSeconds <= 0) {
                daysEl.textContent = '0';
                hoursEl.textContent = '0';
                minutesEl.textContent = '0';
                if (window.alttextaiCountdownInterval) {
                    clearInterval(window.alttextaiCountdownInterval);
                    window.alttextaiCountdownInterval = null;
                }
                return;
            }

            var startTime = parseFloat(countdownElement.getAttribute('data-start-time')) || (Date.now() / 1000);
            var currentTime = Date.now() / 1000;
            var elapsed = Math.max(0, Math.floor(currentTime - startTime));
            remaining = Math.max(0, initialSeconds - elapsed);
        }

        if (remaining <= 0) {
            daysEl.textContent = '0';
            hoursEl.textContent = '0';
            minutesEl.textContent = '0';
            countdownElement.setAttribute('data-countdown', '0');
            if (window.alttextaiCountdownInterval) {
                clearInterval(window.alttextaiCountdownInterval);
                window.alttextaiCountdownInterval = null;
            }
            return;
        }

        var days = Math.floor(remaining / 86400);
        var hours = Math.floor((remaining % 86400) / 3600);
        var minutes = Math.floor((remaining % 3600) / 60);

        daysEl.textContent = days.toString();
        hoursEl.textContent = hours.toString();
        minutesEl.textContent = minutes.toString();
        countdownElement.setAttribute('data-countdown', remaining);
    }

    // Update immediately
    updateCountdown();

    // Clear any existing interval
    if (window.alttextaiCountdownInterval) {
        clearInterval(window.alttextaiCountdownInterval);
    }

    // Update every second
    window.alttextaiCountdownInterval = setInterval(function() {
        updateCountdown();
    }, 1000);
}

// Export function
window.initCountdownTimer = initCountdownTimer;
