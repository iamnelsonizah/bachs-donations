(function () {
	'use strict';

	document.querySelectorAll('.bachs-donation-form').forEach(function (form) {
		var currency = form.querySelector('[name="currency"]');
		var presets = form.querySelector('[name="preset_amount"]');

		if (!currency || !presets) {
			return;
		}

		var groups = presets.querySelectorAll('optgroup[data-currency]');
		var updateGroups = function () {
			groups.forEach(function (group) {
				group.hidden = group.getAttribute('data-currency') !== currency.value;
			});
		};

		currency.addEventListener('change', updateGroups);
		presets.addEventListener('change', function () {
			var selected = presets.value.split(':');
			if (selected.length === 2 && currency.tagName === 'SELECT') {
				currency.value = selected[0];
				updateGroups();
			}
		});
		updateGroups();
	});
}());
