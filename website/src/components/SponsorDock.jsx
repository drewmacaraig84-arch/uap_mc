import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconStar, IconHandshake } from './Icons';
import './SponsorDock.css';

export default function SponsorDock() {
  const { data: sponsors } = useApi('/api/sponsors.php');
  const [bottomOffset, setBottomOffset] = useState(16);

  useEffect(() => {
    const handleScroll = () => {
      const footer = document.querySelector('footer.footer');
      if (!footer) {
        setBottomOffset(16);
        return;
      }

      const footerRect = footer.getBoundingClientRect();
      const windowHeight = window.innerHeight;

      // If footer top has scrolled into the viewport, stop dock above footer
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

  const items = sponsors && Array.isArray(sponsors) ? sponsors : [];

  return (
    <aside
      className="sponsor-rail"
      aria-label="Chapter Sponsors & Partners"
      style={{ bottom: `${bottomOffset}px` }}
    >
      <Link to="/sponsors" className="sponsor-rail-header" title="View all Sponsors & Partners">
        <IconStar size={13} className="sponsor-rail-star" />
        <span className="sponsor-rail-title">Sponsors &amp; Partners</span>
      </Link>

      <div className="sponsor-rail-list">
        {items.length > 0 ? (
          items.map((sponsor) => (
            <Link
              key={sponsor.id}
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
    </aside>
  );
}
