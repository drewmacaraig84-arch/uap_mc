</div>
<footer class="footer">
    <div class="footer-content">
        <div>
            © <?php echo date('Y'); ?> United Architects of the Philippines – Mindoro Chapter
        </div>

        <div class="developer-credit">
            UAP Mindoro Portal | Developed by <strong>Drew Macaraig</strong>
        </div>
    </div>
</footer>
<script>
// Theme Management: follow system preference unless the user has explicitly chosen a theme.
function initTheme() {
  const html = document.getElementById('htmlElement');
  const storedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = storedTheme || (systemPrefersDark ? 'dark' : 'light');
  html.setAttribute('data-theme', theme);
  document.documentElement.style.colorScheme = theme;
  updateThemeSwitch(theme);
}

function syncThemeToSystem() {
  const html = document.getElementById('htmlElement');
  if (!localStorage.getItem('theme')) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = prefersDark ? 'dark' : 'light';
    html.setAttribute('data-theme', theme);
    document.documentElement.style.colorScheme = theme;
    updateThemeSwitch(theme);
  }
}

function updateThemeSwitch(theme) {
  const switchButton = document.getElementById('themeSwitchButton');
  if (!switchButton) return;
  const isDark = theme === 'dark';
  switchButton.classList.toggle('active', isDark);
  switchButton.setAttribute('aria-checked', String(isDark));
}

function toggleThemeFromMenu() {
  const html = document.getElementById('htmlElement');
  const currentTheme = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', nextTheme);
  document.documentElement.style.colorScheme = nextTheme;
  localStorage.setItem('theme', nextTheme);
  updateThemeSwitch(nextTheme);
}

const systemThemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
if (systemThemeQuery.addEventListener) {
  systemThemeQuery.addEventListener('change', syncThemeToSystem);
} else if (systemThemeQuery.addListener) {
  systemThemeQuery.addListener(syncThemeToSystem);
}

// Initialize theme switch UI on page load (theme is already applied in HEAD)
updateThemeSwitch(document.getElementById('htmlElement').getAttribute('data-theme'));

// Search system: narrow visible navigation items and table rows to the current query.
function setupGlobalSearch() {
  const input = document.getElementById('globalSearchInput');
  if (!input) return;

  const matchText = (value) => {
    const query = value.trim().toLowerCase();
    if (!query) return true;
    return value.toLowerCase().includes(query);
  };

  input.addEventListener('input', function () {
    const query = this.value.trim();

    document.querySelectorAll('.nav-item').forEach((item) => {
      const text = item.textContent || '';
      item.style.display = matchText(text, query) ? '' : 'none';
    });

    document.querySelectorAll('table tbody tr, table tr').forEach((row) => {
      const text = row.textContent || '';
      if (row.querySelector('th')) return;
      row.style.display = matchText(text, query) ? '' : 'none';
    });
  });
}

setupGlobalSearch();

// Toggle mobile menu
function toggleMobileMenu() {
  const menu = document.getElementById('mobileNavLinks');
  if (menu) {
    menu.classList.toggle('open');
  }
}

function setupUserMenu() {
  const trigger = document.getElementById('userMenuTrigger');
  const button = document.getElementById('themeMenuToggle');

  if (!trigger || !button) return;

  trigger.addEventListener('click', function (event) {
    event.stopPropagation();
    trigger.classList.toggle('open');
  });

  button.addEventListener('click', function (event) {
    event.stopPropagation();
    toggleThemeFromMenu();
  });

  document.addEventListener('click', function (event) {
    if (!trigger.contains(event.target)) {
      trigger.classList.remove('open');
    }
  });
}

setupUserMenu();

function setupNotificationBell() {
  const bell = document.getElementById('notificationBell');
  const dropdown = bell ? bell.querySelector('.notification-dropdown') : null;
  
  if (!bell || !dropdown) return;

  // Prevent clicks inside dropdown from closing it
  dropdown.addEventListener('click', function (event) {
    event.stopPropagation();
  });

  // Handle bell button clicks to toggle dropdown
  bell.addEventListener('click', function (event) {
    event.stopPropagation();
    bell.classList.toggle('open');
  });

  // Close dropdown when clicking outside the bell
  document.addEventListener('click', function (event) {
    if (!bell.contains(event.target)) {
      bell.classList.remove('open');
    }
  });

  // Allow notification items to navigate but keep dropdown closing logic
  const notificationItems = dropdown.querySelectorAll('.notification-item');
  notificationItems.forEach(item => {
    item.addEventListener('click', function (event) {
      event.stopPropagation();
      // Allow the navigation to happen from the onclick attribute
      // but make sure the dropdown closes
      bell.classList.remove('open');
    });
  });
}

