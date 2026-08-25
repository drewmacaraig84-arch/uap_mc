import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import Lightbox from '../components/Lightbox';
import { IconArrowLeft, IconVerified, IconMapPin, IconCalendar, IconEye, IconQrCode } from '../components/Icons';
import './Profile.css';

export default function Profile() {
  const { id } = useParams();
  const { data: m, loading, error } = useApi(`/api/member.php?id=${id}`);
  const [lightboxIdx, setLightboxIdx] = useState(null);

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
            {m.photo_url ? (
              <img src={m.photo_url} alt={m.name} className="profile-photo" />
            ) : (
              <div className="profile-initials">{initials}</div>
            )}
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

            {m.qr_url && (
              <div className="profile-qr">
                <img src={m.qr_url} alt="Payment QR" />
                <span className="eyebrow" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                  <IconQrCode size={12} />
                  <span>Payment QR</span>
                </span>
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
              {m.specialty && <><dt>Specialization</dt><dd>{m.specialty}</dd></>}
              {m.location  && <><dt>Location</dt><dd>{m.location}</dd></>}
              {m.role_title && <><dt>Title / Role</dt><dd>{m.role_title}</dd></>}
            </dl>
          </div>
        </div>

        {/* Photo Gallery */}
        {m.gallery?.length > 0 && (
          <div className="profile-gallery-section reveal-pop">
            <h2 className="heading-1" style={{ marginBottom: 24 }}>Project <span className="text-gold">Gallery</span></h2>
            <div className="profile-gallery reveal-stagger">
              {m.gallery.map((img, i) => (
                <button key={i} className="gallery-thumb" onClick={() => setLightboxIdx(i)}>
                  <img src={img.url} alt={img.description || `Project ${i + 1}`} />
                  <div className="gallery-thumb-overlay">
                    <IconEye size={26} color="white" />
                  </div>
                </button>
              ))}
            </div>
          </div>
        )}

        {lightboxIdx !== null && (
          <Lightbox images={m.gallery} startIndex={lightboxIdx} onClose={() => setLightboxIdx(null)} />
        )}
      </div>
    </main>
  );
}
