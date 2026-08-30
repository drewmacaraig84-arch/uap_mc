import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconArrowLeft, IconArrowRight, IconVerified, IconMapPin, IconQrCode, IconBriefcase, getSocialLinkInfo } from '../components/Icons';
import './Profile.css';

export default function Profile() {
  const { id } = useParams();
  const { data: m, loading, error } = useApi(`/api/member.php?id=${id}`);

  if (loading) return (
    <main className="page-container">
      <div className="container profile-loading">
        <div className="skeleton" style={{ height: 300, borderRadius: 'var(--r-lg)', marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 40, width: '60%', borderRadius: 8, marginBottom: 12 }} />
        <div className="skeleton" style={{ height: 24, width: '40%', borderRadius: 8 }} />
      </div>
    </main>
  );

  if (error || !m) return (
    <main className="page-container">
      <div className="container text-center" style={{ padding: 'var(--space-10) 0' }}>
        <h2 className="muted">Profile not found</h2>
        <Link to="/directory" className="btn btn-outline" style={{ marginTop: 24 }}>
          <IconArrowLeft size={16} /> Back to Directory
        </Link>
      </div>
    </main>
  );

  const initials = m.name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
  const socialInfo = m.link_url ? getSocialLinkInfo(m.link_url, m.link_type) : null;

  return (
    <main className="page-container">
      <div className="container profile-container">
        <Link to="/directory" className="profile-back reveal">
          <IconArrowLeft size={16} />
          <span>Back to Directory</span>
        </Link>

        {/* Profile Hero Card */}
        <div className="profile-hero card reveal-pop">
          <div className="profile-photo-wrap">
            {m.photo_url && (
              <img
                src={m.photo_url}
                alt={m.name}
                className="profile-photo"
                onError={(e) => {
                  e.currentTarget.style.display = 'none';
                  const sib = e.currentTarget.parentElement?.querySelector('.profile-initials');
                  if (sib) sib.style.display = 'flex';
                }}
              />
            )}
            <div className="profile-initials" style={{ display: m.photo_url ? 'none' : 'flex' }}>{initials}</div>
          </div>

          <div className="profile-info">
            <div className="profile-badges">
              <span className="badge badge-verified">
                <IconVerified size={13} />
                <span>Good Standing</span>
              </span>
              {m.specialty?.split(',').slice(0, 2).map((s, i) => (
                <span key={i} className="badge badge-gold">{s.trim()}</span>
              ))}
            </div>

            <h1 className="profile-name display-2">{m.name}</h1>

            <div className="profile-company-row">
              <div className="profile-company-text-side">
                {m.company_name ? (
                  <p className="profile-company-highlight">{m.company_name}</p>
                ) : (
                  <p className="profile-company-highlight">{m.role_title || 'Licensed Architect'}</p>
                )}

                <div className="profile-meta">
                  {m.company_name && m.role_title && (
                    <span className="profile-meta-item">
                      <IconBriefcase size={15} style={{ color: 'var(--c-gold)' }} />
                      <span>{m.role_title}</span>
                    </span>
                  )}
                  {m.location && (
                    <span className="profile-meta-item">
                      <IconMapPin size={15} />
                      <span>{m.location}</span>
                    </span>
                  )}
                </div>

                {/* Multiple Social Links */}
                {(() => {
                  const socialLinksList = (m.links && m.links.length > 0)
                    ? m.links.map(l => getSocialLinkInfo(l.url, l.type)).filter(Boolean)
                    : (socialInfo ? [socialInfo] : []);

                  if (socialLinksList.length === 0) return null;

                  return (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                      {socialLinksList.map((s, idx) => (
                        <a
                          key={idx}
                          href={s.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className={`btn btn-sm arch-social-${s.type}`}
                          style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            padding: '6px 14px',
                            borderRadius: 8,
                            fontSize: '0.85rem',
                            fontWeight: 600,
                            textDecoration: 'none'
                          }}
                        >
                          <s.Icon size={14} />
                          <span>Visit {s.label} &rarr;</span>
                        </a>
                      ))}
                    </div>
                  );
                })()}
              </div>

              {/* Company Logo — positioned in lower-right of hero exactly as drawn */}
              <div className="profile-company-logo-wrap">
                {m.company_logo_url ? (
                  <img
                    src={m.company_logo_url}
                    alt={m.company_name || 'Company Logo'}
                    className="profile-company-logo-img"
                    onError={(e) => {
                      e.currentTarget.style.display = 'none';
                      const ph = e.currentTarget.parentElement?.querySelector('.profile-company-logo-placeholder');
                      if (ph) ph.style.display = 'flex';
                    }}
                  />
                ) : null}
                <div
                  className="profile-company-logo-placeholder"
                  style={{ display: m.company_logo_url ? 'none' : 'flex' }}
                >
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--c-gold)', opacity: 0.35 }}>
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18M9 21V9"/>
                  </svg>
                  {m.company_name && (
                    <span className="profile-company-logo-initials">
                      {m.company_name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()}
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Top 2-Column Grid: Professional Background & Profile Details (Same Height) */}
        <div className="profile-details-grid reveal-stagger">
          {/* Professional Background */}
          <div className="profile-section card profile-equal-card">
            <h2 className="profile-section-title">Professional Background</h2>
            {m.achievements ? (
              <p className="muted" style={{ lineHeight: 1.75, whiteSpace: 'pre-line' }}>{m.achievements}</p>
            ) : (
              <p className="muted">No background information available.</p>
            )}
          </div>

          {/* Profile Details */}
          <div className="profile-section card profile-equal-card">
            <h2 className="profile-section-title">Profile Details</h2>
            <dl className="profile-dl">
              <dt>Full Name</dt><dd>{m.name}</dd>
              {m.company_name && <><dt>Company / Firm</dt><dd>{m.company_name}</dd></>}
              {m.location && <><dt>Company / Office Address</dt><dd>{m.location}</dd></>}
              {m.role_title && <><dt>Title / Role</dt><dd>{m.role_title}</dd></>}
              {m.specialty && <><dt>Specialization</dt><dd>{m.specialty}</dd></>}
              {(() => {
                const sList = (m.links && m.links.length > 0)
                  ? m.links.map(l => getSocialLinkInfo(l.url, l.type)).filter(Boolean)
                  : (socialInfo ? [socialInfo] : []);

                if (sList.length === 0) return null;

                return (
                  <>
                    <dt>Showcase {sList.length > 1 ? 'Links' : 'Link'}</dt>
                    <dd style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                      {sList.map((s, idx) => (
                        <a
                          key={idx}
                          href={s.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          style={{ color: 'var(--c-gold)', display: 'inline-flex', alignItems: 'center', gap: 5, textDecoration: 'none', fontWeight: 600 }}
                        >
                          <s.Icon size={14} />
                          <span>{s.label} ({s.url.replace(/^https?:\/\//i, '').split('/')[0]}) &rarr;</span>
                        </a>
                      ))}
                    </dd>
                  </>
                );
              })()}
            </dl>
          </div>
        </div>

        {/* Honors, Distinctions & Awards (Landscape Card Underneath Combined Width) */}
        {m.awards && (
          <div className="profile-awards-landscape card reveal-pop">
            <h2 className="profile-section-title" style={{ color: 'var(--c-gold)' }}>Honors, Distinctions &amp; Awards</h2>
            <p className="muted" style={{ lineHeight: 1.75, fontSize: '0.95rem', whiteSpace: 'pre-line', margin: 0 }}>{m.awards}</p>
          </div>
        )}

        {/* COMPLETED WORKS */}
        {m.projects && m.projects.length > 0 && (
          <div className="profile-completed-works-section reveal-pop">
            <div className="completed-works-header">
              <p className="eyebrow" style={{ letterSpacing: '0.14em', textTransform: 'uppercase', marginBottom: 4 }}>Portfolio &amp; Architectural Practice</p>
              <h2 className="heading-1" style={{ letterSpacing: '0.04em', textTransform: 'uppercase', marginBottom: 24 }}>
                COMPLETED <span className="text-gold">WORKS</span>
              </h2>
            </div>
            
            <div className="completed-works-grid reveal-stagger">
              {m.projects.map((proj, pIdx) => {
                const cover = proj.cover_url || proj.photos?.[0];
                const projId = proj.id || `proj_${pIdx + 1}`;
                return (
                  <Link
                    key={projId}
                    to={`/profile/${id}/project/${projId}`}
                    className="completed-work-card card"
                    aria-label={`View details for ${proj.title}`}
                  >
                    <div className="completed-work-photo-wrap">
                      <img
                        src={cover}
                        alt={proj.title}
                        onError={(e) => {
                          e.currentTarget.onerror = null;
                          e.currentTarget.src = '/uap_logo.jpg';
                        }}
                      />
                      {/* Hover Overlay matching Image 3 */}
                      <div className="completed-work-hover-overlay">
                        <div className="completed-work-hover-content">
                          {proj.category && (
                            <span className="completed-work-hover-cat">{proj.category}</span>
                          )}
                          <h3 className="completed-work-hover-title">{proj.title}</h3>
                          {proj.location && (
                            <p className="completed-work-hover-loc">
                              <IconMapPin size={12} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
                              <span>{proj.location}</span>
                            </p>
                          )}
                          <div className="completed-work-hover-action">
                            <span>View Full Project Details</span>
                            <IconArrowRight size={14} />
                          </div>
                        </div>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </main>
  );
}
