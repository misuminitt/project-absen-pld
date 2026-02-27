(function () {
	var storageKey = 'home_index_theme';
	var root = document.documentElement;
	var lastSyncedTheme = '';
	if (!root) {
		return;
	}

	var resolveSyncEndpoint = function () {
		if (typeof window.__homeThemeSyncEndpoint === 'string' && window.__homeThemeSyncEndpoint !== '') {
			return window.__homeThemeSyncEndpoint;
		}
		var source = '';
		try {
			if (document.currentScript && document.currentScript.src) {
				source = String(document.currentScript.src);
			}
		} catch (error) {}
		if (source !== '') {
			var marker = '/src/assets/js/theme-global-init.js';
			var markerIndex = source.indexOf(marker);
			if (markerIndex !== -1) {
				return source.slice(0, markerIndex) + '/home/set_theme_preference';
			}
		}
		return '/home/set_theme_preference';
	};

	var syncThemeToServer = function (theme) {
		if (theme !== 'dark' && theme !== 'light') {
			return;
		}
		if (lastSyncedTheme === theme) {
			return;
		}
		lastSyncedTheme = theme;

		var syncEndpoint = resolveSyncEndpoint();
		var payload = 'theme=' + encodeURIComponent(theme);

		try {
			if (window.fetch) {
				window.fetch(syncEndpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
						'X-Requested-With': 'XMLHttpRequest'
					},
					credentials: 'same-origin',
					body: payload,
					keepalive: true
				}).catch(function () {});
				return;
			}
		} catch (error) {}

		try {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', syncEndpoint, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.send(payload);
		} catch (error) {}
	};

	var readFromStorage = function () {
		var saved = '';
		try {
			saved = String(window.localStorage.getItem(storageKey) || '').toLowerCase();
		} catch (error) {}
		if (saved === 'dark' || saved === 'light') {
			return saved;
		}
		var cookieMatch = document.cookie.match(/(?:^|;\s*)home_index_theme=(dark|light)\b/i);
		if (cookieMatch && cookieMatch[1]) {
			return String(cookieMatch[1]).toLowerCase();
		}
		return '';
	};

	var persistTheme = function (theme) {
		if (theme !== 'dark' && theme !== 'light') {
			return;
		}
		try {
			window.localStorage.setItem(storageKey, theme);
		} catch (error) {}
		try {
			document.cookie = storageKey + '=' + encodeURIComponent(theme) + ';path=/;max-age=31536000;SameSite=Lax';
		} catch (error) {}
		syncThemeToServer(theme);
	};

	var applyToBody = function (isDark) {
		if (!document.body) {
			return;
		}
		document.body.classList.toggle('theme-dark', isDark);
	};

	var applyTheme = function (theme, shouldPersist) {
		var normalized = theme === 'dark' ? 'dark' : 'light';
		var isDark = normalized === 'dark';
		root.classList.toggle('theme-dark', isDark);
		root.setAttribute('data-theme', normalized);
		applyToBody(isDark);
		if (!document.body) {
			document.addEventListener('DOMContentLoaded', function () {
				applyToBody(isDark);
			}, { once: true });
		}
		if (shouldPersist) {
			persistTheme(normalized);
		}
		try {
			window.dispatchEvent(new CustomEvent('home-theme-changed', {
				detail: {
					isDark: isDark,
					theme: normalized
				}
			}));
		} catch (error) {}
	};

	var readActiveTheme = function () {
		var current = String(root.getAttribute('data-theme') || '').toLowerCase();
		if (current === 'dark' || current === 'light') {
			return current;
		}
		return root.classList.contains('theme-dark') ? 'dark' : 'light';
	};

	var updateToggleUi = function (button, theme) {
		if (!button) {
			return;
		}
		var isDark = theme === 'dark';
		var nextLabel = isDark ? 'Mode Siang' : 'Mode Malam';
		button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
		button.setAttribute('aria-label', isDark ? 'Aktifkan mode siang' : 'Aktifkan mode malam');
		button.title = isDark ? 'Ganti ke mode siang' : 'Ganti ke mode malam';
		var label = button.querySelector('.theme-global-toggle-label');
		if (label) {
			label.textContent = nextLabel;
		}
	};

	var bindGlobalToggle = function (button) {
		if (!button || button.getAttribute('data-theme-toggle-bound') === '1') {
			return;
		}
		button.setAttribute('data-theme-toggle-bound', '1');
		button.addEventListener('click', function () {
			var active = readActiveTheme();
			applyTheme(active === 'dark' ? 'light' : 'dark', true);
		});
		updateToggleUi(button, readActiveTheme());
		window.addEventListener('home-theme-changed', function (event) {
			var nextTheme = '';
			if (event && event.detail && (event.detail.theme === 'dark' || event.detail.theme === 'light')) {
				nextTheme = String(event.detail.theme);
			} else {
				nextTheme = readActiveTheme();
			}
			updateToggleUi(button, nextTheme);
		});
	};

	var ensureGlobalToggle = function () {
		var nativeToggle = document.getElementById('themeToggleButton');
		var shouldUseMobileCompanion = false;
		var shouldBindNativeToggle = false;
		if (document.body && document.body.getAttribute('data-theme-mobile-toggle') === '1') {
			shouldUseMobileCompanion = true;
		}
		if (document.body && document.body.getAttribute('data-theme-native-toggle') === '1') {
			shouldBindNativeToggle = true;
		}
		if (nativeToggle && shouldBindNativeToggle) {
			bindGlobalToggle(nativeToggle);
		}
		if (nativeToggle) {
			if (!shouldBindNativeToggle) {
				var nonManagedButton = document.getElementById('globalThemeToggleButton');
				if (nonManagedButton && nonManagedButton.parentNode) {
					nonManagedButton.parentNode.removeChild(nonManagedButton);
				}
				return;
			}
			if (!shouldUseMobileCompanion) {
				var existingButton = document.getElementById('globalThemeToggleButton');
				if (existingButton && existingButton.parentNode) {
					existingButton.parentNode.removeChild(existingButton);
				}
				return;
			}
		}
		var host = document.body || document.documentElement;
		if (!host) {
			return;
		}
		var button = document.getElementById('globalThemeToggleButton');
		if (!button) {
			button = document.createElement('button');
			button.type = 'button';
			button.id = 'globalThemeToggleButton';
			button.className = 'theme-global-toggle-btn';
			button.innerHTML = '<span class="theme-global-toggle-dot" aria-hidden="true"></span><span class="theme-global-toggle-label">Mode Malam</span>';
			host.appendChild(button);
		}
		if (shouldUseMobileCompanion) {
			button.classList.add('theme-global-toggle-btn-mobile-only');
		} else {
			button.classList.remove('theme-global-toggle-btn-mobile-only');
		}
		bindGlobalToggle(button);
	};

	var storedTheme = readFromStorage();
	if (storedTheme !== 'dark' && storedTheme !== 'light') {
		var rootTheme = String(root.getAttribute('data-theme') || '').toLowerCase();
		if (rootTheme === 'dark' || rootTheme === 'light') {
			storedTheme = rootTheme;
		}
	}
	if (storedTheme !== 'dark' && storedTheme !== 'light') {
		storedTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
			? 'dark'
			: 'light';
	}

	applyTheme(storedTheme, true);

	window.__homeThemeManager = {
		applyTheme: applyTheme,
		readTheme: readFromStorage,
		persistTheme: persistTheme
	};

	if (document.body) {
		ensureGlobalToggle();
	} else {
		document.addEventListener('DOMContentLoaded', ensureGlobalToggle, { once: true });
	}
})();
