(function () {
	'use strict';

	var HEADER_STYLE = 'background-color:#f3f3f3!important;color:#111!important;';
	var HEADER_HOVER = 'background-color:#e9e9e9!important;color:#111!important;';

	function getCellValue(row, index) {
		var cell = row.children[index];
		if (!cell) {
			return '';
		}
		if (cell.hasAttribute('data-sort-value')) {
			return cell.getAttribute('data-sort-value') || '';
		}
		return (cell.textContent || '').trim();
	}

	function compareValues(a, b, type, direction) {
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

	function clearSortClasses(header) {
		header.classList.remove('dm-th-sort-asc', 'dm-th-sort-desc');
	}

	function sortTable(table, header) {
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
			clearSortClasses(th);
			th.setAttribute('style', HEADER_STYLE);
			th.classList.remove('dm-th-sort-hover');
		});

		header.classList.add(direction === 'asc' ? 'dm-th-sort-asc' : 'dm-th-sort-desc');
		header.setAttribute('style', HEADER_STYLE);

		var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
		rows.sort(function (rowA, rowB) {
			return compareValues(
				getCellValue(rowA, index),
				getCellValue(rowB, index),
				type,
				direction
			);
		});

		rows.forEach(function (row) {
			tbody.appendChild(row);
		});
	}

	function bindHeader(table, header) {
		header.addEventListener('click', function (e) {
			e.preventDefault();
			sortTable(table, header);
		});

		header.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				sortTable(table, header);
			}
		});

		header.addEventListener('mouseenter', function () {
			header.setAttribute('style', HEADER_HOVER);
			header.classList.add('dm-th-sort-hover');
		});

		header.addEventListener('mouseleave', function () {
			header.setAttribute('style', HEADER_STYLE);
			header.classList.remove('dm-th-sort-hover');
		});
	}

	function initTable(table) {
		table.querySelectorAll('th.dm-th-sort').forEach(function (header) {
			header.setAttribute('style', HEADER_STYLE);
			bindHeader(table, header);
		});
	}

	function init() {
		document.querySelectorAll('table.dm-domain-list-table[data-dm-sortable]').forEach(initTable);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
