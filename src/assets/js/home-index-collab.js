(function () {
	var config = window.__HOME_INDEX_CONFIG || {};
	var feedUrl = String(config.collabFeedUrl || '').trim();
	if (feedUrl === '') {
		return;
	}

	var syncLockUrl = String(config.collabSyncLockUrl || '').trim();
	var actor = String(config.collabActor || '').trim().toLowerCase();
	var pollMs = parseInt(config.collabPollMs, 10);
	if (!isFinite(pollMs) || pollMs < 3000) {
		pollMs = 10000;
	}
	var lockRefreshSeconds = parseInt(config.collabLockWaitRefreshSeconds, 10);
	if (!isFinite(lockRefreshSeconds) || lockRefreshSeconds < 3) {
		lockRefreshSeconds = 5;
	}
	var backupRequiredDirections = Array.isArray(config.syncBackupRequiredDirections)
		? config.syncBackupRequiredDirections
		: ['sheet_to_web_attendance', 'web_to_sheet', 'web_to_sheet_loan', 'sheet_loan_to_web'];
	var backupDirectionMap = {};
	for (var directionIndex = 0; directionIndex < backupRequiredDirections.length; directionIndex += 1) {
		var directionKey = String(backupRequiredDirections[directionIndex] || '').trim().toLowerCase();
		if (directionKey !== '') {
			backupDirectionMap[directionKey] = true;
		}
	}

	var state = {
		revision: parseInt(config.collabRevision, 10),
		sinceEventId: 0,
		snoozeUntil: 0,
		autoRefreshScheduled: false,
		pendingSync: false,
		syncBackupReady: config.syncBackupReady === true
	};
	if (!isFinite(state.revision) || state.revision < 0) {
		state.revision = 0;
	}
	var logoutLink = document.getElementById('adminLogoutLink');
	var suppressLogoutToastUntil = 0;

	var keyBase = 'absen_home_collab_' + (actor !== '' ? actor : 'admin');
	var lastEventKey = keyBase + '_last_event_id';
	var draftKey = keyBase + '_draft';
	var bootstrapKey = keyBase + '_bootstrap_shown';
	var lastAutoRefreshEventKey = keyBase + '_last_auto_refresh_event';

	var storedEventId = parseInt(window.localStorage.getItem(lastEventKey) || '0', 10);
	if (isFinite(storedEventId) && storedEventId > 0) {
		state.sinceEventId = storedEventId;
	}

	var accountRows = Array.isArray(config.accountRows) ? config.accountRows : [];
	var accountMap = {};
	for (var i = 0; i < accountRows.length; i += 1) {
		var row = accountRows[i] || {};
		var username = String(row.username || '').trim().toLowerCase();
		if (username !== '') {
			accountMap[username] = row;
		}
	}

	var toastStack = document.createElement('div');
	toastStack.className = 'collab-toast-stack';
	document.body.appendChild(toastStack);

	var blockOverlay = buildBlockOverlay();
	var confirmOverlay = buildConfirmOverlay();
	var draftOverlay = buildDraftOverlay();
	var lockOverlay = buildLockOverlay();
	document.body.appendChild(blockOverlay.root);
	document.body.appendChild(confirmOverlay.root);
	document.body.appendChild(draftOverlay.root);
	document.body.appendChild(lockOverlay.root);

	var trackedForms = [];
	initTrackedForms();
	ensureExpectedRevisionFields();
	bindEmployeeVersionFields();
	restoreDraftOnLoad();
	bindSyncForms();
	updateSyncBackupButtonState();
	bindLogoutGuard();

	updateToastTop();
	window.addEventListener('resize', updateToastTop);
	window.addEventListener('scroll', updateToastTop, { passive: true });

	fetchFeed(true);
	window.setInterval(function () {
		fetchFeed(false);
	}, pollMs);

	function toInt(value, fallback) {
		var number = parseInt(value, 10);
		if (!isFinite(number)) {
			return fallback;
		}
		return number;
	}

	function updateToastTop() {
		var topValue = 92;
		var topbar = document.querySelector('.topbar');
		if (topbar && typeof topbar.getBoundingClientRect === 'function') {
			var rect = topbar.getBoundingClientRect();
			topValue = Math.max(12, Math.round(rect.bottom + 12));
		}
		toastStack.style.setProperty('--collab-top', String(topValue) + 'px');
	}

	function buildBlockOverlay() {
		var root = document.createElement('div');
		root.className = 'collab-block-overlay';
		root.innerHTML = '' +
			'<div class="collab-block-card">' +
				'<h3 class="collab-block-title">Perubahan Baru Terdeteksi</h3>' +
				'<p class="collab-block-sub" id="collabBlockSummary">Ada update terbaru dari admin lain saat kamu masih edit.</p>' +
				'<div class="collab-countdown" id="collabBlockCountdown">10</div>' +
				'<div class="collab-action-list" style="margin-top:.72rem;">' +
					'<div class="collab-action-item">' +
						'<p class="collab-action-name">Refresh sekarang</p>' +
						'<p class="collab-action-desc">Reload langsung. Semua perubahan form yang belum disimpan akan hilang.</p>' +
						'<button type="button" class="collab-action-btn" data-collab-action="refresh">Refresh sekarang</button>' +
					'</div>' +
					'<div class="collab-action-item">' +
						'<p class="collab-action-name">Simpan draft lalu refresh</p>' +
						'<p class="collab-action-desc">Simpan isi form sementara di browser, lalu reload dan rekonsiliasi dengan data terbaru.</p>' +
						'<button type="button" class="collab-action-btn" data-collab-action="draft_refresh">Simpan draft lalu refresh</button>' +
					'</div>' +
					'<div class="collab-action-item">' +
						'<p class="collab-action-name">Tunda</p>' +
						'<p class="collab-action-desc">Tunda refresh sementara. Risiko konflik tetap ada kalau data server berubah lagi.</p>' +
						'<button type="button" class="collab-action-btn warning" data-collab-action="snooze">Tunda</button>' +
					'</div>' +
				'</div>' +
				'<p class="collab-mini-note">Saran: setelah update dari admin lain, lakukan sync sesuai kebutuhan sebelum lanjut edit.</p>' +
			'</div>';

		var actionButtons = root.querySelectorAll('[data-collab-action]');
		for (var buttonIndex = 0; buttonIndex < actionButtons.length; buttonIndex += 1) {
			actionButtons[buttonIndex].addEventListener('click', function () {
				if (this.disabled) {
					return;
				}
				openActionConfirm(String(this.getAttribute('data-collab-action') || ''));
			});
		}

		return {
			root: root,
			summaryEl: root.querySelector('#collabBlockSummary'),
			countdownEl: root.querySelector('#collabBlockCountdown'),
			actionButtons: actionButtons,
			countdownTimer: null,
			countdownValue: 0,
			pendingEvent: null
		};
	}

	function buildConfirmOverlay() {
		var root = document.createElement('div');
		root.className = 'collab-confirm-overlay';
		root.innerHTML = '' +
			'<div class="collab-confirm-card">' +
				'<h3 class="collab-confirm-title" id="collabConfirmTitle">Konfirmasi Aksi</h3>' +
				'<p class="collab-confirm-sub" id="collabConfirmDesc">Apakah kamu yakin?</p>' +
				'<div class="collab-confirm-actions">' +
					'<button type="button" class="collab-confirm-btn cancel" id="collabConfirmNo">Tidak</button>' +
					'<button type="button" class="collab-confirm-btn ok" id="collabConfirmYes">Yakin</button>' +
				'</div>' +
			'</div>';

		return {
			root: root,
			titleEl: root.querySelector('#collabConfirmTitle'),
			descEl: root.querySelector('#collabConfirmDesc'),
			noBtn: root.querySelector('#collabConfirmNo'),
			yesBtn: root.querySelector('#collabConfirmYes'),
			onYes: null
		};
	}

	function buildDraftOverlay() {
		var root = document.createElement('div');
		root.className = 'collab-draft-overlay';
		root.innerHTML = '' +
			'<div class="collab-draft-card">' +
				'<h3 class="collab-draft-title">Rekonsiliasi Draft</h3>' +
				'<p class="collab-draft-sub">Field yang sama berubah di server. Pilih nilai yang akan dipakai.</p>' +
				'<div class="collab-draft-list" id="collabDraftConflictList"></div>' +
				'<div class="collab-draft-actions">' +
					'<button type="button" class="collab-confirm-btn cancel" id="collabDraftCancel">Batal</button>' +
					'<button type="button" class="collab-confirm-btn ok" id="collabDraftApply">Terapkan Pilihan</button>' +
				'</div>' +
			'</div>';

		return {
			root: root,
			listEl: root.querySelector('#collabDraftConflictList'),
			cancelBtn: root.querySelector('#collabDraftCancel'),
			applyBtn: root.querySelector('#collabDraftApply'),
			conflicts: []
		};
	}

	function buildLockOverlay() {
		var root = document.createElement('div');
		root.className = 'collab-block-overlay';
		root.innerHTML = '' +
			'<div class="collab-block-card">' +
				'<h3 class="collab-block-title">Sync Lock Aktif</h3>' +
				'<p class="collab-block-sub" id="collabLockSummary">Admin lain sedang menjalankan sync. Tunggu sampai lock selesai.</p>' +
				'<div class="collab-countdown" id="collabLockCountdown">0</div>' +
				'<p class="collab-mini-note">Halaman akan otomatis refresh saat hitung mundur selesai. Setelah itu lakukan sync manual.</p>' +
			'</div>';
		return {
			root: root,
			summaryEl: root.querySelector('#collabLockSummary'),
			countdownEl: root.querySelector('#collabLockCountdown'),
			timer: null
		};
	}

	function showToast(title, body, variant, durationMs) {
		var item = document.createElement('div');
		item.className = 'collab-toast-item' + (variant ? ' ' + variant : '');
		item.innerHTML = '<strong>' + escapeHtml(title) + '</strong><div style="margin-top:.32rem;">' + escapeHtml(body) + '</div>';
		toastStack.appendChild(item);

		var timeout = typeof durationMs === 'number' && durationMs > 0 ? durationMs : 60000;
		window.setTimeout(function () {
			if (item && item.parentNode) {
				item.parentNode.removeChild(item);
			}
		}, timeout);
	}

	function toPendingSyncState(payload) {
		var data = payload && typeof payload === 'object' ? payload : {};
		var pendingRevision = toInt(data.pending_revision, 0);
		var lastSyncedRevision = toInt(data.last_synced_revision, 0);
		var hasPendingFlag = data.has_pending === true;
		var hasPending = hasPendingFlag || (pendingRevision > 0 && pendingRevision > lastSyncedRevision);
		return {
			hasPending: hasPending,
			pendingRevision: pendingRevision,
			lastSyncedRevision: lastSyncedRevision,
			pendingActorCount: toInt(data.pending_actor_count, 0)
		};
	}

	function updateLogoutButtonState() {
		if (!logoutLink) {
			return;
		}
		if (state.pendingSync) {
			logoutLink.classList.add('is-disabled');
			logoutLink.setAttribute('aria-disabled', 'true');
			logoutLink.setAttribute('title', 'Wajib sync data web ke sheet sebelum logout.');
			return;
		}
		logoutLink.classList.remove('is-disabled');
		logoutLink.removeAttribute('aria-disabled');
		logoutLink.removeAttribute('title');
	}

	function updatePendingSyncStatus(payload) {
		var parsed = toPendingSyncState(payload);
		var wasPending = state.pendingSync === true;
		state.pendingSync = parsed.hasPending;
		updateLogoutButtonState();
		if (!wasPending && state.pendingSync && Date.now() >= suppressLogoutToastUntil) {
			showToast(
				'Sync Wajib Sebelum Logout',
				'Perubahan kamu belum sync ke sheet. Jalankan "Sync Data Web ke Sheet" dulu.',
				'warning',
				12000
			);
			return;
		}
		if (wasPending && !state.pendingSync) {
			showToast(
				'Sync Selesai',
				'Perubahan kamu sudah sinkron. Logout sekarang diperbolehkan.',
				'sync',
				7000
			);
		}
	}

	function bindLogoutGuard() {
		if (!logoutLink) {
			return;
		}
		logoutLink.addEventListener('click', function (event) {
			if (!state.pendingSync) {
				return;
			}
			event.preventDefault();
			showToast(
				'Logout Ditolak',
				'Perubahan kamu belum sync ke sheet. Klik "Sync Data Web ke Sheet" dulu sebelum logout.',
				'warning',
				12000
			);
		});
	}

	function syncFormRequiresBackup(form) {
		if (!form) {
			return false;
		}
		if (String(form.getAttribute('data-requires-backup') || '') === '1') {
			return true;
		}
		var direction = String(form.getAttribute('data-sync-direction') || '').trim().toLowerCase();
		return !!backupDirectionMap[direction];
	}

	function updateSyncBackupButtonState() {
		var syncForms = document.querySelectorAll('form.sync-control-form');
		for (var i = 0; i < syncForms.length; i += 1) {
			var form = syncForms[i];
			if (!syncFormRequiresBackup(form)) {
				continue;
			}
			var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
			if (!submitButton) {
				continue;
			}
			if (state.syncBackupReady) {
				submitButton.disabled = false;
				submitButton.classList.remove('is-disabled');
				submitButton.removeAttribute('data-sync-backup-locked');
				submitButton.removeAttribute('aria-disabled');
				submitButton.removeAttribute('title');
				continue;
			}
			submitButton.disabled = false;
			submitButton.classList.add('is-disabled');
			submitButton.setAttribute('data-sync-backup-locked', '1');
			submitButton.setAttribute('aria-disabled', 'true');
			submitButton.setAttribute('title', 'Wajib backup local dulu sebelum sync.');
		}
	}

	function escapeHtml(value) {
		var text = String(value || '');
		return text
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/\"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function actionLabel(action) {
		var map = {
			create_account: 'Buat akun karyawan',
			update_account: 'Edit akun karyawan',
			delete_account: 'Hapus akun karyawan',
			update_attendance_deduction: 'Edit potongan absensi',
			delete_attendance_record: 'Hapus data absensi',
			sync_accounts_from_sheet: 'Sync akun dari sheet',
			sync_attendance_from_sheet: 'Sync data absen dari sheet',
			sync_web_to_sheet: 'Sync data web ke sheet',
			sync_web_to_loan_sheet: 'Sync data web ke pinjaman',
			sync_loan_sheet_to_web: 'Sync data pinjaman ke web',
			reset_total_alpha: 'Reset total alpha',
			update_account_field: 'Edit field akun',
			update_account_username: 'Ganti username akun',
			submit_day_off_swap_request: 'Pengajuan tukar hari libur',
			approve_day_off_swap_request: 'Setujui pengajuan tukar hari libur',
			reject_day_off_swap_request: 'Tolak pengajuan tukar hari libur',
			create_day_off_swap: 'Atur tukar hari libur',
			delete_day_off_swap: 'Batalkan tukar hari libur',
			update_privileged_password: 'Ubah akun privileged',
			update_privileged_display_name: 'Ubah nama akun privileged',
			create_feature_admin_account: 'Buat akun admin fitur',
			update_feature_admin_account_permissions: 'Update fitur akun admin'
		};
		var key = String(action || '').toLowerCase();
		if (map[key]) {
			return map[key];
		}
		if (key === '') {
			return 'Perubahan data';
		}
		return key.replace(/_/g, ' ');
	}

	function formatEventBody(event) {
		var actorText = String(event.actor || 'admin');
		var label = actionLabel(event.action || '');
		var note = String(event.note || '').trim();
		var target = String(event.target_username || '').trim();
		var when = String(event.created_at || '').trim();
		var body = actorText + ' melakukan: ' + label;
		if (target !== '') {
			body += ' (' + target + ')';
		}
		if (note !== '') {
			body += '. ' + note;
		}
		if (event.requires_sync) {
			body += ' Wajib sync dulu.';
		}
		if (when !== '') {
			body += ' [' + when + ']';
		}
		return body;
	}

	function setRevision(revisionValue) {
		var incoming = toInt(revisionValue, state.revision);
		if (incoming > state.revision) {
			state.revision = incoming;
		}
		ensureExpectedRevisionFields();
	}

	function ensureExpectedRevisionFields() {
		var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
		for (var i = 0; i < forms.length; i += 1) {
			var form = forms[i];
			var input = form.querySelector('input[name="expected_revision"]');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'expected_revision';
				form.appendChild(input);
			}
			input.value = String(state.revision);
		}
	}

	function resolveAccountFromInput(rawValue) {
		var value = String(rawValue || '').trim().toLowerCase();
		if (value === '') {
			return null;
		}
		if (accountMap[value]) {
			return accountMap[value];
		}
		if (value.indexOf(' - ') !== -1) {
			var parts = value.split(' - ');
			var usernamePart = String(parts[parts.length - 1] || '').trim().toLowerCase();
			if (usernamePart !== '' && accountMap[usernamePart]) {
				return accountMap[usernamePart];
			}
		}
		for (var key in accountMap) {
			if (!Object.prototype.hasOwnProperty.call(accountMap, key)) {
				continue;
			}
			var row = accountMap[key] || {};
			var employeeId = String(row.employee_id || '').trim().toLowerCase();
			if (employeeId !== '' && value === employeeId) {
				return row;
			}
		}
		return null;
	}

	function bindEmployeeVersionFields() {
		var editInput = document.getElementById('editUsernameInput');
		var editVersionInput = document.getElementById('editExpectedVersionInput');
		var editForm = document.getElementById('editEmployeeForm');
		var deleteInput = document.getElementById('deleteUsernameInput');
		var deleteVersionInput = document.getElementById('deleteExpectedVersionInput');
		var deleteForm = document.getElementById('deleteEmployeeForm');

		var syncVersion = function (sourceInput, versionInput) {
			if (!sourceInput || !versionInput) {
				return;
			}
			var row = resolveAccountFromInput(sourceInput.value);
			var version = row && isFinite(parseInt(row.record_version, 10)) ? parseInt(row.record_version, 10) : 1;
			if (!isFinite(version) || version <= 0) {
				version = 1;
			}
			versionInput.value = String(version);
		};

		if (editInput && editVersionInput) {
			editInput.addEventListener('input', function () {
				syncVersion(editInput, editVersionInput);
			});
			editInput.addEventListener('change', function () {
				syncVersion(editInput, editVersionInput);
			});
		}
		if (deleteInput && deleteVersionInput) {
			deleteInput.addEventListener('input', function () {
				syncVersion(deleteInput, deleteVersionInput);
			});
			deleteInput.addEventListener('change', function () {
				syncVersion(deleteInput, deleteVersionInput);
			});
		}
		if (editForm) {
			editForm.addEventListener('submit', function () {
				syncVersion(editInput, editVersionInput);
			});
		}
		if (deleteForm) {
			deleteForm.addEventListener('submit', function () {
				syncVersion(deleteInput, deleteVersionInput);
			});
		}
	}

	function getFieldNodes(form, name) {
		if (!form || !form.elements || !name) {
			return [];
		}
		var nodes = form.elements[name];
		if (!nodes) {
			return [];
		}
		if (nodes.tagName) {
			return [nodes];
		}
		var list = [];
		for (var i = 0; i < nodes.length; i += 1) {
			list.push(nodes[i]);
		}
		return list;
	}

	function getFieldValue(form, name) {
		var nodes = getFieldNodes(form, name);
		if (!nodes.length) {
			return '';
		}
		var first = nodes[0];
		var type = String(first.type || '').toLowerCase();
		if (type === 'radio') {
			for (var i = 0; i < nodes.length; i += 1) {
				if (nodes[i].checked) {
					return String(nodes[i].value || '');
				}
			}
			return '';
		}
		if (type === 'checkbox') {
			if (nodes.length === 1) {
				return nodes[0].checked ? String(nodes[0].value || '1') : '';
			}
			var checkedValues = [];
			for (var checkboxIndex = 0; checkboxIndex < nodes.length; checkboxIndex += 1) {
				if (nodes[checkboxIndex].checked) {
					checkedValues.push(String(nodes[checkboxIndex].value || '1'));
				}
			}
			return checkedValues.join('\n');
		}
		if (first.tagName === 'SELECT' && first.multiple) {
			var values = [];
			for (var optionIndex = 0; optionIndex < first.options.length; optionIndex += 1) {
				if (first.options[optionIndex].selected) {
					values.push(String(first.options[optionIndex].value || ''));
				}
			}
			return values.join('\n');
		}
		return String(first.value || '');
	}

	function setFieldValue(form, name, value) {
		var nodes = getFieldNodes(form, name);
		if (!nodes.length) {
			return;
		}
		var first = nodes[0];
		var type = String(first.type || '').toLowerCase();
		var valueText = String(value || '');
		if (type === 'radio') {
			for (var i = 0; i < nodes.length; i += 1) {
				nodes[i].checked = String(nodes[i].value || '') === valueText;
			}
			return;
		}
		if (type === 'checkbox') {
			if (nodes.length === 1) {
				nodes[0].checked = valueText !== '' && valueText !== '0' && valueText !== 'false';
				return;
			}
			var setMap = {};
			var parts = valueText.split('\n');
			for (var partIndex = 0; partIndex < parts.length; partIndex += 1) {
				setMap[String(parts[partIndex] || '')] = true;
			}
			for (var checkboxIndex = 0; checkboxIndex < nodes.length; checkboxIndex += 1) {
				nodes[checkboxIndex].checked = !!setMap[String(nodes[checkboxIndex].value || '')];
			}
			return;
		}
		if (first.tagName === 'SELECT' && first.multiple) {
			var selectedMap = {};
			var selected = valueText.split('\n');
			for (var selectedIndex = 0; selectedIndex < selected.length; selectedIndex += 1) {
				selectedMap[String(selected[selectedIndex] || '')] = true;
			}
			for (var optionIndex = 0; optionIndex < first.options.length; optionIndex += 1) {
				first.options[optionIndex].selected = !!selectedMap[String(first.options[optionIndex].value || '')];
			}
			return;
		}
		first.value = valueText;
	}

	function snapshotFormValues(form) {
		var output = {};
		var seen = {};
		if (!form || !form.elements) {
			return output;
		}
		for (var i = 0; i < form.elements.length; i += 1) {
			var element = form.elements[i];
			if (!element || !element.name) {
				continue;
			}
			var name = String(element.name);
			if (seen[name]) {
				continue;
			}
			seen[name] = true;
			var type = String(element.type || '').toLowerCase();
			if (type === 'file' || type === 'password') {
				continue;
			}
			if (name === 'expected_revision' || name === 'expected_version') {
				continue;
			}
			output[name] = getFieldValue(form, name);
		}
		return output;
	}

	function snapshotsEqual(left, right) {
		left = left || {};
		right = right || {};
		var key;
		for (key in left) {
			if (!Object.prototype.hasOwnProperty.call(left, key)) {
				continue;
			}
			if (String(left[key] || '') !== String(right[key] || '')) {
				return false;
			}
		}
		for (key in right) {
			if (!Object.prototype.hasOwnProperty.call(right, key)) {
				continue;
			}
			if (String(right[key] || '') !== String(left[key] || '')) {
				return false;
			}
		}
		return true;
	}

	function initTrackedForms() {
		var forms = document.querySelectorAll('form.account-form');
		for (var i = 0; i < forms.length; i += 1) {
			var form = forms[i];
			var formId = String(form.getAttribute('id') || '').trim();
			if (formId === '') {
				formId = 'tracked_form_' + String(i + 1);
				form.setAttribute('data-collab-form-id', formId);
			}
			var baseline = snapshotFormValues(form);
			trackedForms.push({
				id: formId,
				form: form,
				baseline: baseline
			});

			form.addEventListener('input', updateDirtyState);
			form.addEventListener('change', updateDirtyState);
		}
	}

	function updateDirtyState() {
		for (var i = 0; i < trackedForms.length; i += 1) {
			var item = trackedForms[i];
			item.dirty = !snapshotsEqual(snapshotFormValues(item.form), item.baseline);
		}
	}

	function isEditingNow() {
		updateDirtyState();
		for (var i = 0; i < trackedForms.length; i += 1) {
			if (trackedForms[i].dirty) {
				return true;
			}
		}
		return false;
	}

	function collectDraftPayload() {
		updateDirtyState();
		var formsPayload = {};
		for (var i = 0; i < trackedForms.length; i += 1) {
			var item = trackedForms[i];
			if (!item.dirty) {
				continue;
			}
			formsPayload[item.id] = {
				values: snapshotFormValues(item.form),
				baseline: item.baseline
			};
		}
		return {
			revision: state.revision,
			created_at: new Date().toISOString(),
			forms: formsPayload
		};
	}

	function saveDraftToStorage() {
		var payload = collectDraftPayload();
		if (!payload.forms || !Object.keys(payload.forms).length) {
			return false;
		}
		window.localStorage.setItem(draftKey, JSON.stringify(payload));
		return true;
	}

	function clearDraftStorage() {
		window.localStorage.removeItem(draftKey);
	}

	function findTrackedFormById(formId) {
		for (var i = 0; i < trackedForms.length; i += 1) {
			if (trackedForms[i].id === formId) {
				return trackedForms[i];
			}
		}
		return null;
	}

	function findFieldLabel(form, fieldName) {
		if (!form) {
			return fieldName;
		}
		var nodes = getFieldNodes(form, fieldName);
		if (!nodes.length) {
			return fieldName;
		}
		var input = nodes[0];
		if (input.id) {
			var label = form.querySelector('label[for="' + input.id.replace(/"/g, '\\"') + '"]');
			if (label) {
				var text = String(label.textContent || '').trim();
				if (text !== '') {
					return text;
				}
			}
		}
		return fieldName;
	}

	function applyDraftPayload(payload) {
		payload = payload && typeof payload === 'object' ? payload : null;
		if (!payload || !payload.forms || typeof payload.forms !== 'object') {
			clearDraftStorage();
			return;
		}

		var conflictRows = [];
		var appliedCount = 0;
		for (var formId in payload.forms) {
			if (!Object.prototype.hasOwnProperty.call(payload.forms, formId)) {
				continue;
			}
			var tracked = findTrackedFormById(formId);
			if (!tracked) {
				continue;
			}
			var formPayload = payload.forms[formId] || {};
			var values = formPayload.values || {};
			var baseline = formPayload.baseline || {};
			for (var fieldName in values) {
				if (!Object.prototype.hasOwnProperty.call(values, fieldName)) {
					continue;
				}
				var currentValue = getFieldValue(tracked.form, fieldName);
				var baselineValue = String(baseline[fieldName] || '');
				var draftValue = String(values[fieldName] || '');
				if (String(currentValue || '') === baselineValue) {
					setFieldValue(tracked.form, fieldName, draftValue);
					appliedCount += 1;
					continue;
				}
				if (String(currentValue || '') === draftValue) {
					continue;
				}
				conflictRows.push({
					formId: formId,
					fieldName: fieldName,
					label: findFieldLabel(tracked.form, fieldName),
					serverValue: String(currentValue || ''),
					draftValue: draftValue
				});
			}
		}

		if (!conflictRows.length) {
			clearDraftStorage();
			for (var i = 0; i < trackedForms.length; i += 1) {
				trackedForms[i].baseline = snapshotFormValues(trackedForms[i].form);
			}
			if (appliedCount > 0) {
				showToast('Draft Dipulihkan', 'Perubahan draft berhasil dipulihkan ke form.', 'sync', 9000);
			}
			return;
		}

		openDraftConflictModal(conflictRows, function (choices) {
			for (var conflictIndex = 0; conflictIndex < conflictRows.length; conflictIndex += 1) {
				var conflict = conflictRows[conflictIndex];
				var trackedForm = findTrackedFormById(conflict.formId);
				if (!trackedForm) {
					continue;
				}
				var key = conflict.formId + '::' + conflict.fieldName;
				var choice = choices[key] || 'server';
				if (choice === 'draft') {
					setFieldValue(trackedForm.form, conflict.fieldName, conflict.draftValue);
				}
			}
			clearDraftStorage();
			for (var i = 0; i < trackedForms.length; i += 1) {
				trackedForms[i].baseline = snapshotFormValues(trackedForms[i].form);
			}
			showToast('Draft Direkonsiliasi', 'Pilihan nilai server/draft sudah diterapkan ke form.', 'sync', 10000);
		});
	}

	function restoreDraftOnLoad() {
		var raw = window.localStorage.getItem(draftKey);
		if (!raw) {
			return;
		}
		try {
			var payload = JSON.parse(raw);
			applyDraftPayload(payload);
		}
		catch (error) {
			clearDraftStorage();
		}
	}

	function openDraftConflictModal(conflicts, onApply) {
		draftOverlay.conflicts = conflicts.slice();
		draftOverlay.listEl.innerHTML = '';
		for (var i = 0; i < draftOverlay.conflicts.length; i += 1) {
			var row = draftOverlay.conflicts[i];
			var key = row.formId + '::' + row.fieldName;
			var rowEl = document.createElement('div');
			rowEl.className = 'collab-draft-row';
			rowEl.innerHTML = '' +
				'<p class="collab-draft-field">' + escapeHtml(row.label) + '</p>' +
				'<div class="collab-draft-values">' +
					'<div><strong>Server:</strong> ' + escapeHtml(row.serverValue || '-') + '</div>' +
					'<div><strong>Draft:</strong> ' + escapeHtml(row.draftValue || '-') + '</div>' +
				'</div>' +
				'<div class="collab-draft-choice">' +
					'<label><input type="radio" name="draft_choice_' + escapeHtml(String(i)) + '" value="server" checked> Pakai server</label>' +
					'<label><input type="radio" name="draft_choice_' + escapeHtml(String(i)) + '" value="draft"> Pakai draft</label>' +
				'</div>';
			rowEl.setAttribute('data-draft-key', key);
			draftOverlay.listEl.appendChild(rowEl);
		}
		draftOverlay.root.classList.add('show');

		draftOverlay.cancelBtn.onclick = function () {
			draftOverlay.root.classList.remove('show');
			clearDraftStorage();
		};
		draftOverlay.applyBtn.onclick = function () {
			var choices = {};
			var rows = draftOverlay.listEl.querySelectorAll('.collab-draft-row');
			for (var rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
				var rowEl = rows[rowIndex];
				var conflictKey = String(rowEl.getAttribute('data-draft-key') || '');
				var checked = rowEl.querySelector('input[type="radio"]:checked');
				choices[conflictKey] = checked ? String(checked.value || 'server') : 'server';
			}
			draftOverlay.root.classList.remove('show');
			if (typeof onApply === 'function') {
				onApply(choices);
			}
		};
	}

	function fetchFeed(isBootstrap) {
		var params = [];
		params.push('since_id=' + encodeURIComponent(String(state.sinceEventId || 0)));
		params.push('limit=40');
		if (isBootstrap) {
			params.push('bootstrap=1');
		}
		var requestUrl = feedUrl + (feedUrl.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
		fetch(requestUrl, {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Cache-Control': 'no-cache'
			},
			cache: 'no-store'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + String(response.status || '500'));
				}
				return response.json();
			})
			.then(function (payload) {
				if (!payload || payload.success !== true) {
					return;
				}
				setRevision(payload.revision);
				updatePendingSyncStatus(payload.pending_sync || null);
				handleIncomingEvents(payload.events || [], !!isBootstrap);
			})
			.catch(function () {
				// Silent by design.
			});
	}

	function handleIncomingEvents(events, isBootstrap) {
		if (!Array.isArray(events) || !events.length) {
			return;
		}
		var firstBootstrapInTab = false;
		if (isBootstrap) {
			firstBootstrapInTab = window.sessionStorage.getItem(bootstrapKey) !== '1';
			window.sessionStorage.setItem(bootstrapKey, '1');
		}

		for (var i = 0; i < events.length; i += 1) {
			var event = events[i] || {};
			var eventId = toInt(event.id, 0);
			if (eventId <= 0) {
				continue;
			}
			if (eventId > state.sinceEventId) {
				state.sinceEventId = eventId;
			}
			if (toInt(event.revision, 0) > state.revision) {
				setRevision(toInt(event.revision, state.revision));
			}

			if (isBootstrap && !firstBootstrapInTab) {
				continue;
			}

			var toastVariant = event.event_type === 'sync' ? 'sync' : '';
			showToast('Notifikasi Perubahan', formatEventBody(event), toastVariant, 60000);
			handleRealtimeAction(event);
		}

		window.localStorage.setItem(lastEventKey, String(state.sinceEventId));
	}

	function handleRealtimeAction(event) {
		var eventActor = String(event.actor || '').trim().toLowerCase();
		var isFromOtherActor = actor !== '' && eventActor !== '' && eventActor !== actor;
		if (!isFromOtherActor) {
			return;
		}
		if (Date.now() < state.snoozeUntil) {
			return;
		}

		if (isEditingNow()) {
			openBlockingModal(event);
			return;
		}

		if (String(event.event_type || '') === 'sync') {
			scheduleAutoRefreshForSyncEvent(event);
		}
	}

	function scheduleAutoRefreshForSyncEvent(event) {
		if (state.autoRefreshScheduled) {
			return;
		}
		var eventId = toInt(event.id, 0);
		if (eventId <= 0) {
			return;
		}
		var lastRefreshedEventId = toInt(window.sessionStorage.getItem(lastAutoRefreshEventKey) || '0', 0);
		if (eventId <= lastRefreshedEventId) {
			return;
		}
		state.autoRefreshScheduled = true;
		window.sessionStorage.setItem(lastAutoRefreshEventKey, String(eventId));
		window.setTimeout(function () {
			window.location.reload();
		}, 2500);
	}

	function openBlockingModal(event) {
		if (blockOverlay.root.classList.contains('show')) {
			return;
		}
		blockOverlay.pendingEvent = event;
		blockOverlay.summaryEl.textContent = formatEventBody(event);
		blockOverlay.root.classList.add('show');
		startBlockingCountdown(10);
	}

	function startBlockingCountdown(seconds) {
		if (blockOverlay.countdownTimer) {
			window.clearInterval(blockOverlay.countdownTimer);
			blockOverlay.countdownTimer = null;
		}
		blockOverlay.countdownValue = seconds;
		setBlockingButtonsEnabled(false);
		blockOverlay.countdownEl.textContent = String(blockOverlay.countdownValue);
		blockOverlay.countdownTimer = window.setInterval(function () {
			blockOverlay.countdownValue -= 1;
			if (blockOverlay.countdownValue <= 0) {
				window.clearInterval(blockOverlay.countdownTimer);
				blockOverlay.countdownTimer = null;
				blockOverlay.countdownValue = 0;
				setBlockingButtonsEnabled(true);
			}
			blockOverlay.countdownEl.textContent = String(blockOverlay.countdownValue);
		}, 1000);
	}

	function setBlockingButtonsEnabled(enabled) {
		for (var i = 0; i < blockOverlay.actionButtons.length; i += 1) {
			blockOverlay.actionButtons[i].disabled = !enabled;
		}
	}

	function closeBlockingModal() {
		if (blockOverlay.countdownTimer) {
			window.clearInterval(blockOverlay.countdownTimer);
			blockOverlay.countdownTimer = null;
		}
		blockOverlay.root.classList.remove('show');
	}

	function openActionConfirm(actionName) {
		var configMap = {
			refresh: {
				title: 'Konfirmasi Refresh Sekarang',
				description: 'Halaman akan langsung reload. Semua perubahan form yang belum disimpan akan hilang. Lanjutkan?',
				onConfirm: function () {
					window.location.reload();
				}
			},
			draft_refresh: {
				title: 'Konfirmasi Simpan Draft',
				description: 'Isi form saat ini akan disimpan sementara di browser, lalu halaman reload. Setelah reload kamu bisa pilih rekonsiliasi server vs draft. Lanjutkan?',
				onConfirm: function () {
					saveDraftToStorage();
					window.location.reload();
				}
			},
			snooze: {
				title: 'Konfirmasi Tunda',
				description: 'Refresh akan ditunda sementara. Kamu tetap berisiko konflik jika data server berubah lagi. Lanjut tunda?',
				onConfirm: function () {
					state.snoozeUntil = Date.now() + (lockRefreshSeconds * 1000);
					closeBlockingModal();
				}
			}
		};
		var selected = configMap[actionName];
		if (!selected) {
			return;
		}

		confirmOverlay.titleEl.textContent = selected.title;
		confirmOverlay.descEl.textContent = selected.description;
		confirmOverlay.onYes = selected.onConfirm;
		confirmOverlay.root.classList.add('show');
	}

	confirmOverlay.noBtn.addEventListener('click', function () {
		confirmOverlay.root.classList.remove('show');
	});
	confirmOverlay.yesBtn.addEventListener('click', function () {
		var callback = confirmOverlay.onYes;
		confirmOverlay.root.classList.remove('show');
		if (typeof callback === 'function') {
			callback();
		}
	});

	function bindSyncForms() {
		var syncForms = document.querySelectorAll('form.sync-control-form');
		for (var i = 0; i < syncForms.length; i += 1) {
			(function (form) {
				form.addEventListener('submit', function (event) {
					if (form.getAttribute('data-sync-bypass') === '1') {
						form.removeAttribute('data-sync-bypass');
						return;
					}
					event.preventDefault();
					if (syncFormRequiresBackup(form) && !state.syncBackupReady) {
						var backupWarningMessage = 'Sebelum sync, klik "Backup Local Dulu (Wajib)" dulu untuk buat snapshot data di server.';
						showToast(
							'Backup Wajib Sebelum Sync',
							backupWarningMessage,
							'warning',
							12000
						);
						try {
							window.alert(backupWarningMessage);
						} catch (alertError) {}
						updateSyncBackupButtonState();
						return;
					}
					checkSyncLockStatus()
						.then(function (lock) {
							if (lock.active && lock.owner !== '' && lock.owner !== actor) {
								showLockWaitModal(lock);
								var lockOwner = String(lock.owner || 'admin lain');
								var lockWait = toInt(lock.remaining_seconds, 0);
								if (lockWait <= 0) {
									lockWait = lockRefreshSeconds;
								}
								try {
									window.alert('Sync lock sedang dipakai oleh ' + lockOwner + '. Coba lagi sekitar ' + String(lockWait) + ' detik lagi.');
								} catch (alertError) {}
								return;
							}
							suppressLogoutToastUntil = Date.now() + 5000;
							if (syncFormRequiresBackup(form)) {
								state.syncBackupReady = false;
								updateSyncBackupButtonState();
							}
							form.setAttribute('data-sync-bypass', '1');
							form.submit();
						})
						.catch(function () {
							if (syncFormRequiresBackup(form)) {
								state.syncBackupReady = false;
								updateSyncBackupButtonState();
							}
							form.setAttribute('data-sync-bypass', '1');
							form.submit();
						});
				});
			})(syncForms[i]);
		}
	}

	function checkSyncLockStatus() {
		if (syncLockUrl === '') {
			return Promise.resolve({ active: false, owner: '', remaining_seconds: 0 });
		}
		var url = syncLockUrl + (syncLockUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + String(Date.now());
		return fetch(url, {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Cache-Control': 'no-cache'
			},
			cache: 'no-store'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + String(response.status || '500'));
				}
				return response.json();
			})
			.then(function (payload) {
				var lock = payload && payload.lock ? payload.lock : {};
				return {
					active: !!lock.active,
					owner: String(lock.owner || '').trim().toLowerCase(),
					remaining_seconds: toInt(lock.remaining_seconds, 0)
				};
			});
	}

	function showLockWaitModal(lock) {
		if (lockOverlay.timer) {
			window.clearInterval(lockOverlay.timer);
			lockOverlay.timer = null;
		}
		var seconds = toInt(lock.remaining_seconds, 0);
		if (seconds <= 0) {
			seconds = lockRefreshSeconds;
		}
		var owner = String(lock.owner || 'admin lain');
		lockOverlay.summaryEl.textContent = 'Sync lock sedang dipakai oleh ' + owner + '. Tunggu sampai selesai.';
		lockOverlay.countdownEl.textContent = String(seconds);
		lockOverlay.root.classList.add('show');

		lockOverlay.timer = window.setInterval(function () {
			seconds -= 1;
			if (seconds <= 0) {
				window.clearInterval(lockOverlay.timer);
				lockOverlay.timer = null;
				window.location.reload();
				return;
			}
			lockOverlay.countdownEl.textContent = String(seconds);
		}, 1000);
	}
})();
