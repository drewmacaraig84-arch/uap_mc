import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconArrowLeft, IconArrowRight, IconVerified, IconMapPin, IconCalendar, IconQrCode, IconBriefcase, getSocialLinkInfo } from '../components/Icons';
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
            <p className="profile-role">{m.role_title || 'Licensed Architect'}</p>

            <div className="profile-meta">
              {m.company_name && (
                <span className="profile-meta-item">
                  <IconBriefcase size={15} style={{ color: 'var(--c-gold)' }} />
                  <span>{m.company_name}</span>
                </span>
              )}
              {m.location && (
                <span className="profile-meta-item">
                  <IconMapPin size={15} />
                  <span>{m.location}</span>
                </span>
              )}
              <span className="profile-meta-item">
                <IconCalendar size={15} />
                <span>PRC ID: <strong>{m.id_number}</strong></span>
              </span>
            </div>

            {socialInfo && (
              <div style={{ marginTop: 12 }}>
                <a
                  href={socialInfo.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={`btn btn-sm arch-social-${socialInfo.type}`}
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
                  <socialInfo.Icon size={14} />
                  <span>Visit {socialInfo.label} &rarr;</span>
                </a>
              </div>
            )}
          </div>
        </div>

        {/* Details Grid */}
        <div className="profile-details-grid reveal-stagger">
          {/* About / Achievements */}
          <div className="profile-section card">
            <h2 className="profile-section-title">Professional Background</h2>
            {m.achievements ? (
              <p className="muted" style={{ lineHeight: 1.75 }}>{m.achievements}</p>
            ) : (
              <p className="muted">No background information available.</p>
            )}

            {m.awards && (
              <>
                <h3 className="profile-subsection-title" style={{ marginTop: 24 }}>Awards & Recognition</h3>
                <p className="muted" style={{ lineHeight: 1.75 }}>{m.awards}</p>
              </>
            )}
          </div>

          {/* Info card */}
          <div className="profile-section card">
            <h2 className="profile-section-title">Profile Details</h2>
            <dl className="profile-dl">
              <dt>Full Name</dt><dd>{m.name}</dd>
              <dt>PRC ID No.</dt><dd>{m.id_number}</dd>
              {m.company_name && <><dt>Company / Firm</dt><dd>{m.company_name}</dd></>}
              {socialInfo && (
                <>
                  <dt>Showcase Link</dt>
                  <dd>
                    <a
                      href={socialInfo.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ color: 'var(--c-gold)', display: 'inline-flex', alignItems: 'center', gap: 5, textDecoration: 'none', fontWeight: 600 }}
                    >
                      <socialInfo.Icon size={14} />
                      <span>{socialInfo.label} ({socialInfo.url.replace(/^https?:\/\//i, '').split('/')[0]}) &rarr;</span>
                    </a>
                  </dd>
                </>
              )}
              {m.specialty && <><dt>Specialization</dt><dd>{m.specialty}</dd></>}
              {m.location  && <><dt>Location</dt><dd>{m.location}</dd></>}
              {m.role_title && <><dt>Title / Role</dt><dd>{m.role_title}</dd></>}
            </dl>
          </div>
        </div>

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
