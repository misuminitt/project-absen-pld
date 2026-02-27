(function () {
	var config = window.__HOME_INDEX_CONFIG || {};
	var accountRows = Array.isArray(config.accountRows) ? config.accountRows : [];
	if (!accountRows.length) {
		return;
	}

	var editUsernameInput = document.getElementById('editUsernameInput');
	var editCrossBranchInput = document.getElementById('editCrossBranchInput');
	if (!editUsernameInput || !editCrossBranchInput) {
		return;
	}

	var byKey = {};
	var rows = [];

	var normalize = function (value) {
		return String(value || '').trim().toLowerCase();
	};

	var resolveCrossBranchValue = function (value) {
		if (typeof value === 'boolean') {
			return value ? 1 : 0;
		}
		if (typeof value === 'number') {
			return value === 1 ? 1 : 0;
		}
		var text = normalize(value);
		if (text === '1' || text === 'ya' || text === 'iya' || text === 'yes' || text === 'true' || text === 'aktif' || text === 'enabled' || text === 'on') {
			return 1;
		}
		return 0;
	};

	for (var i = 0; i < accountRows.length; i += 1) {
		var row = accountRows[i] || {};
		var username = normalize(row.username);
		var employeeId = normalize(row.employee_id);
		if (username !== '') {
			byKey[username] = row;
		}
		if (employeeId !== '' && employeeId !== '-') {
			byKey[employeeId] = row;
			byKey[employeeId + ' - ' + username] = row;
		}
		rows.push(row);
	}

	var resolveAccount = function (rawValue, allowFuzzy) {
		var key = normalize(rawValue);
		if (key === '') {
			return null;
		}
		if (Object.prototype.hasOwnProperty.call(byKey, key)) {
			return byKey[key];
		}
		if (key.indexOf(' - ') !== -1) {
			var parts = key.split(' - ');
			var usernamePart = normalize(parts[parts.length - 1]);
			if (usernamePart !== '' && Object.prototype.hasOwnProperty.call(byKey, usernamePart)) {
				return byKey[usernamePart];
			}
		}
		if (!allowFuzzy) {
			return null;
		}

		var matches = [];
		for (var idx = 0; idx < rows.length; idx += 1) {
			var row = rows[idx] || {};
			var username = normalize(row.username);
			var employeeId = normalize(row.employee_id);
			var composite = employeeId !== '' && employeeId !== '-' ? employeeId + ' - ' + username : username;
			if (
				(username !== '' && username.indexOf(key) !== -1) ||
				(employeeId !== '' && employeeId !== '-' && employeeId.indexOf(key) !== -1) ||
				(composite.indexOf(key) !== -1)
			) {
				matches.push(row);
			}
		}
		return matches.length === 1 ? matches[0] : null;
	};

	var applyCrossBranchDefaultForSelectedAccount = function () {
		var row = resolveAccount(editUsernameInput.value, true);
		if (!row) {
			if (normalize(editUsernameInput.value) === '') {
				editCrossBranchInput.value = '0';
			}
			return;
		}

		var crossBranchRaw = Object.prototype.hasOwnProperty.call(row, 'cross_branch_enabled')
			? row.cross_branch_enabled
			: row.lintas_cabang;
		editCrossBranchInput.value = resolveCrossBranchValue(crossBranchRaw) === 1 ? '1' : '0';
	};

	var scheduleApply = function () {
		window.setTimeout(applyCrossBranchDefaultForSelectedAccount, 0);
	};

	editUsernameInput.addEventListener('input', scheduleApply);
	editUsernameInput.addEventListener('change', scheduleApply);
	editUsernameInput.addEventListener('blur', scheduleApply);

	scheduleApply();
})();
