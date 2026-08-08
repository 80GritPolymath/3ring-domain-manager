(function ($) {
	'use strict';

	$(document).on('click', '.dm-confirm-delete', function (e) {
		if (!window.confirm('Permanently delete this item? This cannot be undone.')) {
			e.preventDefault();
		}
	});

	/**
	 * Auto-dismiss success notices after 4 seconds on Domain Manager screens.
	 */
	function autoDismissSuccessNotices() {
		var $notices = $('body.dm-page .notice-success, body.dm-page .notice.updated, .dm-wrap .notice-success, .dm-wrap .notice.updated');

		if (!$notices.length) {
			return;
		}

		window.setTimeout(function () {
			$notices.stop(true, true).fadeOut(400, function () {
				$(this).remove();
			});
		}, 4000);
	}

	$(autoDismissSuccessNotices);

	function addDnsRecordRow() {
		var template = document.getElementById('dm-dns-record-row-template');
		var table = document.getElementById('dm-dns-records-table');
		if (!template || !table || !template.content) {
			return;
		}

		var tbody = table.querySelector('tbody');
		if (!tbody) {
			return;
		}

		tbody.appendChild(template.content.cloneNode(true));
	}

	$(document).on('click', '#dm-dns-records-add', function (e) {
		e.preventDefault();
		addDnsRecordRow();
	});

	$(document).on('click', '.dm-dns-records__remove', function (e) {
		e.preventDefault();
		var $rows = $('#dm-dns-records-table tbody .dm-dns-records__row');
		if ($rows.length <= 1) {
			$(this).closest('tr').find('input[type="text"], input[type="number"]').val('');
			$(this).closest('tr').find('input[name="dns_record_name[]"]').val('@');
			$(this).closest('tr').find('input[name="dns_record_ttl[]"]').val('3600');
			$(this).closest('tr').find('select[name="dns_record_type[]"]').val('A');
			return;
		}
		$(this).closest('tr').remove();
	});

	/**
	 * Client-side sorting for Domains list tables (Dashboard + Domains).
	 * Avoids full page reloads from WP_List_Table orderby links.
	 */
	function getCellSortValue(row, index) {
		var cell = row.children[index];
		if (!cell) {
			return '';
		}
		if (cell.hasAttribute('data-sort-value')) {
			return cell.getAttribute('data-sort-value') || '';
		}
		return (cell.textContent || '').trim();
	}

	function compareSortValues(a, b, type, direction) {
		var left = a;
		var right = b;

		if (type === 'date') {
			left = left ? Date.parse(left) : 0;
			right = right ? Date.parse(right) : 0;
			if (isNaN(left)) {
				left = 0;
			}
			if (isNaN(right)) {
				right = 0;
			}
		} else {
			left = String(left).toLowerCase();
			right = String(right).toLowerCase();
		}

		if (left < right) {
			return direction === 'asc' ? -1 : 1;
		}
		if (left > right) {
			return direction === 'asc' ? 1 : -1;
		}
		return 0;
	}

	function sortDomainsTable(table, header) {
		var tbody = table.tBodies[0];
		if (!tbody) {
			return;
		}

		var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
		var index = headers.indexOf(header);
		if (index < 0) {
			return;
		}

		var type = header.getAttribute('data-sort-type') || 'text';
		var direction = header.classList.contains('dm-th-sort-asc') ? 'desc' : 'asc';

		headers.forEach(function (th) {
			th.classList.remove('dm-th-sort-asc', 'dm-th-sort-desc');
			th.setAttribute('aria-sort', 'none');
		});

		header.classList.add(direction === 'asc' ? 'dm-th-sort-asc' : 'dm-th-sort-desc');
		header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');

		var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
		rows.sort(function (rowA, rowB) {
			return compareSortValues(
				getCellSortValue(rowA, index),
				getCellSortValue(rowB, index),
				type,
				direction
			);
		});

		rows.forEach(function (row) {
			tbody.appendChild(row);
		});
	}

	function bindDomainsTableSort(table) {
		table.querySelectorAll('thead th.dm-th-sort').forEach(function (header) {
			header.addEventListener('click', function (e) {
				e.preventDefault();
				sortDomainsTable(table, header);
			});
			header.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					sortDomainsTable(table, header);
				}
			});
		});
	}

	function initDomainsTableSort() {
		document.querySelectorAll('table.dm-domains-table').forEach(bindDomainsTableSort);
	}

	$(initDomainsTableSort);

	/**
	 * Settings: WP color picker + live CSS var preview on body.dm-page.
	 */
	function hexToRgb(hex) {
		hex = String(hex || '').replace('#', '');
		if (hex.length === 3) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}
		if (!/^[A-Fa-f0-9]{6}$/.test(hex)) {
			return null;
		}
		return [
			parseInt(hex.slice(0, 2), 16),
			parseInt(hex.slice(2, 4), 16),
			parseInt(hex.slice(4, 6), 16)
		];
	}

	function rgbToHex(rgb) {
		return '#' + rgb.map(function (channel) {
			var value = Math.max(0, Math.min(255, Math.round(channel)));
			return ('0' + value.toString(16)).slice(-2);
		}).join('').toUpperCase();
	}

	function mixWithWhite(rgb, whiteAmount) {
		var keep = 1 - whiteAmount;
		return [
			(rgb[0] * keep) + (255 * whiteAmount),
			(rgb[1] * keep) + (255 * whiteAmount),
			(rgb[2] * keep) + (255 * whiteAmount)
		];
	}

	function applyBrandPreview(hex) {
		var rgb = hexToRgb(hex);
		if (!rgb || !document.body || !document.body.classList.contains('dm-page')) {
			return;
		}

		var brand = '#' + String(hex).replace('#', '').toUpperCase();
		if (brand.length === 4) {
			brand = '#' + brand[1] + brand[1] + brand[2] + brand[2] + brand[3] + brand[3];
		}

		var style = document.body.style;
		style.setProperty('--dm-brand', brand);
		style.setProperty('--dm-brand-dark', rgbToHex([rgb[0] * 0.8, rgb[1] * 0.8, rgb[2] * 0.8]));
		style.setProperty('--dm-brand-darker', rgbToHex([rgb[0] * 0.6, rgb[1] * 0.6, rgb[2] * 0.6]));
		style.setProperty('--dm-brand-tint', rgbToHex(mixWithWhite(rgb, 0.94)));
		style.setProperty('--dm-brand-tint-strong', rgbToHex(mixWithWhite(rgb, 0.88)));
		style.setProperty('--dm-brand-accent', rgbToHex(mixWithWhite(rgb, 0.22)));
		style.setProperty('--dm-brand-rgb', rgb[0] + ', ' + rgb[1] + ', ' + rgb[2]);
	}

	function initBrandColorPicker() {
		var $input = $('.dm-brand-color');
		if (!$input.length || typeof $.fn.wpColorPicker !== 'function') {
			return;
		}

		$input.wpColorPicker({
			change: function (event, ui) {
				if (ui && ui.color) {
					applyBrandPreview(ui.color.toString());
				}
			},
			clear: function () {
				var fallback = (window.dmBrand && dmBrand.defaultColor) ? dmBrand.defaultColor : '#3300FF';
				$input.val(fallback);
				applyBrandPreview(fallback);
			}
		});
	}

	$(initBrandColorPicker);

	/**
	 * Domain form: show Account#/ID + Management URL from the selected registrar provider.
	 */
	function syncRegistrarProviderFields() {
		var select = document.getElementById('registrar_id');
		var accountEl = document.getElementById('dm-registrar-account-id');
		var urlEl = document.getElementById('dm-registrar-management-url');

		if (!select || !accountEl || !urlEl) {
			return;
		}

		var option = select.options[select.selectedIndex];
		var accountId = option ? (option.getAttribute('data-account-id') || '') : '';
		var managementUrl = option ? (option.getAttribute('data-management-url') || '') : '';
		var emptyLabel = '—';

		accountEl.textContent = accountId || emptyLabel;
		accountEl.classList.toggle('dm-detail-value--muted', !accountId);

		urlEl.classList.toggle('dm-detail-value--muted', !managementUrl);
		if (managementUrl) {
			urlEl.innerHTML = '';
			var link = document.createElement('a');
			link.href = managementUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = managementUrl;
			urlEl.appendChild(link);
		} else {
			urlEl.textContent = emptyLabel;
		}
	}

	$(document).on('change', '#registrar_id', syncRegistrarProviderFields);
	$(syncRegistrarProviderFields);
})(jQuery);
