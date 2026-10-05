/*
 * Release 4.9.0CF: confirmation for a CASH transaction dated before the Cash Opening Date.
 *
 * The server answers such a save with HTTP 409 {cash_opening_warning:true, token, ...} and
 * writes nothing. This wraps jQuery's POST requests: on that answer it shows a modal, and
 *   Continue and Save -> sends the SAME request again with the server's token (the server
 *                        re-checks it against the date, method, record and opening date);
 *   Go Back           -> hands the 409 to the form's normal error handling (nothing saved).
 * Every other request, and every non-409 answer, behaves exactly as before.
 */
(function ($) {
	if (!$ || $.__cashOpeningGuard) { return; }
	$.__cashOpeningGuard = true;
	var realAjax = $.ajax;

	function json(xhr) {
		if (xhr && xhr.responseJSON) { return xhr.responseJSON; }
		try { return JSON.parse(xhr.responseText); } catch (e) { return null; }
	}

	function withToken(data, field, token) {
		if (typeof FormData !== 'undefined' && data instanceof FormData) { data.set(field, token); return data; }
		if (typeof data === 'string') {
			return data.replace(new RegExp('(^|&)' + field + '=[^&]*', 'g'), '') + (data ? '&' : '') + encodeURIComponent(field) + '=' + encodeURIComponent(token);
		}
		var o = $.extend({}, data || {}); o[field] = token; return o;
	}

	function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

	function ask(info, onContinue, onBack) {
		$('#cashOpeningModal').remove();
		var html =
			'<div class="modal fade" id="cashOpeningModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">' +
			'<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
			'<div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Cash Transaction Before Opening Date</h5></div>' +
			'<div class="modal-body">' +
			'<div class="mb-2"><small class="text-muted text-uppercase fw-semibold">Transaction Date</small><div class="fw-semibold fs-6" id="cowTxDate">' + esc(info.tx_date) + '</div></div>' +
			'<div class="mb-3"><small class="text-muted text-uppercase fw-semibold">Cash Opening Date</small><div class="fw-semibold fs-6" id="cowOpenDate">' + esc(info.opening_date) + '</div></div>' +
			'<p class="mb-0" id="cowDetail">' + esc(info.detail) + '</p>' +
			'</div>' +
			'<div class="modal-footer"><button type="button" class="btn btn-secondary" id="cowBack">Go Back</button>' +
			'<button type="button" class="btn btn-warning" id="cowContinue">Continue and Save</button></div>' +
			'</div></div></div>';
		$('body').append(html);
		var el = document.getElementById('cashOpeningModal');
		var modal = window.bootstrap ? new window.bootstrap.Modal(el) : null;
		var done = false;
		function finish(fn) { if (done) { return; } done = true; if (modal) { modal.hide(); } else { $(el).remove(); } fn(); }
		$('#cowBack').on('click', function () { finish(onBack); });
		$('#cowContinue').on('click', function () { finish(onContinue); });
		if (modal) { modal.show(); } else { el.style.display = 'block'; el.classList.add('show'); }
	}

	$.ajax = function (url, options) {
		var o = (typeof url === 'object') ? url : $.extend({}, options || {}, { url: url });
		var method = String(o.type || o.method || 'GET').toUpperCase();
		if (method === 'GET' || o.async === false) { return realAjax.apply(this, arguments); }

		var cb = { success: o.success, error: o.error, complete: o.complete };
		var ctx = o.context || window;
		var dfd = $.Deferred();

		function settle(ok, args) {
			var xhr = ok ? args[2] : args[0];
			var cbFn = ok ? cb.success : cb.error;
			if (cbFn) { (Array.isArray(cbFn) ? cbFn : [cbFn]).forEach(function (f) { f.apply(ctx, ok ? args : args); }); }
			if (cb.complete) { (Array.isArray(cb.complete) ? cb.complete : [cb.complete]).forEach(function (f) { f.call(ctx, xhr, args[1]); }); }
			dfd[ok ? 'resolveWith' : 'rejectWith'](ctx, args);
		}

		function run(token, field) {
			var o2 = $.extend({}, o, { success: undefined, error: undefined, complete: undefined });
			if (token) { o2.data = withToken(o.data, field, token); }
			realAjax.call($, o2).done(function (d, s, x) { settle(true, [d, s, x]); })
				.fail(function (x, s, e) {
					var j = (x && x.status === 409) ? json(x) : null;
					if (j && j.cash_opening_warning) {
						ask(j, function () { run(j.token, j.field); }, function () { settle(false, [x, s, e]); });
					} else {
						settle(false, [x, s, e]);
					}
				});
		}
		run(null);

		var p = dfd.promise();
		p.abort = function () { return p; };
		return p;
	};
})(window.jQuery);
