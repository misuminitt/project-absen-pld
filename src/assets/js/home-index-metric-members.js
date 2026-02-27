var homeIndexMetricMemberConfig = window.__HOME_INDEX_CONFIG || {};
(function () {
	var chartEndpoint = String(homeIndexMetricMemberConfig.chartEndpoint || "").trim();
	var modal = document.getElementById("metricModal");
	var modalTitle = document.getElementById("metricModalTitle");
	var memberWrap = document.getElementById("metricMemberWrap");
	var memberTitle = document.getElementById("metricMemberTitle");
	var memberNote = document.getElementById("metricMemberNote");
	var memberList = document.getElementById("metricMemberList");
	var rangeButtons = document.querySelectorAll(".metric-range-btn[data-metric-range]");
	var metricCards = document.querySelectorAll("[data-metric-card]");

	if (!chartEndpoint || !modal || !memberWrap || !memberTitle || !memberNote || !memberList) {
		return;
	}

	var currentMetricKey = "hadir";
	var pollTimer = null;
	var pending = false;
	var requestSeq = 0;

	var metricLabels = {
		hadir: "Total Hadir",
		terlambat: "Total Terlambat",
		izin_cuti: "Total Izin/Cuti",
		alpha: "Total Alpha"
	};

	var toMetricKeyFromText = function (text) {
		var raw = String(text || "").toLowerCase();
		if (raw.indexOf("terlambat") !== -1) {
			return "terlambat";
		}
		if (raw.indexOf("izin") !== -1 || raw.indexOf("cuti") !== -1) {
			return "izin_cuti";
		}
		if (raw.indexOf("alpha") !== -1) {
			return "alpha";
		}
		return "hadir";
	};

	var getActiveRange = function () {
		for (var i = 0; i < rangeButtons.length; i += 1) {
			if (rangeButtons[i].classList.contains("active")) {
				return String(rangeButtons[i].getAttribute("data-metric-range") || "1B").toUpperCase();
			}
		}
		return "1B";
	};

	var syncMetricFromTitle = function () {
		if (!modalTitle) {
			return;
		}
		currentMetricKey = toMetricKeyFromText(modalTitle.textContent);
	};

	var renderState = function (titleText, noteText, details, names, unknownCount) {
		memberWrap.hidden = false;
		memberTitle.textContent = titleText;
		memberNote.textContent = noteText;
		memberList.innerHTML = "";

		var hasDetailRows = Array.isArray(details) && details.length > 0;
		if (hasDetailRows) {
			for (var d = 0; d < details.length; d += 1) {
				var detail = details[d] || {};
				var detailName = String(detail.name || "").trim();
				if (!detailName) {
					continue;
				}
				var detailDate = String(detail.date_label || detail.date || "").trim();
				var detailChip = document.createElement("span");
				detailChip.className = "metric-member-chip";
				detailChip.textContent = detailDate ? (detailName + " - " + detailDate) : detailName;
				memberList.appendChild(detailChip);
			}
		}

		for (var i = 0; !hasDetailRows && i < names.length; i += 1) {
			var chip = document.createElement("span");
			chip.className = "metric-member-chip";
			chip.textContent = String(names[i] || "");
			memberList.appendChild(chip);
		}

		if (unknownCount > 0) {
			var unknownChip = document.createElement("span");
			unknownChip.className = "metric-member-chip";
			unknownChip.textContent = "+" + String(unknownCount) + " data tanpa nama";
			memberList.appendChild(unknownChip);
		}
	};

	var renderLoading = function () {
		var metricLabel = metricLabels[currentMetricKey] || "Total Hadir";
		renderState("Daftar Karyawan - " + metricLabel, "Memuat daftar karyawan...", [], [], 0);
	};

	var renderError = function () {
		var metricLabel = metricLabels[currentMetricKey] || "Total Hadir";
		renderState("Daftar Karyawan - " + metricLabel, "Gagal memuat daftar karyawan.", [], [], 0);
	};

	var renderPayload = function (payload) {
		var metricLabel = String(payload && payload.metric_label ? payload.metric_label : (metricLabels[currentMetricKey] || "Total Hadir"));
		var rangeLabel = String(payload && payload.range_label ? payload.range_label : getActiveRange());
		var names = Array.isArray(payload && payload.employee_names) ? payload.employee_names : [];
		var detailsRaw = Array.isArray(payload && payload.employee_details) ? payload.employee_details : [];
		var details = [];
		for (var detailIndex = 0; detailIndex < detailsRaw.length; detailIndex += 1) {
			var row = detailsRaw[detailIndex];
			if (!row || typeof row !== "object") {
				continue;
			}
			var rowName = String(row.name || "").trim();
			if (!rowName) {
				continue;
			}
			details.push({
				name: rowName,
				date: String(row.date || "").trim(),
				date_label: String(row.date_label || "").trim()
			});
		}
		var unknownCount = Number(payload && payload.employee_unknown_count ? payload.employee_unknown_count : 0);
		if (!isFinite(unknownCount) || unknownCount < 0) {
			unknownCount = 0;
		}

		var total = Number(payload && payload.employee_count ? payload.employee_count : 0);
		if (!isFinite(total) || total < 0) {
			total = 0;
		}
		if (total <= 0) {
			total = (details.length > 0 ? details.length : names.length) + unknownCount;
		}
		var uniqueCount = Number(payload && payload.employee_unique_count ? payload.employee_unique_count : 0);
		if (!isFinite(uniqueCount) || uniqueCount < 0) {
			uniqueCount = 0;
		}
		if (uniqueCount <= 0) {
			uniqueCount = names.length;
		}

		var noteText = rangeLabel + " - total " + String(total) + " data";
		if (!total) {
			noteText = rangeLabel + " - tidak ada data karyawan pada metrik ini.";
		} else {
			if (uniqueCount > 0) {
				noteText += " (" + String(uniqueCount) + " karyawan unik)";
			}
			noteText += ".";
		}
		renderState("Daftar Karyawan - " + metricLabel, noteText, details, names, unknownCount);
	};

	var fetchMembers = function () {
		if (!modal.classList.contains("show")) {
			return;
		}
		if (pending) {
			return;
		}

		syncMetricFromTitle();
		var metricKey = currentMetricKey;
		var rangeKey = getActiveRange();
		var requestId = requestSeq + 1;
		requestSeq = requestId;
		pending = true;
		renderLoading();

		var requestUrl = chartEndpoint
			+ "?metric=" + encodeURIComponent(metricKey)
			+ "&range=" + encodeURIComponent(rangeKey)
			+ "&_members=" + String(Date.now());

		fetch(requestUrl, {
			credentials: "same-origin",
			headers: { "X-Requested-With": "XMLHttpRequest" }
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error("HTTP " + String(response.status));
				}
				return response.json();
			})
			.then(function (json) {
				if (requestId !== requestSeq) {
					return;
				}
				if (!json || json.success !== true) {
					throw new Error("Invalid payload");
				}
				renderPayload(json);
			})
			.catch(function () {
				if (requestId !== requestSeq) {
					return;
				}
				renderError();
			})
			.then(function () {
				if (requestId === requestSeq) {
					pending = false;
				}
			});
	};

	var startPolling = function () {
		if (pollTimer !== null) {
			window.clearInterval(pollTimer);
			pollTimer = null;
		}
		pollTimer = window.setInterval(function () {
			if (modal.classList.contains("show")) {
				fetchMembers();
			}
		}, 20000);
	};

	var stopPolling = function () {
		if (pollTimer !== null) {
			window.clearInterval(pollTimer);
			pollTimer = null;
		}
	};

	for (var i = 0; i < metricCards.length; i += 1) {
		(function (card) {
			card.addEventListener("click", function () {
				var key = String(card.getAttribute("data-metric-card") || "").trim();
				if (key) {
					currentMetricKey = key;
				}
				window.setTimeout(fetchMembers, 140);
			});
			card.addEventListener("keydown", function (event) {
				if (event.key === "Enter" || event.key === " ") {
					var key = String(card.getAttribute("data-metric-card") || "").trim();
					if (key) {
						currentMetricKey = key;
					}
					window.setTimeout(fetchMembers, 140);
				}
			});
		})(metricCards[i]);
	}

	for (var j = 0; j < rangeButtons.length; j += 1) {
		rangeButtons[j].addEventListener("click", function () {
			window.setTimeout(fetchMembers, 140);
		});
	}

	var modalObserver = new MutationObserver(function () {
		if (modal.classList.contains("show")) {
			fetchMembers();
			startPolling();
		} else {
			stopPolling();
		}
	});
	modalObserver.observe(modal, { attributes: true, attributeFilter: ["class"] });

	if (modal.classList.contains("show")) {
		fetchMembers();
		startPolling();
	}
})();