setupNotificationBell();

document.addEventListener('click', function (event) {
  const menu = document.getElementById('mobileNavLinks');
  const toggle = document.querySelector('.menu-toggle');
  if (!menu || !toggle) return;
  const clickedInside = menu.contains(event.target) || toggle.contains(event.target);
  if (window.innerWidth <= 900 && !clickedInside) {
    menu.classList.remove('open');
  }
});

window.addEventListener('resize', function () {
  const menu = document.getElementById('mobileNavLinks');
  if (menu && window.innerWidth > 900) {
    menu.classList.remove('open');
  }
});

// ================= GLOBAL CONFIRMATION MODAL =================
let pendingConfirmAction = null;

function showConfirmModal(event, targetAction, options = {}) {
  if (event && event.preventDefault) event.preventDefault();
  
  const title = options.title || 'Confirm Action';
  const message = options.message || 'Are you sure you want to proceed?';
  const confirmText = options.confirmText || 'Confirm';
  const btnClass = options.btnClass || 'btn-success';
  const icon = options.icon || '⚠️';

  document.getElementById('uapConfirmTitle').textContent = title;
  document.getElementById('uapConfirmMessage').textContent = message;
  document.getElementById('uapConfirmIcon').textContent = icon;

  const okBtn = document.getElementById('uapConfirmOkBtn');
  okBtn.textContent = confirmText;
  okBtn.className = 'btn btn-sm ' + btnClass;
  
  if (typeof targetAction === 'function') {
    pendingConfirmAction = targetAction;
  } else if (targetAction && targetAction.tagName === 'FORM') {
    pendingConfirmAction = () => targetAction.submit();
  } else {
    pendingConfirmAction = null;
  }

  document.getElementById('uapConfirmModal').style.display = 'flex';
  return false;
}

function closeUapConfirmModal() {
  const modal = document.getElementById('uapConfirmModal');
  if (modal) modal.style.display = 'none';
  pendingConfirmAction = null;
}

document.addEventListener('DOMContentLoaded', function() {
  const okBtn = document.getElementById('uapConfirmOkBtn');
  if (okBtn) {
    okBtn.addEventListener('click', function() {
      if (pendingConfirmAction) {
        const action = pendingConfirmAction;
        closeUapConfirmModal();
        action();
      }
    });
  }

  const modal = document.getElementById('uapConfirmModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeUapConfirmModal();
    });
  }

  // Intercept any form with data-confirm
  document.addEventListener('submit', function(e) {
    const form = e.target;
    if (form && form.getAttribute && form.getAttribute('data-confirm')) {
      if (form.dataset.confirmed === 'true') {
        form.dataset.confirmed = 'false';
        return true;
      }
      e.preventDefault();
      const message = form.getAttribute('data-confirm');
      const title = form.getAttribute('data-confirm-title') || 'Confirm Action';
      const btnText = form.getAttribute('data-confirm-btn') || 'Confirm';
      const btnClass = form.getAttribute('data-confirm-class') || 'btn-success';
      const icon = form.getAttribute('data-confirm-icon') || '⚠️';

      showConfirmModal(e, () => {
        form.dataset.confirmed = 'true';
        form.submit();
      }, { title, message, confirmText: btnText, btnClass, icon });
    }
  });
});
</script>

<!-- Global Confirmation Modal Markup -->
<div id="uapConfirmModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.72);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a);border:1px solid var(--border-color, rgba(255,255,255,0.12));border-radius:14px;max-width:440px;width:100%;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);color:var(--text-primary);">
    <div style="padding:22px 24px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
        <div id="uapConfirmIcon" style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;background:rgba(245,158,11,0.15);flex-shrink:0;">⚠️</div>
        <h3 id="uapConfirmTitle" style="margin:0;font-size:17px;font-weight:700;color:var(--text-primary);">Confirm Action</h3>
      </div>
      <p id="uapConfirmMessage" style="margin:0 0 20px 0;font-size:14px;line-height:1.5;color:var(--text-secondary);"></p>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" onclick="closeUapConfirmModal()" class="btn btn-sm" style="background:transparent;border:1px solid var(--border-color);color:var(--text-primary);padding:8px 16px;">Cancel</button>
        <button type="button" id="uapConfirmOkBtn" class="btn btn-sm btn-success" style="padding:8px 18px;font-weight:700;">Confirm</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>

