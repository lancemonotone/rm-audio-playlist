/**
 * RM Audio Playlist — admin: shortcode select, clear tracks, bulk allow download.
 */
(function () {
	"use strict";

	var cfg = window.rmAudioPlaylistAdmin;
	if (!cfg || typeof cfg !== "object") {
		cfg = {};
	}

	function initDownloadAllToggle() {
		var cb = document.getElementById("rm-pl-download-all");
		if (!cb) {
			return;
		}
		if (cb.getAttribute("data-bulk-state") === "mixed") {
			cb.indeterminate = true;
		}
	}

	function postJson(url, body) {
		return fetch(url, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"X-WP-Nonce": cfg.nonce,
				"Content-Type": "application/json",
			},
			body: JSON.stringify(body),
		}).then(function (res) {
			return res.json().then(function (payload) {
				return { ok: res.ok, body: payload };
			});
		});
	}

	function alertError(body, fallback) {
		var errMsg =
			body && body.message
				? body.message
				: typeof fallback === "string"
					? fallback
					: "Request failed.";
		window.alert(errMsg);
	}

	initDownloadAllToggle();

	document.addEventListener("click", function (e) {
		var t = e.target;
		if (t && t.id === "rm-pl-shortcode-copy") {
			t.select();
			return;
		}

		var btn = t && t.closest ? t.closest("#rm-pl-clear-tracks") : null;
		if (!btn || btn.disabled) {
			return;
		}

		if (!cfg.restClearUrl || !cfg.nonce) {
			return;
		}

		var msg =
			typeof cfg.confirmClear === "string"
				? cfg.confirmClear
				: "Remove all MP3 files for this playlist?";
		if (!window.confirm(msg)) {
			return;
		}

		btn.disabled = true;
		var dlToggle = document.getElementById("rm-pl-download-all");
		if (dlToggle) {
			dlToggle.disabled = true;
		}
		var status = document.getElementById("rm-pl-clear-tracks-status");
		if (status) {
			status.textContent =
				typeof cfg.clearing === "string" ? cfg.clearing : "Removing…";
			status.hidden = false;
		}

		postJson(cfg.restClearUrl, {})
			.then(function (result) {
				if (!result.ok) {
					alertError(
						result.body,
						typeof cfg.clearFailed === "string"
							? cfg.clearFailed
							: "Could not remove tracks.",
					);
					btn.disabled = false;
					if (dlToggle) {
						dlToggle.disabled = false;
					}
					if (status) {
						status.hidden = true;
					}
					return;
				}
				window.location.reload();
			})
			.catch(function () {
				alertError(
					null,
					typeof cfg.clearFailed === "string"
						? cfg.clearFailed
						: "Could not remove tracks.",
				);
				btn.disabled = false;
				if (dlToggle) {
					dlToggle.disabled = false;
				}
				if (status) {
					status.hidden = true;
				}
			});
	});

	document.addEventListener("change", function (e) {
		var cb = e.target;
		if (!cb || cb.id !== "rm-pl-download-all" || cb.disabled) {
			return;
		}
		if (!cfg.restDownloadAllUrl || !cfg.nonce) {
			return;
		}

		var downloadable = cb.checked;
		cb.disabled = true;
		var clearBtn = document.getElementById("rm-pl-clear-tracks");
		if (clearBtn) {
			clearBtn.disabled = true;
		}
		var status = document.getElementById("rm-pl-clear-tracks-status");
		if (status) {
			status.textContent =
				typeof cfg.downloadAllSaving === "string"
					? cfg.downloadAllSaving
					: "Updating…";
			status.hidden = false;
		}

		postJson(cfg.restDownloadAllUrl, { downloadable: downloadable })
			.then(function (result) {
				if (!result.ok) {
					alertError(
						result.body,
						typeof cfg.downloadAllFailed === "string"
							? cfg.downloadAllFailed
							: "Could not update download settings.",
					);
					cb.disabled = false;
					if (clearBtn) {
						clearBtn.disabled = false;
					}
					if (status) {
						status.hidden = true;
					}
					window.location.reload();
					return;
				}
				window.location.reload();
			})
			.catch(function () {
				alertError(
					null,
					typeof cfg.downloadAllFailed === "string"
						? cfg.downloadAllFailed
						: "Could not update download settings.",
				);
				cb.disabled = false;
				if (clearBtn) {
					clearBtn.disabled = false;
				}
				if (status) {
					status.hidden = true;
				}
				window.location.reload();
			});
	});
})();
