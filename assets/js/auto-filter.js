/*
 * Release 4.9.0CW: immediate filtering for server-rendered GET filter forms.
 * Opt in with <form method="get" data-auto-filter>. select / checkbox / radio / date changes submit the form
 * once (changes within 80 ms are coalesced); free-text and number inputs submit after a 600 ms pause or on Enter.
 * A form that only submits its own fields drops any ?page=, so every filter change lands on page 1.
 * A control may carry data-auto-filter-clear="name1,name2" to blank dependent fields first (e.g. project -> product).
 * Delegated jQuery handlers are used so Select2's jQuery-triggered change events are seen.
 */
(function ($) {
	'use strict';
	var timers = new WeakMap();

	function validRange(form) {
		var d = $(form).find('input[type="date"]').filter(function () { return this.value; });
		return !(d.length === 2 && d[0].value > d[1].value);
	}

	function queue(form, delay) {
		clearTimeout(timers.get(form));
		timers.set(form, setTimeout(function () {
			if (validRange(form)) { form.submit(); }
		}, delay));
	}

	var TEXTUAL = 'input[type="text"], input[type="search"], input[type="number"], input:not([type])';

	$(document).on('change', 'form[data-auto-filter] select, form[data-auto-filter] input[type="checkbox"], form[data-auto-filter] input[type="radio"], form[data-auto-filter] input[type="date"], form[data-auto-filter] input[type="month"]', function () {
		var form = this.form || $(this).closest('form')[0];
		var clear = this.getAttribute('data-auto-filter-clear');
		if (clear) {
			clear.split(',').forEach(function (n) {
				$(form).find('[name="' + n.trim() + '"]').val('');
			});
		}
		queue(form, 80);
	});

	$(document).on('input', 'form[data-auto-filter] :input', function () {
		if (!$(this).is(TEXTUAL)) { return; }
		queue(this.form || $(this).closest('form')[0], 600);
	});

	$(document).on('keydown', 'form[data-auto-filter] :input', function (e) {
		if (e.key === 'Enter' && $(this).is(TEXTUAL)) {
			e.preventDefault();
			queue(this.form || $(this).closest('form')[0], 0);
		}
	});
})(jQuery);
