(function ($) {
	function getString(key, fallback) {
		if (window.IUAAdmin && IUAAdmin.i18n && IUAAdmin.i18n[key]) {
			return IUAAdmin.i18n[key];
		}
		return fallback;
	}

	function showNotice(type, text) {
		var $notice = $('<div class="notice is-dismissible"></div>').addClass('notice-' + type);
		$notice.append($('<p />').text(text));
		$('#iua-admin').prepend($notice);

		window.setTimeout(function () {
			$notice.fadeOut(200, function () {
				$(this).remove();
			});
		}, 3000);
	}

	function updateTabCounts(deltaUnused, deltaUsed) {
		function adjust($tab, delta) {
			var text = $tab.text();
			var match = text.match(/\((\d+)\)/);

			if (!match) {
				return;
			}

			var nextValue = parseInt(match[1], 10) + (delta || 0);
			if (nextValue < 0) {
				nextValue = 0;
			}

			$tab.text(text.replace(/\(\d+\)/, '(' + nextValue + ')'));
		}

		adjust($('.nav-tab[href*="iua_tab=unused"]'), deltaUnused || 0);
		adjust($('.nav-tab[href*="iua_tab=used"]'), deltaUsed || 0);
	}

	function removeRow(id) {
		$('#iua-row-' + id).fadeOut(120, function () {
			$(this).remove();
		});
	}

	function getSelected() {
		var ids = [];

		$('.iua-select:checked:visible').each(function () {
			var id = parseInt($(this).val(), 10);
			if (id) {
				ids.push(id);
			}
		});

		return ids;
	}

	function syncSelectAllState() {
		var $visible = $('.iua-select:visible');
		var $checked = $('.iua-select:visible:checked');
		var allChecked = $visible.length > 0 && $visible.length === $checked.length;

		$('.iua-select-all-toggle').prop('checked', allChecked);
	}

	function updateQuickCount() {
		var query = ($('#iua-quick-filter').val() || '').toString().toLowerCase().trim();
		var shown = $('.iua-row:visible').length;

		if (!query) {
			$('#iua-quick-count').text('');
			return;
		}

		$('#iua-quick-count').text(getString('shown_count', '%d shown').replace('%d', shown));
	}

	function applyQuickFilter() {
		var query = ($('#iua-quick-filter').val() || '').toString().toLowerCase().trim();

		$('.iua-row').each(function () {
			var haystack = ($(this).attr('data-iua-haystack') || '').toString();
			var matches = !query || haystack.indexOf(query) !== -1;
			$(this).toggle(matches);
		});

		syncSelectAllState();
		updateQuickCount();
	}

	$(document).on('click', '#iua-run-scan', function (event) {
		event.preventDefault();

		var $button = $(this);

		$button.prop('disabled', true).text(getString('scanning', 'Scanning…'));

		$.post(IUAAdmin.ajax_url, {
			action: 'iua_run_scan',
			nonce: IUAAdmin.nonces.run_scan
		})
			.done(function (response) {
				if (response && response.success) {
					window.location.reload();
					return;
				}

				showNotice('error', getString('scan_error', 'Scan error.'));
			})
			.fail(function () {
				showNotice('error', getString('scan_error', 'Scan error.'));
			})
			.always(function () {
				var label = IUAAdmin.last_scan ? getString('run_scan_again', 'Run scan again') : getString('run_scan', 'Run scan');
				$button.prop('disabled', false).text(label);
			});
	});

	$(document).on('click', '.iua-mark-used', function (event) {
		event.preventDefault();

		var id = parseInt($(this).data('id'), 10);
		if (!id) {
			return;
		}

		$.post(IUAAdmin.ajax_url, {
			action: 'iua_mark_manual_used',
			nonce: IUAAdmin.nonces.mark_manual,
			id: id
		})
			.done(function (response) {
				if (response && response.success) {
					removeRow(id);
					updateTabCounts(-1, 1);
					showNotice('success', getString('marked', 'Marked as used (manual).'));
					return;
				}

				showNotice('error', getString('error', 'An error occurred.'));
			})
			.fail(function () {
				showNotice('error', getString('error', 'An error occurred.'));
			});
	});

	$(document).on('click', '.iua-unmark-used', function (event) {
		event.preventDefault();

		var id = parseInt($(this).data('id'), 10);
		if (!id) {
			return;
		}

		$.post(IUAAdmin.ajax_url, {
			action: 'iua_unmark_manual_used',
			nonce: IUAAdmin.nonces.unmark_manual,
			id: id
		})
			.done(function (response) {
				if (response && response.success) {
					removeRow(id);
					updateTabCounts(1, -1);
					showNotice('success', getString('unmarked', 'Unmarked (manual).'));
					return;
				}

				showNotice('error', getString('error', 'An error occurred.'));
			})
			.fail(function () {
				showNotice('error', getString('error', 'An error occurred.'));
			});
	});

	$(document).on('click', '#iua-bulk-mark', function (event) {
		event.preventDefault();

		var ids = getSelected();

		if (!ids.length) {
			showNotice('warning', getString('none_selected', 'No items selected.'));
			return;
		}

		$.post(IUAAdmin.ajax_url, {
			action: 'iua_mark_manual_used_bulk',
			nonce: IUAAdmin.nonces.mark_manual_bulk,
			ids: ids
		})
			.done(function (response) {
				if (response && response.success) {
					ids.forEach(removeRow);
					updateTabCounts(-ids.length, ids.length);
					showNotice('success', getString('bulk_done', 'Bulk action completed.'));
					return;
				}

				showNotice('error', getString('error', 'An error occurred.'));
			})
			.fail(function () {
				showNotice('error', getString('error', 'An error occurred.'));
			});
	});

	$(document).on('click', '#iua-bulk-unmark', function (event) {
		event.preventDefault();

		var ids = getSelected();

		if (!ids.length) {
			showNotice('warning', getString('none_selected', 'No items selected.'));
			return;
		}

		$.post(IUAAdmin.ajax_url, {
			action: 'iua_unmark_manual_used_bulk',
			nonce: IUAAdmin.nonces.unmark_bulk,
			ids: ids
		})
			.done(function (response) {
				if (response && response.success) {
					ids.forEach(removeRow);
					updateTabCounts(ids.length, -ids.length);
					showNotice('success', getString('bulk_done', 'Bulk action completed.'));
					return;
				}

				showNotice('error', getString('error', 'An error occurred.'));
			})
			.fail(function () {
				showNotice('error', getString('error', 'An error occurred.'));
			});
	});

	$(document).on('click', '.iua-toggle-prov', function (event) {
		event.preventDefault();

		var $button = $(this);
		var $wrap = $button.closest('.iua-prov-wrap');
		var $more = $wrap.find('.iua-prov-more');

		if ($more.is(':visible')) {
			$more.slideUp(120);
			$button.text(getString('show_more', 'Show more'));
		} else {
			$more.slideDown(120);
			$button.text(getString('show_less', 'Show less'));
		}
	});

	var key = 'iua_columns_v1';
	var defaultColumns = {
		thumb: true,
		id: true,
		file: true,
		uploaded: true,
		provenance: true,
		count: true
	};

	function loadColumns() {
		try {
			var raw = window.localStorage.getItem(key);
			if (!raw) {
				return $.extend({}, defaultColumns);
			}

			return $.extend({}, defaultColumns, JSON.parse(raw));
		} catch (error) {
			return $.extend({}, defaultColumns);
		}
	}

	function saveColumns(columns) {
		try {
			window.localStorage.setItem(key, JSON.stringify(columns));
		} catch (error) {
			return;
		}
	}

	function applyColumns(columns) {
		$('th[data-col], td[data-col]').each(function () {
			var column = $(this).attr('data-col');
			$(this).toggle(columns[column] !== false);
		});
	}

	function mountPanel(columns) {
		$('#iua-col-thumb').prop('checked', !!columns.thumb);
		$('#iua-col-id').prop('checked', !!columns.id);
		$('#iua-col-file').prop('checked', !!columns.file);
		$('#iua-col-uploaded').prop('checked', !!columns.uploaded);
		$('#iua-col-provenance').prop('checked', !!columns.provenance);
		$('#iua-col-count').prop('checked', !!columns.count);
	}

	$(function () {
		var columns = loadColumns();
		applyColumns(columns);
		mountPanel(columns);
		syncSelectAllState();
		updateQuickCount();
	});

	$(document).on('click', '#iua-columns-toggle', function (event) {
		event.preventDefault();
		$('#iua-columns-panel').toggle();
	});

	$(document).on('click', function (event) {
		var $panel = $('#iua-columns-panel');

		if (!$panel.length) {
			return;
		}

		if (!$(event.target).closest('#iua-columns-panel, #iua-columns-toggle').length) {
			$panel.hide();
		}
	});

	$(document).on('change', '.iua-col-toggle', function () {
		var columns = loadColumns();
		var columnKey = $(this).data('col');

		columns[columnKey] = $(this).is(':checked');
		saveColumns(columns);
		applyColumns(columns);
	});

	$(document).on('click', '#iua-columns-reset', function (event) {
		event.preventDefault();

		var columns = $.extend({}, defaultColumns);
		saveColumns(columns);
		applyColumns(columns);
		mountPanel(columns);
	});

	$(document).on('change', '.iua-select-all-toggle', function () {
		var checked = $(this).is(':checked');

		$('.iua-select:visible').prop('checked', checked);
		$('.iua-select-all-toggle').prop('checked', checked);
	});

	$(document).on('change', '.iua-select', function () {
		syncSelectAllState();
	});

	$(document).on('input', '#iua-quick-filter', function () {
		applyQuickFilter();
	});

	$(document).on('click', '[data-iua-density]', function (event) {
		event.preventDefault();

		var mode = $(this).data('iuaDensity');
		var $root = $('#iua-admin');

		$('[data-iua-density]').removeClass('button-primary');

		if ('compact' === mode) {
			$root.addClass('iua-compact');
			$('[data-iua-density="compact"]').addClass('button-primary');
			return;
		}

		$root.removeClass('iua-compact');
		$('[data-iua-density="comfortable"]').addClass('button-primary');
	});
})(jQuery);
