import { useState, useEffect } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import './Navbar.css';

const NAV_LINKS = [
  { to: '/',         label: 'Home' },
  { to: '/directory',label: 'Directory' },
  { to: '/about',    label: 'About' },
  { to: '/news',     label: 'News' },
  { to: '/partners', label: 'Partners' },
  { to: '/contact',  label: 'Contact' },
];

export default function Navbar() {
  const { data: settings } = useApi('/api/settings.php');
  const logoSrc = settings?.logo || '/public/logo.jpg';
  const orgName = settings?.org_name || 'UAP Mindoro';
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();

  // Close menu on route change
  useEffect(() => { setMenuOpen(false); }, [location]);

  // Add/remove scrolled class
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <header className={`navbar${scrolled ? ' scrolled' : ''}`}>
      <div className="navbar-inner container-wide">
        {/* Brand */}
        <Link to="/" className="navbar-brand">
          <img 
            src={logoSrc} 
            alt={orgName} 
            className="navbar-logo" 
            onError={(e) => {
              if (!e.currentTarget.src.endsWith('/logo.jpg')) {
                e.currentTarget.src = '/logo.jpg';
              }
            }} 
          />
          <div className="navbar-brand-text">
            <span className="navbar-brand-title">{orgName.split(' ').slice(0, 2).join(' ')}</span>
            <span className="navbar-brand-sub">Chapter 121</span>
          </div>
        </Link>

        {/* Desktop Nav */}
        <nav className="navbar-links">
          {NAV_LINKS.map(({ to, label }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              className={({ isActive }) => `navbar-link${isActive ? ' active' : ''}`}
            >
              {label}
            </NavLink>
          ))}
        </nav>

        {/* CTA + Hamburger */}
        <div className="navbar-actions">
          <a
            href="/auth/login.php"
            className="btn btn-gold navbar-cta"
            target="_self"
          >
            Member Portal
          </a>
          <button
            className={`navbar-hamburger${menuOpen ? ' open' : ''}`}
            onClick={() => setMenuOpen(!menuOpen)}
            aria-label="Toggle menu"
          >
            <span /><span /><span />
          </button>
        </div>
      </div>

      {/* Mobile Drawer */}
      <div className={`navbar-drawer${menuOpen ? ' open' : ''}`}>
        {NAV_LINKS.map(({ to, label }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/'}
            className={({ isActive }) => `drawer-link${isActive ? ' active' : ''}`}
          >
            {label}
          </NavLink>
        ))}
        <a
          href="/auth/login.php"
          className="btn btn-gold drawer-cta"
        >
          Member Portal
        </a>
      </div>
    </header>
  );
}
