import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconStar, IconSparkles, IconHandshake, IconChevronDown, IconChevronUp, IconGrid, IconExternalLink } from './Icons';
import './SponsorDock.css';

export default function SponsorDock() {
  const { data: sponsors } = useApi('/api/sponsors.php');
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isPaused, setIsPaused] = useState(false);
  const [openDropdowns, setOpenDropdowns] = useState({});
  const marqueeRef = useRef(null);

  const rawItems = sponsors && Array.isArray(sponsors) ? sponsors : [];

  // Sort: Platinum partners ALWAYS on top, followed by display_order
  const sortedItems = [...rawItems].sort((a, b) => {
    const aPlat = a.is_platinum === 1 ? 1 : 0;
    const bPlat = b.is_platinum === 1 ? 1 : 0;
    if (bPlat !== aPlat) return bPlat - aPlat;
    return (a.display_order || 0) - (b.display_order || 0);
  });

  const toggleDropdown = (id, e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    setOpenDropdowns((prev) => ({
      ...prev,
      [id]: !prev[id],
    }));
  };

  // Duplicate for seamless infinite auto-slide marquee when items exist
  const marqueeItems = sortedItems.length > 0 
    ? (sortedItems.length < 5 ? [...sortedItems, ...sortedItems, ...sortedItems, ...sortedItems] : [...sortedItems, ...sortedItems]) 
    : [];

  return (
    <>
      {/* COLLAPSED FLOATING TRIGGER PILL (Mobile Only) */}
      {isCollapsed && (
        <button
          type="button"
          className="sponsor-collapsed-pill mobile-only reveal-pop"
          onClick={() => setIsCollapsed(false)}
          aria-label="Expand Featured Partners"
          title="Show Featured Partners"
        >
          <div className="sponsor-pill-inner">
            <span className="sponsor-pill-icon-wrap">
              <IconStar size={13} className="sponsor-pill-star" />
            </span>
            <span className="sponsor-pill-text">Featured Partners</span>
            {sortedItems.length > 0 && (
              <span className="sponsor-pill-badge">{sortedItems.length}</span>
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
        aria-label="Featured Partners"
      >
        {/* Dock Header */}
        <div className="sponsor-rail-header">
          <Link to="/partners" className="sponsor-rail-title-wrap" title="View all Featured Partners">
            <IconStar size={14} className="sponsor-rail-star" />
            <span className="sponsor-rail-title">Featured Partners</span>
            {sortedItems.length > 0 && (
              <span className="sponsor-count-tag">{sortedItems.length}</span>
            )}
          </Link>

          <div className="sponsor-rail-actions">
            <Link to="/partners" className="sponsor-view-all-link" title="Explore all partners and sponsors">
              <span>View All</span>
              <span style={{ fontSize: '10px' }}>&rarr;</span>
            </Link>
            {/* Collapse toggle button strictly for mobile view */}
            <button
              type="button"
              className="sponsor-toggle-btn mobile-only"
              onClick={() => setIsCollapsed(true)}
              aria-label="Minimize Partners"
              title="Minimize"
            >
              <IconChevronDown size={14} />
            </button>
          </div>
        </div>

        {/* Desktop Vertical List (1/4 screen enlarged rail) */}
        <div className="sponsor-rail-list desktop-only">
          {sortedItems.length > 0 ? (
            sortedItems.map((sponsor) => {
              const isPlat = sponsor.is_platinum === 1;
              const products = sponsor.products && Array.isArray(sponsor.products) ? sponsor.products : [];
              const hasProducts = products.length > 0;
              const isExpanded = !!openDropdowns[sponsor.id];

              return (
                <div
                  key={`desk-${sponsor.id}`}
                  className={`sponsor-rail-card-box ${isPlat ? 'is-platinum' : ''}`}
                >
                  <Link
                    to={`/partners/${sponsor.id}`}
                    className="sponsor-rail-card-main"
                    title={`View ${sponsor.name} details & showcase`}
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

                    <div className="sponsor-rail-info">
                      <div className="sponsor-rail-name-row">
                        <span className="sponsor-rail-name">{sponsor.name}</span>
                        {isPlat && (
                          <span className="sponsor-rail-plat-badge" title="Official Platinum Partner">
                            <IconSparkles size={10} />
                            <span>PLATINUM</span>
                          </span>
                        )}
                      </div>

                      <span className={`sponsor-rail-tag ${isPlat ? 'plat-tag' : ''}`}>
                        {isPlat ? 'PLATINUM PARTNER' : 'FEATURED PARTNER'}
                      </span>

                      {/* Products Badge & Dropdown Trigger Button */}
                      {isPlat && hasProducts && (
                        <div className="sponsor-rail-product-trigger-row">
                          <button
                            type="button"
                            className={`sponsor-rail-dropdown-btn ${isExpanded ? 'active' : ''}`}
                            onClick={(e) => toggleDropdown(sponsor.id, e)}
                            title="Toggle promotional products list"
                          >
                            <IconGrid size={11} />
                            <span>{products.length} Product{products.length > 1 ? 's' : ''}</span>
                            <IconChevronDown
                              size={12}
                              className={`dropdown-arrow-icon ${isExpanded ? 'rotated' : ''}`}
                            />
                          </button>
                        </div>
                      )}
                    </div>
                  </Link>

                  {/* Expandable Products Dropdown inside the dock card */}
                  {isPlat && hasProducts && isExpanded && (
                    <div className="sponsor-rail-products-dropdown reveal-pop">
                      <div className="sponsor-rail-dropdown-header">
                        <span>Promotional Products ({products.length}):</span>
                      </div>
                      <div className="sponsor-rail-dropdown-list">
                        {products.map((prod, pIdx) => (
                          <Link
                            key={prod.id || pIdx}
                            to={`/partners/${sponsor.id}`}
                            className="sponsor-rail-dropdown-item"
                          >
                            <div className="sponsor-dropdown-item-img-wrap">
                              {prod.image_url ? (
                                <img src={prod.image_url} alt={prod.name} className="sponsor-dropdown-item-img" />
                              ) : (
                                <IconGrid size={14} className="muted" />
                              )}
                            </div>
                            <div className="sponsor-dropdown-item-text">
                              <span className="sponsor-dropdown-item-name">{prod.name}</span>
                              {prod.description && (
                                <span className="sponsor-dropdown-item-desc">{prod.description}</span>
                              )}
                            </div>
                          </Link>
                        ))}
                      </div>
                      <Link
                        to={`/partners/${sponsor.id}`}
                        className="sponsor-rail-dropdown-footer-link"
                      >
                        <span>View Full Product Showcase</span>
                        <span style={{ fontSize: '11px' }}>&rarr;</span>
                      </Link>
                    </div>
                  )}
                </div>
              );
            })
          ) : (
            <Link to="/partners" className="sponsor-rail-empty-link" title="Partner with UAP Mindoro Chapter">
              <div className="sponsor-rail-empty-icon">
                <IconHandshake size={24} />
              </div>
              <span className="sponsor-rail-empty-text">Partner with Us</span>
              <span className="sponsor-rail-empty-sub">Showcase your brand on our official chapter portal</span>
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
              {marqueeItems.map((sponsor, idx) => {
                const isPlat = sponsor.is_platinum === 1;
                return (
                  <Link
                    key={`mob-${sponsor.id}-${idx}`}
                    to={`/partners/${sponsor.id}`}
                    className={`sponsor-ticker-card ${isPlat ? 'is-platinum-ticker' : ''}`}
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
                    <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                      <span className="sponsor-ticker-name">{sponsor.name}</span>
                      {isPlat && (
                        <span style={{ fontSize: '8.5px', color: '#f5b800', fontWeight: 800 }}>★ PLATINUM</span>
                      )}
                    </div>
                  </Link>
                );
              })}
            </div>
          ) : (
            <div className="sponsor-mobile-empty">
              <Link to="/partners" className="sponsor-mobile-empty-link">
                <IconHandshake size={16} />
                <span>Partner with UAP Mindoro &bull; Inquire for Partnership</span>
              </Link>
            </div>
          )}
        </div>
      </aside>
    </>
  );
}
