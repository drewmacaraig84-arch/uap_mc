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
</script>
</body>
</html>
