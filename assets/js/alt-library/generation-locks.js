(function (window) {
	'use strict';

	function ensureLock() {
		window.bbaiGenerationLock = window.bbaiGenerationLock || {
			active: false,
			jobId: null,
			source: null,
			startedAt: null
		};
		return window.bbaiGenerationLock;
	}

	function isLocked() {
		return !!(ensureLock() && window.bbaiGenerationLock.active);
	}

	function stopDuplicate(event) {
		if (!isLocked()) {
			return false;
		}
		if (event && typeof event.preventDefault === 'function') {
			event.preventDefault();
		}
		if (event && typeof event.stopPropagation === 'function') {
			event.stopPropagation();
		}
		return true;
	}

	function acquire(event, source, jobId, applyUi) {
		if (stopDuplicate(event)) {
			return false;
		}
		window.bbaiGenerationLock = {
			active: true,
			source: source || 'dashboard_generate',
			jobId: jobId || null,
			startedAt: Date.now()
		};
		window.bbaiGenerationInProgress = true;
		if (typeof applyUi === 'function') {
			applyUi();
		}
		return true;
	}

	function set(source, jobId, applyUi) {
		if (isLocked()) {
			return;
		}
		window.bbaiGenerationLock = {
			active: true,
			source: source || null,
			jobId: jobId || null,
			startedAt: Date.now()
		};
		window.bbaiGenerationInProgress = true;
		if (typeof applyUi === 'function') {
			applyUi();
		}
	}

	function clear(releaseUi) {
		var lock = ensureLock();
		lock.active = false;
		lock.source = null;
		lock.jobId = null;
		lock.startedAt = null;
		window.bbaiGenerationInProgress = false;
		if (typeof releaseUi === 'function') {
			releaseUi();
		}
	}

	window.BBAIAltLibraryGenerationLocks = {
		acquire: acquire,
		clear: clear,
		isLocked: isLocked,
		set: set,
		stopDuplicate: stopDuplicate
	};
}(window));
