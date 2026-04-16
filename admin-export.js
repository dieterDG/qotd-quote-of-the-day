(function () {
	'use strict';

	function triggerDownload(content, filename) {
		var blob = new Blob([content], { type: 'application/json;charset=utf-8;' });
		var url  = URL.createObjectURL(blob);
		var a    = document.createElement('a');
		a.href     = url;
		a.download = filename;
		a.style.display = 'none';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var btn    = document.getElementById('qotd-export-btn');
		var status = document.getElementById('qotd-export-status');

		if (!btn) {
			return;
		}

		btn.addEventListener('click', function () {
			btn.disabled    = true;
			btn.textContent = QOTDExport.labelLoading;
			status.textContent = '';

			fetch(QOTDExport.endpoint, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': QOTDExport.nonce,
				},
			})
				.then(function (res) {
					if (!res.ok) {
						throw new Error('HTTP ' + res.status);
					}
					return res.json();
				})
				.then(function (data) {
					triggerDownload(JSON.stringify(data, null, 2), QOTDExport.filename);
				})
				.catch(function (err) {
					status.textContent = QOTDExport.labelError + ' ' + err.message;
					status.style.color = '#d63638';
				})
				.finally(function () {
					btn.disabled    = false;
					btn.textContent = QOTDExport.labelExport;
				});
		});
	});
})();
