(function (window) {
	'use strict';

	function isMissing(row) {
		return !!(row && String(row.getAttribute('data-alt-missing') || 'false') === 'true');
	}

	function getAttachmentId(row) {
		var id = row && row.getAttribute ? parseInt(row.getAttribute('data-attachment-id') || '', 10) : 0;
		return isNaN(id) ? 0 : id;
	}

	window.BBAIAltLibraryRowUpdates = {
		getAttachmentId: getAttachmentId,
		isMissing: isMissing
	};
}(window));
