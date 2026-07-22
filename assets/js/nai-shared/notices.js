(function (window) {
	'use strict';

	function toast(root, message, sub, tone) {
		var el = root ? root.querySelector('[data-nai-toast]') : null;
		var close;
		if (!el) {
			return;
		}

		el.className = 'nai-toast' + (tone ? ' nai-toast--' + tone : '');
		el.textContent = '';

		var title = document.createElement('strong');
		title.textContent = message;
		el.appendChild(title);

		if (sub) {
			var body = document.createElement('span');
			body.textContent = sub;
			el.appendChild(body);
		}

		close = document.createElement('button');
		close.type = 'button';
		close.setAttribute('aria-label', 'Dismiss notice');
		close.textContent = '×';
		close.addEventListener('click', function () {
			el.hidden = true;
		}, { once: true });
		el.appendChild(close);

		el.hidden = false;
		clearTimeout(el._timer);
		el._timer = setTimeout(function () {
			el.hidden = true;
		}, 4200);
	}

	window.BBAINaiNotices = {
		toast: toast
	};
}(window));
