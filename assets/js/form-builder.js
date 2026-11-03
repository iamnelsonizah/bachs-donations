(function () {
	'use strict';

	var builder = document.getElementById('bachs-donation-builder');
	var form = document.getElementById('bachs-builder-form');
	if (!builder || !form) {
		return;
	}

	var dialog = builder.querySelector('.bachs-builder__dialog');
	var title = builder.querySelector('#bachs-builder-title');
	var titleInput = form.querySelector('[name="form_title"]');
	var excerptInput = form.querySelector('[name="form_excerpt"]');
	var contentInput = form.querySelector('[name="form_content"]');
	var imageIdInput = form.querySelector('[name="featured_image_id"]');
	var imagePreview = builder.querySelector('[data-bachs-image-preview]');
	var imageName = builder.querySelector('[data-bachs-image-name]');
	var removeImageButton = builder.querySelector('[data-bachs-remove-image]');
	var previewCard = builder.querySelector('.bachs-preview-card');
	var previewFields = builder.querySelector('[data-bachs-preview-fields]');
	var customFieldContainer = builder.querySelector('[data-bachs-custom-fields]');
	var customFieldTemplate = builder.querySelector('#bachs-custom-field-template');
	var customFieldIndex = 0;
	var previousFocus = null;
	var selectedImageUrl = '';

	function field(name) {
		var fields = form.querySelectorAll('[name]');
		for (var index = 0; index < fields.length; index += 1) {
			if (fields[index].name === name) {
				return fields[index];
			}
		}
		return null;
	}

	function setValue(name, value) {
		var input = field(name);
		if (input) {
			input.value = value === null || typeof value === 'undefined' ? '' : value;
		}
	}

	function setChecked(name, value) {
		var fields = form.querySelectorAll('[name]');
		fields.forEach(function (input) {
			if (input.name === name) {
				input.checked = Array.isArray(value) ? value.indexOf(input.value) !== -1 : Boolean(value);
			}
		});
	}

	function checkedValues(name) {
		return Array.prototype.slice.call(form.querySelectorAll('[name]')).filter(function (input) {
			return input.name === name && input.checked;
		}).map(function (input) {
			return input.value;
		});
	}

	function contrastText(hex) {
		var normalized = (hex || '#176b52').replace('#', '');
		if (normalized.length === 3) {
			normalized = normalized.split('').map(function (digit) { return digit + digit; }).join('');
		}
		if (normalized.length !== 6) {
			return '#ffffff';
		}
		var channels = [0, 2, 4].map(function (offset) {
			var channel = parseInt(normalized.slice(offset, offset + 2), 16) / 255;
			return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
		});
		var luminance = (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
		var darkContrast = (luminance + 0.05) / 0.05;
		var lightContrast = 1.05 / (luminance + 0.05);
		return darkContrast >= lightContrast ? '#15231d' : '#ffffff';
	}

	function updateCurrencyFields() {
		var selected = checkedValues('bachs_form[currencies][]');
		builder.querySelectorAll('[data-bachs-currency-field], [data-bachs-methods]').forEach(function (section) {
			section.hidden = selected.indexOf(section.getAttribute('data-bachs-currency-field') || section.getAttribute('data-bachs-methods')) === -1;
		});
		var defaultCurrency = field('bachs_form[default_currency]');
		if (defaultCurrency) {
			Array.prototype.slice.call(defaultCurrency.options).forEach(function (option) {
				option.disabled = selected.indexOf(option.value) === -1;
			});
			if (selected.indexOf(defaultCurrency.value) === -1 && selected.length) {
				defaultCurrency.value = selected[0];
			}
		}
	}

	function updateImage(url, id) {
		selectedImageUrl = url || '';
		imageIdInput.value = id || '0';
		imagePreview.hidden = !selectedImageUrl;
		removeImageButton.hidden = !selectedImageUrl;
		if (selectedImageUrl) {
			imagePreview.src = selectedImageUrl;
			imageName.textContent = BachsDonationBuilder.imageAlt;
		} else {
			imagePreview.removeAttribute('src');
			imageName.textContent = 'Optional. A clear, relevant image helps supporters connect with your cause.';
		}
		var previewImage = builder.querySelector('[data-bachs-preview-image]');
		previewImage.hidden = !selectedImageUrl;
		previewImage.style.backgroundImage = selectedImageUrl ? 'url("' + selectedImageUrl.replace(/["\\]/g, '') + '")' : '';
	}

	function updatePreview() {
		var previewTitle = builder.querySelector('[data-bachs-preview-title]');
		var previewDescription = builder.querySelector('[data-bachs-preview-description]');
		var previewButton = builder.querySelector('[data-bachs-preview-button]');
		var accent = field('bachs_form[accent_color]').value || '#176b52';
		var style = field('bachs_form[form_style]').value;
		var corners = field('bachs_form[corner_style]').value;
		var enteredTitle = titleInput.value.trim();
		var enteredExcerpt = excerptInput.value.trim();
		var buttonText = field('bachs_form[button_text]').value.trim();
		var currency = field('bachs_form[default_currency]').value || 'USD';
		var amountInput = field('bachs_form[amounts][' + currency + ']');
		var amounts = amountInput ? amountInput.value.split(/[\n,]+/).map(function (amount) { return amount.trim(); }).filter(Boolean).slice(0, 3) : [];
		var previewAmountButtons = builder.querySelectorAll('.bachs-preview-card__amounts button');
		var fallbackAmounts = currency === 'USD' ? ['10.00', '25.00', '50.00'] : ['5000.00', '10000.00', '25000.00'];
		previewTitle.textContent = enteredTitle || 'Your cause name';
		previewDescription.textContent = enteredExcerpt || 'A short introduction to the cause will appear here.';
		previewButton.textContent = buttonText || 'Continue to secure payment';
		while (previewFields.firstChild) {
			previewFields.removeChild(previewFields.firstChild);
		}
		var nameField = field('bachs_form[donor_name]');
		var nameRequired = field('bachs_form[donor_name_required]');
		if (nameField && nameField.checked) {
			addPreviewField('Your name', 'text', nameRequired && nameRequired.checked);
		}
		addPreviewField('Email address', 'text', true);
		var phoneField = field('bachs_form[donor_phone]');
		var phoneRequired = field('bachs_form[donor_phone_required]');
		if (phoneField && phoneField.checked) {
			addPreviewField('Phone number', 'text', phoneRequired && phoneRequired.checked);
		}
		customFieldContainer.querySelectorAll('[data-bachs-custom-field]').forEach(function (customField) {
			var label = customField.querySelector('[data-bachs-custom-label]').value.trim() || 'Your question';
			var type = customField.querySelector('[data-bachs-custom-type]').value;
			var required = customField.querySelector('[name$="[required]"]').checked;
			addPreviewField(label, type, required);
		});
		previewCard.style.setProperty('--bachs-preview-accent', accent);
		previewCard.style.setProperty('--bachs-preview-button-text', contrastText(accent));
		previewAmountButtons.forEach(function (button, index) {
			button.textContent = currency + ' ' + (amounts[index] || fallbackAmounts[index]);
		});
		previewCard.classList.remove('bachs-preview-card--soft', 'bachs-preview-card--outlined', 'bachs-preview-card--minimal', 'bachs-preview-card--square');
		previewCard.classList.add('bachs-preview-card--' + style);
		if (corners === 'square') {
			previewCard.classList.add('bachs-preview-card--square');
		}
	}

	function addPreviewField(labelText, type, required) {
		var previewField = document.createElement('div');
		previewField.className = 'bachs-preview-field';
		var label = document.createElement('span');
		label.className = 'bachs-preview-field__label';
		label.textContent = labelText + (required ? ' *' : '');
		previewField.appendChild(label);
		if (type === 'checkbox') {
			var checkbox = document.createElement('span');
			checkbox.className = 'bachs-preview-field__checkbox';
			checkbox.textContent = '✓';
			previewField.appendChild(checkbox);
		} else {
			var control = document.createElement('span');
			control.className = 'bachs-preview-field__control' + (type === 'textarea' ? ' is-textarea' : '') + (type === 'select' ? ' is-select' : '');
			control.textContent = type === 'select' ? 'Choose an option  ▾' : '';
			previewField.appendChild(control);
		}
		previewFields.appendChild(previewField);
	}

	function updateCustomFieldOptions(row) {
		var type = row.querySelector('[data-bachs-custom-type]').value;
		row.querySelector('[data-bachs-custom-options-row]').hidden = type !== 'select';
	}

	function addCustomField(data) {
		if (customFieldContainer.querySelectorAll('[data-bachs-custom-field]').length >= 10) {
			return;
		}
		var row = customFieldTemplate.content.firstElementChild.cloneNode(true);
		row.querySelectorAll('[name]').forEach(function (input) {
			input.name = input.name.replace(/__index__/g, String(customFieldIndex));
		});
		row.querySelector('[data-bachs-custom-label]').value = data && data.label ? data.label : '';
		row.querySelector('[data-bachs-custom-type]').value = data && data.type ? data.type : 'text';
		row.querySelector('[name$="[key]"]').value = data && data.key ? data.key : '';
		row.querySelector('[name$="[required]"]').checked = Boolean(data && data.required);
		row.querySelector('[data-bachs-custom-options]').value = data && Array.isArray(data.options) ? data.options.join('\n') : '';
		customFieldContainer.appendChild(row);
		customFieldIndex += 1;
		updateCustomFieldOptions(row);
		builder.querySelector('[data-bachs-add-custom-field]').disabled = customFieldContainer.querySelectorAll('[data-bachs-custom-field]').length >= 10;
		updatePreview();
	}

	function clearCustomFields() {
		while (customFieldContainer.firstChild) {
			customFieldContainer.removeChild(customFieldContainer.firstChild);
		}
		customFieldIndex = 0;
		builder.querySelector('[data-bachs-add-custom-field]').disabled = false;
	}

	function applyData(data) {
		form.reset();
		clearCustomFields();
		setValue('form_id', data && data.id ? data.id : '0');
		setValue('form_title', data && data.title ? data.title : '');
		setValue('form_excerpt', data && data.excerpt ? data.excerpt : '');
		setValue('form_content', data && data.content ? data.content : '');
		setValue('featured_image_id', data && data.featured_image_id ? data.featured_image_id : '0');
		title.textContent = data ? 'Edit donation form' : 'Create a donation form';
		var publishButton = builder.querySelector('[data-bachs-publish-button]');
		if (BachsDonationBuilder.canPublish) {
			publishButton.textContent = data && data.status === 'publish' ? 'Update form' : 'Publish donation form';
			publishButton.value = 'publish';
		} else {
			publishButton.textContent = BachsDonationBuilder.reviewLabel;
			publishButton.value = 'pending';
		}

		if (data && data.settings) {
			var settings = data.settings;
			setChecked('bachs_form[currencies][]', settings.currencies || []);
			setValue('bachs_form[default_currency]', settings.default_currency);
			setValue('bachs_form[amounts][USD]', (settings.amounts.USD || []).join(', '));
			setValue('bachs_form[amounts][NGN]', (settings.amounts.NGN || []).join(', '));
			setChecked('bachs_form[payment_methods][USD][]', settings.payment_methods.USD || []);
			setChecked('bachs_form[payment_methods][NGN][]', settings.payment_methods.NGN || []);
			setChecked('bachs_form[custom_amount]', settings.custom_amount);
			setValue('bachs_form[goal_amount]', settings.goal_amount);
			setValue('bachs_form[goal_currency]', settings.goal_currency);
			setChecked('bachs_form[donor_name]', settings.donor_name);
			setChecked('bachs_form[donor_name_required]', settings.donor_name_required);
			setChecked('bachs_form[donor_phone]', settings.donor_phone);
			setChecked('bachs_form[donor_phone_required]', settings.donor_phone_required);
			(settings.custom_fields || []).forEach(addCustomField);
			setValue('bachs_form[accent_color]', settings.accent_color);
			setValue('bachs_form[form_style]', settings.form_style);
			setValue('bachs_form[corner_style]', settings.corner_style);
			setValue('bachs_form[button_text]', settings.button_text);
			var goalDetails = builder.querySelector('.bachs-builder__advanced');
			goalDetails.open = Boolean(settings.goal_amount);
		} else {
			builder.querySelector('.bachs-builder__advanced').open = false;
		}
		updateImage(data && data.featured_image_url ? data.featured_image_url : '', data && data.featured_image_id ? data.featured_image_id : '0');
		updateCurrencyFields();
		if (window.jQuery && window.jQuery.fn.wpColorPicker) {
			window.jQuery(field('bachs_form[accent_color]')).wpColorPicker('color', field('bachs_form[accent_color]').value);
		}
		updatePreview();
	}

	function openModal(data) {
		previousFocus = document.activeElement;
		applyData(data || null);
		builder.hidden = false;
		document.body.classList.add('bachs-builder-open');
		window.requestAnimationFrame(function () {
			if (titleInput) {
				titleInput.focus();
			}
		});
	}

	function closeModal() {
		builder.hidden = true;
		document.body.classList.remove('bachs-builder-open');
		if (previousFocus && typeof previousFocus.focus === 'function') {
			previousFocus.focus();
		}
	}

	document.querySelectorAll('[data-bachs-open-modal]').forEach(function (button) {
		button.addEventListener('click', function () {
			openModal(null);
		});
	});

	document.querySelectorAll('[data-bachs-edit-form]').forEach(function (button) {
		button.addEventListener('click', function () {
			try {
				openModal(JSON.parse(button.getAttribute('data-bachs-edit-form')));
			} catch (error) {
				window.alert('This form could not be opened. Refresh the page and try again.');
			}
		});
	});

	builder.querySelectorAll('[data-bachs-close-modal]').forEach(function (button) {
		button.addEventListener('click', closeModal);
	});

	document.addEventListener('keydown', function (event) {
		if (builder.hidden) {
			return;
		}
		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal();
		} else if (event.key === 'Tab') {
			var focusable = Array.prototype.slice.call(dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [href]')).filter(function (element) {
				return element.getClientRects().length > 0;
			});
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});

	form.addEventListener('change', function () {
		updateCurrencyFields();
		customFieldContainer.querySelectorAll('[data-bachs-custom-field]').forEach(updateCustomFieldOptions);
		updatePreview();
	});
	form.addEventListener('input', updatePreview);
	customFieldContainer.addEventListener('click', function (event) {
		var removeButton = event.target.closest('[data-bachs-remove-custom-field]');
		if (removeButton) {
			removeButton.closest('[data-bachs-custom-field]').remove();
			builder.querySelector('[data-bachs-add-custom-field]').disabled = false;
			updatePreview();
		}
	});
	builder.querySelector('[data-bachs-add-custom-field]').addEventListener('click', function () {
		addCustomField(null);
	});
	form.addEventListener('submit', function (event) {
		var selectedCurrencies = checkedValues('bachs_form[currencies][]');
		var error = builder.querySelector('[data-bachs-builder-error]');
		var missingMethod = selectedCurrencies.some(function (currency) {
			return checkedValues('bachs_form[payment_methods][' + currency + '][]').length === 0;
		});
		var invalidDropdown = Array.prototype.slice.call(customFieldContainer.querySelectorAll('[data-bachs-custom-field]')).some(function (customField) {
			var type = customField.querySelector('[data-bachs-custom-type]').value;
			var options = customField.querySelector('[data-bachs-custom-options]').value.split(/[\r\n]+/).map(function (option) { return option.trim(); }).filter(Boolean);
			return type === 'select' && options.length < 2;
		});
		if (!selectedCurrencies.length || missingMethod || invalidDropdown) {
			event.preventDefault();
			error.textContent = !selectedCurrencies.length ? 'Choose at least one currency for this form.' : (missingMethod ? 'Choose at least one payment method for each selected currency.' : 'Add at least two choices to every dropdown field.');
			error.hidden = false;
			error.focus();
		} else {
			error.hidden = true;
		}
	});
	form.querySelector('[data-bachs-choose-image]').addEventListener('click', function () {
		if (!window.wp || !window.wp.media) {
			return;
		}
		var picker = window.wp.media({ title: BachsDonationBuilder.chooseImage, button: { text: BachsDonationBuilder.useImage }, library: { type: 'image' }, multiple: false });
		picker.on('select', function () {
			var image = picker.state().get('selection').first().toJSON();
			updateImage(image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url, image.id);
		});
		picker.open();
	});
	removeImageButton.addEventListener('click', function () {
		updateImage('', '0');
	});

	if (window.jQuery && window.jQuery.fn.wpColorPicker) {
		window.jQuery('.bachs-builder__color').wpColorPicker({
			change: function (event, ui) {
				window.setTimeout(function () {
					field('bachs_form[accent_color]').value = ui.color.toString();
					updatePreview();
				}, 0);
			}
		});
	}

	updateCurrencyFields();
	updatePreview();
}());
