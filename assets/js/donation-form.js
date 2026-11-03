(function () {
	'use strict';

	document.querySelectorAll('.bachs-donation-form').forEach(function (form) {
		var currency = form.querySelector('[name="currency"]');
		var amountChoices = form.querySelectorAll('.bachs-donation-amount-choice');
		var customAmount = form.querySelector('[name="custom_amount"]');
		var validationMessage = form.querySelector('.bachs-donation-validation');

		if (!currency) {
			return;
		}

		var updateChoices = function () {
			amountChoices.forEach(function (choice) {
				var matchesCurrency = choice.getAttribute('data-currency') === currency.value;
				choice.hidden = !matchesCurrency;
				if (!matchesCurrency) {
					var input = choice.querySelector('input');
					if (input) {
						input.checked = false;
					}
				}
			});
		};

		currency.addEventListener('change', updateChoices);
		amountChoices.forEach(function (choice) {
			var input = choice.querySelector('input');
			if (!input) {
				return;
			}
			input.addEventListener('change', function () {
				if (input.checked) {
					if (currency.tagName === 'SELECT') {
						currency.value = choice.getAttribute('data-currency');
					}
					if (customAmount) {
						customAmount.value = '';
					}
					if (validationMessage) {
						validationMessage.hidden = true;
					}
					updateChoices();
				}
			});
		});

		if (customAmount) {
			customAmount.addEventListener('input', function () {
				if (customAmount.value !== '') {
					form.querySelectorAll('[name="preset_amount"]').forEach(function (input) {
						input.checked = false;
					});
					if (validationMessage) {
						validationMessage.hidden = true;
					}
				}
			});
		}

		form.addEventListener('submit', function (event) {
			var selectedPreset = form.querySelector('[name="preset_amount"]:checked');
			var hasCustomAmount = customAmount && customAmount.value !== '';
			if (!selectedPreset && !hasCustomAmount) {
				event.preventDefault();
				var firstChoice = form.querySelector('.bachs-donation-amount-choice:not([hidden]) input');
				if (firstChoice) {
					firstChoice.focus();
				} else if (customAmount) {
					customAmount.focus();
				}
				if (validationMessage) {
					validationMessage.hidden = false;
				}
			}
		});
		updateChoices();
	});
}());
