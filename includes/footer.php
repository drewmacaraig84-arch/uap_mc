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
// Theme Management
function initTheme() {
  const savedTheme = localStorage.getItem('theme') || 'light';
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = savedTheme || (prefersDark ? 'dark' : 'light');
  applyTheme(theme);
}

function applyTheme(theme) {
  const html = document.getElementById('htmlElement');
  const toggle = document.getElementById('themeToggle');
  
  if (theme === 'dark') {
    html.setAttribute('data-theme', 'dark');
    if (toggle) toggle.textContent = '☀️';
    localStorage.setItem('theme', 'dark');
  } else {
    html.removeAttribute('data-theme');
    if (toggle) toggle.textContent = '🌙';
    localStorage.setItem('theme', 'light');
  }
}

function toggleTheme() {
  const html = document.getElementById('htmlElement');
  const currentTheme = html.getAttribute('data-theme');
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  applyTheme(newTheme);
}

// Initialize theme on page load
initTheme();

// Toggle mobile menu
function toggleMobileMenu() {
  const menu = document.getElementById('mobileNavLinks');
  if (menu) {
    menu.classList.toggle('open');
  }
}

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
