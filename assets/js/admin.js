(function () {
	'use strict';

	document.querySelectorAll('[data-bachs-copy]').forEach(function (button) {
		button.addEventListener('click', function () {
			var value = button.getAttribute('data-bachs-copy') || '';
			var originalText = button.textContent;
			var showCopied = function () {
				button.textContent = 'Copied';
				window.setTimeout(function () {
					button.textContent = originalText;
				}, 1600);
			};

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(value).then(showCopied);
				return;
			}

			var temporary = document.createElement('textarea');
			temporary.value = value;
			temporary.setAttribute('readonly', 'readonly');
			temporary.style.position = 'fixed';
			temporary.style.opacity = '0';
			document.body.appendChild(temporary);
			temporary.select();
			try {
				document.execCommand('copy');
				showCopied();
			} finally {
				document.body.removeChild(temporary);
			}
		});
	});
}());
