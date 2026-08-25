import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconStar, IconHandshake, IconChevronDown, IconChevronUp, IconChevronRight, IconChevronLeft } from './Icons';
import './SponsorDock.css';

export default function SponsorDock() {
  const { data: sponsors } = useApi('/api/sponsors.php');
  const [bottomOffset, setBottomOffset] = useState(16);
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isPaused, setIsPaused] = useState(false);
  const marqueeRef = useRef(null);

  // Calculate footer overlap on desktop
  useEffect(() => {
    const handleScroll = () => {
      const footer = document.querySelector('footer.footer');
      if (!footer) {
        setBottomOffset(16);
        return;
      }

      const footerRect = footer.getBoundingClientRect();
      const windowHeight = window.innerHeight;

      if (footerRect.top < windowHeight) {
        const overlap = windowHeight - footerRect.top;
        setBottomOffset(overlap + 16);
      } else {
        setBottomOffset(16);
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll, { passive: true });
    handleScroll();

    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('resize', handleScroll);
    };
  }, []);

  const rawItems = sponsors && Array.isArray(sponsors) ? sponsors : [];
  // Duplicate for seamless infinite auto-slide marquee when items exist
  const marqueeItems = rawItems.length > 0 
    ? (rawItems.length < 5 ? [...rawItems, ...rawItems, ...rawItems, ...rawItems] : [...rawItems, ...rawItems]) 
    : [];

  return (
    <>
      {/* COLLAPSED FLOATING TRIGGER PILL (Mobile Only) */}
      {isCollapsed && (
        <button
          type="button"
          className="sponsor-collapsed-pill mobile-only reveal-pop"
          onClick={() => setIsCollapsed(false)}
          aria-label="Expand Sponsors & Partners"
          title="Show Sponsors & Partners"
        >
          <div className="sponsor-pill-inner">
            <span className="sponsor-pill-icon-wrap">
              <IconStar size={13} className="sponsor-pill-star" />
            </span>
            <span className="sponsor-pill-text">Sponsors &amp; Partners</span>
            {rawItems.length > 0 && (
              <span className="sponsor-pill-badge">{rawItems.length}</span>
            )}
            <span className="sponsor-pill-arrow">
              <IconChevronUp size={14} />
            </span>
          </div>
        </button>
      )}

      {/* EXPANDED SPONSOR DOCK / RAIL */}
      <aside
        className={`sponsor-rail${isCollapsed ? ' mobile-collapsed' : ''}`}
        aria-label="Chapter Sponsors & Partners"
        style={{ bottom: `${bottomOffset}px` }}
      >
        {/* Dock Header */}
        <div className="sponsor-rail-header">
          <Link to="/sponsors" className="sponsor-rail-title-wrap" title="View all Sponsors & Partners">
            <IconStar size={13} className="sponsor-rail-star" />
            <span className="sponsor-rail-title">Sponsors &amp; Partners</span>
            {rawItems.length > 0 && (
              <span className="sponsor-count-tag">{rawItems.length}</span>
            )}
          </Link>

          <div className="sponsor-rail-actions">
            <Link to="/sponsors" className="sponsor-view-all-link mobile-only">
              View All
            </Link>
            {/* Collapse toggle button strictly for mobile view */}
            <button
              type="button"
              className="sponsor-toggle-btn mobile-only"
              onClick={() => setIsCollapsed(true)}
              aria-label="Minimize Sponsors"
              title="Minimize"
            >
              <IconChevronDown size={14} />
            </button>
          </div>
        </div>

        {/* Desktop Vertical List */}
        <div className="sponsor-rail-list desktop-only">
          {rawItems.length > 0 ? (
            rawItems.map((sponsor) => (
              <Link
                key={`desk-${sponsor.id}`}
                to={`/sponsors#sponsor-${sponsor.id}`}
                className="sponsor-rail-card"
                title={`View ${sponsor.name} profile`}
              >
                <div className="sponsor-rail-img-wrap">
                  {sponsor.logo_url ? (
                    <img src={sponsor.logo_url} alt={sponsor.name} className="sponsor-rail-img" />
                  ) : (
                    <div className="sponsor-rail-placeholder">
                      {sponsor.name.slice(0, 2).toUpperCase()}
                    </div>
                  )}
                </div>
                <span className="sponsor-rail-name">{sponsor.name}</span>
              </Link>
            ))
          ) : (
            <Link to="/sponsors" className="sponsor-rail-empty-link" title="Partner with UAP Mindoro Chapter">
              <div className="sponsor-rail-empty-icon">
                <IconHandshake size={20} />
              </div>
              <span className="sponsor-rail-empty-text">Partner with Us</span>
            </Link>
          )}
        </div>

        {/* Mobile Auto-Slide Marquee Ticker */}
        <div 
          className="sponsor-mobile-ticker-wrap mobile-only"
          onMouseEnter={() => setIsPaused(true)}
          onMouseLeave={() => setIsPaused(false)}
          onTouchStart={() => setIsPaused(true)}
          onTouchEnd={() => setIsPaused(false)}
        >
          {marqueeItems.length > 0 ? (
            <div 
              ref={marqueeRef}
              className={`sponsor-mobile-marquee-track${isPaused ? ' paused' : ''}`}
            >
              {marqueeItems.map((sponsor, idx) => (
                <Link
                  key={`mob-${sponsor.id}-${idx}`}
                  to={`/sponsors#sponsor-${sponsor.id}`}
                  className="sponsor-ticker-card"
                  title={`View ${sponsor.name}`}
                >
                  <div className="sponsor-ticker-img-wrap">
                    {sponsor.logo_url ? (
                      <img src={sponsor.logo_url} alt={sponsor.name} className="sponsor-ticker-img" />
                    ) : (
                      <div className="sponsor-ticker-placeholder">
                        {sponsor.name.slice(0, 2).toUpperCase()}
                      </div>
                    )}
                  </div>
                  <span className="sponsor-ticker-name">{sponsor.name}</span>
                </Link>
              ))}
            </div>
          ) : (
            <div className="sponsor-mobile-empty">
              <Link to="/sponsors" className="sponsor-mobile-empty-link">
                <IconHandshake size={16} />
                <span>Partner with UAP Mindoro &bull; Inquire for Sponsorship</span>
              </Link>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}
