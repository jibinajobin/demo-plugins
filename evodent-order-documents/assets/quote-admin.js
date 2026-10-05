jQuery(function($) {

	let productIndex = 1;
	let intlProductIndex = 1;
	let searchTimer = null;
	let searchRequest = null;
	// let paymentReminderShown = false; // tracks whether we're currently above the threshold

	

	/* ======================================================
	   PRODUCT SEARCH
	====================================================== */

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function positionSearchResults(field, results) {
		const offset = field.offset();
		const height = field.outerHeight();
		const width = Math.max(field.outerWidth(), 280);

		results.css({
			'position': 'absolute',
			'top': (offset.top + height + 2) + 'px',
			'left': offset.left + 'px',
			'width': width + 'px',
			'z-index': 999999
		});
	}

	function searchQuoteProducts(input) {
		const field = $(input);
		const wrap = field.closest('.evodent-product-search-wrap');
		let results = field.data('results-el');

		if (!results || !results.length) {
			results = wrap.find('.evodent-product-search-results');
			if (results.length) {
				results.appendTo('body');
			} else {
				results = $('<div class="evodent-product-search-results"></div>').appendTo('body');
			}
			field.data('results-el', results);
		}

		results.data('activeInput', field);
		const term = $.trim(field.val());

		if (term.length < 2) {
			results.empty().hide();
			return;
		}

		clearTimeout(searchTimer);

		searchTimer = setTimeout(function() {
			positionSearchResults(field, results);
			results.html('<div class="evodent-product-search-loading">Searching products...</div>').show();

			if (searchRequest) {
				searchRequest.abort();
			}

			const selectedCountryOpt = $('#intl_customer_country option:selected');
			const countryCode = selectedCountryOpt.attr('data-code') || $('#intl_customer_country').val() || '';
			const currencyCode = $('#intl_quote_currency').val() || 'USD';

			searchRequest = $.ajax({
				url: window.EvodentQuote.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'evodent_search_quote_products',
					nonce: window.EvodentQuote.nonce,
					search: term,
					country: countryCode,
					currency: currencyCode
				}
			});

			searchRequest.done(function(response) {
				if (!response || !response.success) {
					results.html('<div class="evodent-product-search-empty">Unable to search products.</div>').show();
					return;
				}

				const products = response.data.products || [];

				if (!products.length) {
					results.html('<div class="evodent-product-search-empty">No products found.</div>').show();
					return;
				}

				let html = '';

				$.each(products, function(index, product) {
					const metaParts = [];

					if (product.sku) {
						metaParts.push('SKU: ' + escapeHtml(product.sku));
					}

					if (product.hsn) {
						metaParts.push('HSN: ' + escapeHtml(product.hsn));
					}

					const intlPrice = (product.intl_unit_price !== undefined) ? product.intl_unit_price : (product.rate_inc_gst || 0);

					html +=
						'<button type="button" class="evodent-product-search-result"' +
						' data-id="' + escapeHtml(product.id) + '"' +
						' data-name="' + escapeHtml(product.name) + '"' +
						' data-sku="' + escapeHtml(product.sku || '') + '"' +
						' data-hsn="' + escapeHtml(product.hsn || '') + '"' +
						' data-rate="' + escapeHtml(product.rate_inc_gst || product.rate_ex_gst || 0) + '"' +
						' data-intl-rate="' + escapeHtml(intlPrice) + '">' +
						'<span class="evodent-product-search-result-name">' + escapeHtml(product.name) + '</span>' +
						(metaParts.length ? '<span class="evodent-product-search-result-meta">' + metaParts.join(' &nbsp; | &nbsp; ') + '</span>' : '') +
						'</button>';
				});

				positionSearchResults(field, results);
				results.html(html).show();
			});

			searchRequest.fail(function(xhr, status) {
				if (status !== 'abort') {
					results.html('<div class="evodent-product-search-empty">Unable to search products.</div>').show();
				}
			});
		}, 300);
	}

	$(document).on('input', '.evodent-product-search', function() {
		$(this).closest('tr').find('.evodent-product-id').val('');
		searchQuoteProducts(this);
	});

	$(document).on('click', '.evodent-product-search-result', function(e) {
		e.preventDefault();
		const result = $(this);
		const resultsContainer = result.closest('.evodent-product-search-results');
		const field = resultsContainer.data('activeInput');

		if (field && field.length) {
			const row = field.closest('tr');

			if (row.hasClass('evodent-intl-product-row')) {
				row.find('.evodent-product-search').val(result.attr('data-name') || '');
				row.find('.evodent-product-id').val(result.attr('data-id') || '');
				row.find('.evodent-sku').val(result.attr('data-sku') || '');
				row.find('.evodent-hsn').val(result.attr('data-hsn') || '90230090');
				const intlUnitRate = parseFloat(result.attr('data-intl-rate')) || parseFloat(result.attr('data-rate')) || 0;
				row.find('.evodent-intl-unit-price').val(intlUnitRate.toFixed(2));
				calculateIntlTotals();
			} else {
				row.find('.evodent-product-search').val(result.attr('data-name') || '');
				row.find('.evodent-product-id').val(result.attr('data-id') || '');
				row.find('.evodent-sku').val(result.attr('data-sku') || '');
				row.find('.evodent-hsn').val(result.attr('data-hsn') || '');
				row.find('.evodent-rate-inc-gst').val((parseFloat(result.attr('data-rate')) || 0).toFixed(2));
				calculateTotals();
			}
		}

		resultsContainer.empty().hide();
	});

	$(document).on('click', function(e) {
		if (!$(e.target).closest('.evodent-product-search, .evodent-product-search-results').length) {
			$('.evodent-product-search-results').empty().hide();
		}
	});

	$(window).add('.evodent-product-table-wrapper').on('scroll resize', function() {
		$('.evodent-product-search-results:visible').each(function() {
			const res = $(this);
			const field = res.data('activeInput');

			if (field && field.length && field.is(':visible')) {
				positionSearchResults(field, res);
			} else {
				res.hide();
			}
		});
	});

	/* ======================================================
	   QUOTE TYPE SWITCH
	====================================================== */

	$('input[name="quote_type"]').on('change', function() {
		const quoteType = $(this).val();

		if (quoteType === 'domestic') {
			$('#evodent-domestic-content').show();
			$('#evodent-international-content').hide();
		} else {
			$('#evodent-domestic-content').hide();
			$('#evodent-international-content').show();
		}
	});

	/* ======================================================
	   INTERNATIONAL COUNTRY TO CURRENCY MAPPING & DYNAMIC PRICING
	====================================================== */

	// FALLBACK ONLY. The real source of truth is window.EvodentQuote.wcpbcZones,
	// which PHP should build from the live WCPBC Pro zone settings (see #intl_customer_country
	// change handler below). This map is a safety net in case that localization is missing/stale,
	// kept in sync with the zones configured in WooCommerce > Settings > Pricing Zones as of Aug 2026.
	// Keyed by ISO 3166-1 alpha-2 country code (matches the `data-code` attribute on each <option>).
	const countryCurrencyMapByCode = {
		// Single-country top-level zones
		'AU': 'AUD', 'AE': 'AED', 'GB': 'GBP', 'IN': 'INR', 'US': 'USD',

		// Euro Zone
		'AX': 'EUR', 'AD': 'EUR', 'AT': 'EUR', 'BE': 'EUR', 'BG': 'EUR', 'HR': 'EUR',
		'CY': 'EUR', 'EE': 'EUR', 'FI': 'EUR', 'FR': 'EUR', 'GF': 'EUR', 'TF': 'EUR',
		'DE': 'EUR', 'GR': 'EUR', 'GP': 'EUR', 'IE': 'EUR', 'IT': 'EUR', 'LV': 'EUR',
		'LT': 'EUR', 'LU': 'EUR', 'MT': 'EUR', 'MQ': 'EUR', 'YT': 'EUR', 'NL': 'EUR',
		'PT': 'EUR', 'RE': 'EUR', 'BL': 'EUR', 'MF': 'EUR', 'SM': 'EUR', 'SK': 'EUR',
		'SI': 'EUR', 'ES': 'EUR', 'VA': 'EUR',

		// Single-country zones with their own currency
		'BH': 'BHD', 'BD': 'BDT', 'BR': 'BRL', 'CA': 'CAD', 'CL': 'CLP', 'CO': 'COP',
		'CZ': 'CZK', 'DK': 'DKK', 'EG': 'EGP', 'GE': 'GEL', 'HU': 'HUF', 'IS': 'ISK',
		'IL': 'ILS', 'ID': 'IDR', 'JP': 'JPY', 'JO': 'JOD', 'KW': 'KWD', 'MY': 'MYR',
		'MX': 'MXN', 'NZ': 'NZD', 'NO': 'NOK', 'OM': 'OMR', 'PE': 'PEN', 'PH': 'PHP',
		'PL': 'PLN', 'RO': 'RON', 'SA': 'SAR', 'QA': 'QAR', 'RU': 'RUB', 'SG': 'SGD',
		'ZA': 'ZAR', 'KR': 'KRW', 'LK': 'LKR', 'SE': 'SEK', 'CH': 'CHF', 'TH': 'THB',
		'TR': 'TRY', 'VN': 'VND'

		// Everything else falls into the "Rest of the World" zone => USD (handled as the
		// default below). If WCPBC's "default pricing" ever needs to differ, add it here.
	};

	// Same map keyed by full country name, for cases where the <option> value is the
	// country name rather than the ISO code (kept for backward compatibility with markup
	// that doesn't set data-code).
	const countryCurrencyMap = {
		'Australia': 'AUD', 'United Arab Emirates': 'AED', 'United Kingdom': 'GBP',
		'United Kingdom (UK)': 'GBP', 'India': 'INR', 'United States': 'USD',
		'United States (US)': 'USD',

		'Åland Islands': 'EUR', 'Andorra': 'EUR', 'Austria': 'EUR', 'Belgium': 'EUR',
		'Bulgaria': 'EUR', 'Croatia': 'EUR', 'Cyprus': 'EUR', 'Estonia': 'EUR',
		'Finland': 'EUR', 'France': 'EUR', 'French Guiana': 'EUR',
		'French Southern Territories': 'EUR', 'Germany': 'EUR', 'Greece': 'EUR',
		'Guadeloupe': 'EUR', 'Ireland': 'EUR', 'Italy': 'EUR', 'Latvia': 'EUR',
		'Lithuania': 'EUR', 'Luxembourg': 'EUR', 'Malta': 'EUR', 'Martinique': 'EUR',
		'Mayotte': 'EUR', 'Netherlands': 'EUR', 'Portugal': 'EUR', 'Reunion': 'EUR',
		'Réunion': 'EUR', 'Saint Barthélemy': 'EUR', 'Saint Martin (French part)': 'EUR',
		'San Marino': 'EUR', 'Slovakia': 'EUR', 'Slovenia': 'EUR', 'Spain': 'EUR',
		'Vatican': 'EUR', 'Vatican City': 'EUR', 'Holy See (Vatican City State)': 'EUR',

		'Bahrain': 'BHD', 'Bangladesh': 'BDT', 'Brazil': 'BRL', 'Canada': 'CAD',
		'Chile': 'CLP', 'Colombia': 'COP', 'Czech Republic': 'CZK', 'Czechia': 'CZK',
		'Denmark': 'DKK', 'Egypt': 'EGP', 'Georgia': 'GEL', 'Hungary': 'HUF',
		'Iceland': 'ISK', 'Israel': 'ILS', 'Indonesia': 'IDR', 'Japan': 'JPY',
		'Jordan': 'JOD', 'Kuwait': 'KWD', 'Malaysia': 'MYR', 'Mexico': 'MXN',
		'New Zealand': 'NZD', 'Norway': 'NOK', 'Oman': 'OMR', 'Peru': 'PEN',
		'Philippines': 'PHP', 'Poland': 'PLN', 'Romania': 'RON', 'Saudi Arabia': 'SAR',
		'Qatar': 'QAR', 'Russia': 'RUB', 'Singapore': 'SGD', 'South Africa': 'ZAR',
		'South Korea': 'KRW', 'Sri Lanka': 'LKR', 'Sweden': 'SEK', 'Switzerland': 'CHF',
		'Thailand': 'THB', 'Turkey': 'TRY', 'Türkiye': 'TRY', 'Vietnam': 'VND'
	};

	const currencySymbolMap = $.extend({
		'USD': '$', 'EUR': '€', 'GBP': '£', 'AUD': 'A$', 'SGD': 'S$', 'CAD': 'C$',
		'AED': 'AED', 'JPY': '¥', 'SAR': 'SAR', 'QAR': 'QAR', 'MYR': 'RM',
		'NZD': 'NZ$', 'CHF': 'CHF', 'INR': '₹', 'BRL': 'R$', 'CNY': '¥',
		'HKD': 'HK$', 'ILS': '₪', 'KRW': '₩', 'MXN': 'Mex$', 'NOK': 'kr',
		'PLN': 'zł', 'RUB': '₽', 'SEK': 'kr', 'THB': '฿', 'TRY': '₺', 'ZAR': 'R'
	}, (window.EvodentQuote && window.EvodentQuote.currencySymbols) ? window.EvodentQuote.currencySymbols : {});

	function refreshIntlProductPrices() {
		const productIds = [];
		$('#evodent-intl-product-rows .evodent-intl-product-row').each(function() {
			const id = $(this).find('.evodent-product-id').val();
			if (id) {
				productIds.push(id);
			}
		});

		if (!productIds.length) {
			return;
		}

		const selectedCountryOpt = $('#intl_customer_country option:selected');
		const countryCode = selectedCountryOpt.attr('data-code') || $('#intl_customer_country').val() || '';
		const currencyCode = $('#intl_quote_currency').val() || 'USD';

		$.ajax({
			url: window.EvodentQuote.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'evodent_get_product_prices_for_country',
				nonce: window.EvodentQuote.nonce,
				country: countryCode,
				currency: currencyCode,
				product_ids: productIds
			}
		}).done(function(response) {
			if (response && response.success && response.data.prices) {
				const prices = response.data.prices;
				$('#evodent-intl-product-rows .evodent-intl-product-row').each(function() {
					const row = $(this);
					const id = row.find('.evodent-product-id').val();
					if (id && prices[id] !== undefined) {
						row.find('.evodent-intl-unit-price').val(parseFloat(prices[id]).toFixed(2));
					}
				});
				calculateIntlTotals();
			}
		});
	}

	// $('#intl_customer_country').on('change', function() {
	// 	const selectedOpt = $(this).find('option:selected');
	// 	const countryCode = (selectedOpt.attr('data-code') || $(this).val() || '').toUpperCase();
	// 	const countryName = selectedOpt.text() || $(this).val() || '';
	// 	const wcpbcZones = (window.EvodentQuote && window.EvodentQuote.wcpbcZones) ? window.EvodentQuote.wcpbcZones : {};

	// 	let targetCurrency = 'USD'; // Rest of the World zone defaults to USD

	// 	if (countryCode && wcpbcZones[countryCode]) {
	// 		// 1. Trust the live zone data localized from WCPBC Pro's actual settings (PHP side).
	// 		targetCurrency = wcpbcZones[countryCode];
	// 	} else if (countryCode && countryCurrencyMapByCode[countryCode]) {
	// 		// 2. Fall back to this file's code-keyed map if server data is missing/stale.
	// 		targetCurrency = countryCurrencyMapByCode[countryCode];
	// 	} else if (countryCurrencyMap[countryName]) {
	// 		// 3. Last-resort match by displayed country name.
	// 		targetCurrency = countryCurrencyMap[countryName];
	// 	}

	// 	$('#intl_quote_currency').val(targetCurrency).trigger('change');
	// });

	$('#intl_customer_country').on('change', function() {

	const selectedOpt = $(this).find('option:selected');

	const countryCode = (
		selectedOpt.attr('data-code') ||
		$(this).val() ||
		''
	).toUpperCase();

	const countryName = (
		selectedOpt.text() ||
		$(this).val() ||
		''
	).trim();

	const wcpbcZones = (
		window.EvodentQuote &&
		window.EvodentQuote.wcpbcZones
	)
		? window.EvodentQuote.wcpbcZones
		: {};

	/*
	 * Find the selected country's LOCAL currency.
	 *
	 * Priority:
	 * 1. Live WCPBC zone data
	 * 2. Country code fallback map
	 * 3. Country name fallback map
	 */
	let localCurrency = 'USD';

	if (countryCode && wcpbcZones[countryCode]) {

		localCurrency = wcpbcZones[countryCode];

	} else if (
		countryCode &&
		countryCurrencyMapByCode[countryCode]
	) {

		localCurrency = countryCurrencyMapByCode[countryCode];

	} else if (countryCurrencyMap[countryName]) {

		localCurrency = countryCurrencyMap[countryName];
	}


	/*
	 * Rebuild the currency dropdown.
	 *
	 * Always show:
	 * 1. Local currency
	 * 2. USD
	 * 3. EUR
	 *
	 * But don't show duplicates.
	 */
	const currencySelect = $('#intl_quote_currency');

	currencySelect.empty();


	/*
	 * LOCAL CURRENCY
	 *
	 * This is selected by default.
	 */
	if (localCurrency) {

		let localLabel = localCurrency;

		if (localCurrency === 'USD') {

			localLabel = 'USD (USA)';

		} else if (localCurrency === 'EUR') {

			localLabel = 'EUR (Eurozone)';

		} else {

			localLabel =
				localCurrency +
				' (' +
				countryName +
				')';
		}

		currencySelect.append(
			$('<option>', {
				value: localCurrency,
				text: localLabel,
				selected: true
			})
		);
	}


	/*
	 * USD
	 *
	 * Don't add it if the local currency is already USD.
	 */
	if (localCurrency !== 'USD') {

		currencySelect.append(
			$('<option>', {
				value: 'USD',
				text: 'USD (USA)'
			})
		);
	}


	/*
	 * EUR
	 *
	 * Don't add it if the local currency is already EUR.
	 */
	if (localCurrency !== 'EUR') {

		currencySelect.append(
			$('<option>', {
				value: 'EUR',
				text: 'EUR (Eurozone)'
			})
		);
	}


	/*
	 * Make sure local currency is selected.
	 */
	currencySelect
		.val(localCurrency)
		.trigger('change');

});

	$('#intl_quote_currency').on('change', function() {
		const curr = $(this).val() || 'USD';
		const symbol = currencySymbolMap[curr] || curr;
		$('.evodent-intl-curr-code').text(curr);
		$('.evodent-intl-curr-symbol').text(symbol);
		refreshIntlProductPrices();
	});

	/* ======================================================
	   ADD PRODUCT (DOMESTIC)
	====================================================== */

	$('#evodent-add-product').on('click', function() {
		const row = $('.evodent-product-row:first').clone();
		row.find('.evodent-product-search-results').empty().hide();

		row.find('input').each(function() {
			const name = $(this).attr('name');
			if (name) {
				$(this).attr('name', name.replace(/products\[0\]/, 'products[' + productIndex + ']'));
			}

			if ($(this).hasClass('evodent-quantity')) {
				$(this).val(1);
			} else if ($(this).hasClass('evodent-gst')) {
				$(this).val(18);
			} else if ($(this).hasClass('evodent-rate-ex-gst')) {
				$(this).val(0);
			} else if ($(this).hasClass('evodent-rate-inc-gst') || $(this).hasClass('evodent-item-total')) {
				$(this).val('0.00');
			} else {
				$(this).val('');
			}
		});

		row.find('.evodent-serial').text($('#evodent-product-rows .evodent-product-row').length + 1);
		$('#evodent-product-rows').append(row);
		productIndex++;
		calculateTotals();
	});

	/* ======================================================
	   ADD PRODUCT (INTERNATIONAL)
	====================================================== */

	$('#evodent-intl-add-product').on('click', function() {
		const row = $('.evodent-intl-product-row:first').clone();
		row.find('.evodent-product-search-results').empty().hide();

		row.find('input').each(function() {
			const name = $(this).attr('name');
			if (name) {
				$(this).attr('name', name.replace(/intl_products\[0\]/, 'intl_products[' + intlProductIndex + ']'));
			}

			if ($(this).hasClass('evodent-brand')) {
				$(this).val('EVODENT');
			} else if ($(this).hasClass('evodent-hsn')) {
				$(this).val('90230090');
			} else if ($(this).hasClass('evodent-intl-quantity')) {
				$(this).val(1);
			} else if ($(this).hasClass('evodent-intl-unit-price') || $(this).hasClass('evodent-intl-item-total')) {
				$(this).val('0.00');
			} else {
				$(this).val('');
			}
		});

		row.find('.evodent-serial').text($('#evodent-intl-product-rows .evodent-intl-product-row').length + 1);
		$('#evodent-intl-product-rows').append(row);
		intlProductIndex++;
		calculateIntlTotals();
	});

	/* ======================================================
	   REMOVE PRODUCT (DOMESTIC)
	====================================================== */

	$(document).on('click', '.evodent-remove-product', function() {
		if ($('#evodent-product-rows .evodent-product-row').length <= 1) {
			return;
		}

		const row = $(this).closest('tr');
		const field = row.find('.evodent-product-search');
		const resultsEl = field.data('results-el');

		if (resultsEl) {
			resultsEl.remove();
		}

		row.remove();
		$('.evodent-product-search-results').empty().hide();
		updateSerialNumbers();
		calculateTotals();
	});

	/* ======================================================
	   REMOVE PRODUCT (INTERNATIONAL)
	====================================================== */

	$(document).on('click', '.evodent-intl-remove-product', function() {
		if ($('#evodent-intl-product-rows .evodent-intl-product-row').length <= 1) {
			return;
		}

		const row = $(this).closest('tr');
		const field = row.find('.evodent-product-search');
		const resultsEl = field.data('results-el');

		if (resultsEl) {
			resultsEl.remove();
		}

		row.remove();
		$('.evodent-product-search-results').empty().hide();
		updateIntlSerialNumbers();
		calculateIntlTotals();
	});

	/* ======================================================
	   CALCULATE WHEN VALUES CHANGE (DOMESTIC & INTERNATIONAL)
	====================================================== */

	$(document).on(
		'input change',
		'.evodent-quantity, .evodent-rate-inc-gst, .evodent-rate-ex-gst, .evodent-gst, #quote_discount, #quote_discount_type, #quote_freight',
		function() {
			calculateTotals();
		}
	);

	$(document).on(
		'input change',
		'.evodent-intl-quantity, .evodent-intl-unit-price, #intl_quote_discount, #intl_quote_discount_type, #intl_quote_freight, #intl_quote_tax_other',
		function() {
			calculateIntlTotals();
		}
	);$(document).on('change', '#intl_quote_freight_option', function() {

    const option = $(this).val();
    const amountField = $('#intl_quote_freight');

    if (option === 'tbc') {

        amountField.val('0').prop('disabled', true);
        $('.evodent-intl-freight-amount').hide();

    } else {

        amountField.prop('disabled', false);
        $('.evodent-intl-freight-amount').show();

    }

    calculateIntlTotals();
});
$('#intl_quote_freight_option').trigger('change');

	/* ======================================================
	   CALCULATE TOTALS (DOMESTIC)
	====================================================== */

	function calculateTotals() {
		let subtotal = 0;

		$('.evodent-product-row').each(function() {
			const row = $(this);
			const quantity = parseFloat(row.find('.evodent-quantity').val()) || 0;
			const rateIncGst = parseFloat(row.find('.evodent-rate-inc-gst').val()) || 0;
			const gst = parseFloat(row.find('.evodent-gst').val()) || 0;
			const rateExGst = gst > 0 ? (rateIncGst / (1 + (gst / 100))) : rateIncGst;
			const itemTotal = rateIncGst * quantity;

			row.find('.evodent-rate-ex-gst').val(rateExGst.toFixed(2));
			row.find('.evodent-item-total').val(itemTotal.toFixed(2));

			subtotal += itemTotal;
		});

		const discountValue = parseFloat($('#quote_discount').val()) || 0;
		const discountType = $('#quote_discount_type').val();
		let discount = 0;

		if ('percentage' === discountType) {
			discount = (subtotal * discountValue) / 100;
		} else {
			discount = discountValue;
		}

		discount = Math.min(discount, subtotal);
		const freight = parseFloat($('#quote_freight').val()) || 0;
		const grandTotal = subtotal - discount + freight;

		$('#evodent-subtotal').text(subtotal.toFixed(2));
		$('#evodent-grand-total').text(grandTotal.toFixed(2));
	}

	/* ======================================================
	   CALCULATE TOTALS (INTERNATIONAL)
	====================================================== */

	function calculateIntlTotals() {
		let subtotal = 0;

		$('#evodent-intl-product-rows .evodent-intl-product-row').each(function() {
			const row = $(this);
			const quantity = parseFloat(row.find('.evodent-intl-quantity').val()) || 0;
			const unitPrice = parseFloat(row.find('.evodent-intl-unit-price').val()) || 0;
			const itemTotal = quantity * unitPrice;

			row.find('.evodent-intl-item-total').val(itemTotal.toFixed(2));
			subtotal += itemTotal;
		});

		const discountValue = parseFloat($('#intl_quote_discount').val()) || 0;
		const discountType = $('#intl_quote_discount_type').val();
		let discount = 0;

		if ('percentage' === discountType) {
			discount = (subtotal * discountValue) / 100;
		} else {
			discount = discountValue;
		}

		discount = Math.min(discount, subtotal);
		// const freight = parseFloat($('#intl_quote_freight').val()) || 0;
		// const taxOther = parseFloat($('#intl_quote_tax_other').val()) || 0;
		// const grandTotal = subtotal - discount + freight + taxOther;
		const freightOption = $('#intl_quote_freight_option').val() || 'amount';

const freight = freightOption === 'tbc'
    ? 0
    : (parseFloat($('#intl_quote_freight').val()) || 0);

const taxOther = parseFloat($('#intl_quote_tax_other').val()) || 0;

const grandTotal = subtotal - discount + freight + taxOther;

		$('#evodent-intl-subtotal').text(subtotal.toFixed(2));
		$('#evodent-intl-grand-total').text(grandTotal.toFixed(2));

		const currencyCode = $('#intl_quote_currency').val() || 'USD';
		// checkIntlPaymentLimit(grandTotal, currencyCode);
	}

	/* ======================================================
	   UPDATE SERIAL NUMBERS
	====================================================== */

	function updateSerialNumbers() {
		$('.evodent-product-row').each(function(index) {
			$(this).find('.evodent-serial').text(index + 1);
		});
	}

	function updateIntlSerialNumbers() {
		$('#evodent-intl-product-rows .evodent-intl-product-row').each(function(index) {
			$(this).find('.evodent-serial').text(index + 1);
		});
	}

	/* ======================================================
	   INITIAL CALCULATION
	====================================================== */

	calculateTotals();
	calculateIntlTotals();
	/*
	 * Initialize international currency
	 * based on the currently selected country.
	 */
	if ($('#intl_customer_country').val()) {
		$('#intl_customer_country').trigger('change');
	}

});