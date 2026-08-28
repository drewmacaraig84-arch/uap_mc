import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { IconArrowLeft, IconArrowRight, IconMapPin, IconBuilding, IconVerified } from '../components/Icons';
import './ProjectDetail.css';

export default function ProjectDetail() {
  const { id, projectId } = useParams();
  const { data: m, loading, error } = useApi(`/api/member.php?id=${id}`);
  const [activeSlide, setActiveSlide] = useState(0);
  const [isPaused, setIsPaused] = useState(false);

  // Find the selected project from member's projects list
  const project = m?.projects?.find(
    (p, idx) => String(p.id) === String(projectId) || String(idx + 1) === String(projectId)
  ) || m?.projects?.[0];

  // Combine Cover Photo + All Gallery Photos into the slide show array (deduplicated in order)
  const rawPhotos = [];
  if (project?.cover_url) {
    rawPhotos.push(project.cover_url);
  }
  if (project?.photos && Array.isArray(project.photos)) {
    project.photos.forEach((ph) => {
      if (ph && !rawPhotos.includes(ph)) {
        rawPhotos.push(ph);
      }
    });
  }
  const photos = rawPhotos.length > 0 
    ? rawPhotos 
    : (project?.cover_photo ? [project.cover_photo] : (m?.photo_url ? [m.photo_url] : []));

  const totalPhotos = photos.length;

  // Auto-slide effect for project slideshow (every 3 seconds)
  useEffect(() => {
    if (totalPhotos <= 1 || isPaused) return;
    const timer = setInterval(() => {
      setActiveSlide((prev) => (prev + 1) % totalPhotos);
    }, 3000);
    return () => clearInterval(timer);
  }, [totalPhotos, isPaused]);

  const handlePrev = () => {
    setActiveSlide((prev) => (prev <= 0 ? totalPhotos - 1 : prev - 1));
  };

  const handleNext = () => {
    setActiveSlide((prev) => (prev + 1) % totalPhotos);
  };

  if (loading) {
    return (
      <main className="page-container">
        <div className="container project-detail-loading">
          <div className="skeleton" style={{ height: 480, borderRadius: 'var(--r-xl)', marginBottom: 32 }} />
          <div className="grid-2">
            <div className="skeleton" style={{ height: 180, borderRadius: 12 }} />
            <div className="skeleton" style={{ height: 180, borderRadius: 12 }} />
          </div>
        </div>
      </main>
    );
  }

  if (error || !m || !project) {
    return (
      <main className="page-container">
        <div className="container text-center" style={{ padding: 'var(--space-10) 0' }}>
          <h2 className="muted">Project not found</h2>
          <p className="muted" style={{ marginTop: 8 }}>The requested completed work may have been moved or updated.</p>
          <Link to={`/profile/${id}`} className="btn btn-outline" style={{ marginTop: 24 }}>
            <IconArrowLeft size={16} /> Back to Architect Profile
          </Link>
        </div>
      </main>
    );
  }

  // Parse team lines
  const teamLines = project.project_team 
    ? project.project_team.split(/\r?\n/).map(l => l.trim()).filter(Boolean)
    : (m.name ? [m.name] : []);

  return (
    <main className="page-container project-detail-page">
      <div className="container project-detail-container">
        {/* Navigation Breadcrumb */}
        <div className="project-breadcrumb reveal">
          <Link to={`/profile/${id}`} className="project-back-btn">
            <IconArrowLeft size={16} />
            <span>Back to {m.name}'s Profile</span>
          </Link>
          <div className="project-arch-tag">
            <IconVerified size={14} />
            <span>Certified Chapter Architect</span>
          </div>
        </div>

        {/* HERO IMAGE SHOWCASE (Slideshow / Auto-sliding Carousel) */}
        <section
          className="project-showcase card reveal-pop"
          onMouseEnter={() => setIsPaused(true)}
          onMouseLeave={() => setIsPaused(false)}
        >
          <div className="project-slide-stage">
            {photos.map((url, i) => (
              <div
                key={i}
                className={`project-slide-item ${i === activeSlide ? 'active' : ''}`}
                style={{ opacity: i === activeSlide ? 1 : 0 }}
              >
                <img
                  src={url}
                  alt={`${project.title} - View ${i + 1}`}
                  className="project-slide-image"
                />
              </div>
            ))}

            {/* Previous / Next Controls (if multiple photos) */}
            {totalPhotos > 1 && (
              <>
                <button
                  type="button"
                  className="project-slider-btn prev"
                  onClick={handlePrev}
                  aria-label="Previous Image"
                  title="Previous Image"
                >
                  <IconArrowLeft size={20} />
                </button>
                <button
                  type="button"
                  className="project-slider-btn next"
                  onClick={handleNext}
                  aria-label="Next Image"
                  title="Next Image"
                >
                  <IconArrowRight size={20} />
                </button>

                {/* Counter & Indicator Dots */}
                <div className="project-slider-indicators">
                  <div className="project-slider-dots">
                    {photos.map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        className={`project-dot ${i === activeSlide ? 'active' : ''}`}
                        onClick={() => setActiveSlide(i)}
                        aria-label={`Slide ${i + 1}`}
                      />
                    ))}
                  </div>
                  <span className="project-slider-counter">
                    {activeSlide + 1} / {totalPhotos}
                  </span>
                </div>
              </>
            )}
          </div>

          {/* Thumbnail preview strip if multiple photos */}
          {totalPhotos > 1 && (
            <div className="project-thumbs-strip">
              {photos.map((url, i) => (
                <button
                  key={i}
                  type="button"
                  className={`project-thumb-btn ${i === activeSlide ? 'active' : ''}`}
                  onClick={() => setActiveSlide(i)}
                  title={`View image ${i + 1}`}
                >
                  <img src={url} alt={`Thumbnail ${i + 1}`} />
                </button>
              ))}
            </div>
          )}
        </section>

        {/* EDITORIAL ARCHITECTURAL DETAILS SECTION (Split Layout) */}
        <section className="project-editorial-section card reveal-pop">
          <div className="project-editorial-grid">
            {/* LEFT COLUMN: Meta & Luxury Typography */}
            <div className="project-meta-col">
              <div className="project-category-badge">
                {project.category || 'RESIDENTIAL'}
              </div>
              <h1 className="project-editorial-title">
                {project.title}
              </h1>
              {project.location && (
                <p className="project-editorial-location">
                  <IconMapPin size={13} style={{ display: 'inline', verticalAlign: 'middle', marginRight: 4 }} />
                  <span>{project.location.toUpperCase()}</span>
                </p>
              )}
              
              <div className="project-firm-info">
                {m.company_name && (
                  <p className="project-firm-name">
                    <IconBuilding size={14} /> <span>{m.company_name}</span>
                  </p>
                )}
                <p className="project-lead-architect">
                  Principal Architect: <strong>{m.name}</strong>
                </p>
              </div>
            </div>

            {/* RIGHT COLUMN: Architectural Narrative & Team Credits */}
            <div className="project-narrative-col">
              <div className="project-narrative-content">
                {project.description ? (
                  <p className="project-narrative-text">
                    {project.description}
                  </p>
                ) : (
                  <p className="project-narrative-text muted">
                    Architectural design, space planning, and construction documentation executed with high standards of design excellence.
                  </p>
                )}
              </div>

              {/* PROJECT TEAM BLOCK (Bottom Right) */}
              {teamLines.length > 0 && (
                <div className="project-team-block">
                  <h3 className="project-team-heading">Project Team:</h3>
                  <ul className="project-team-list">
                    {teamLines.map((member, idx) => (
                      <li key={idx} className="project-team-member">{member}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </div>
        </section>

        {/* OTHER COMPLETED WORKS BY THIS ARCHITECT */}
        {m.projects?.length > 1 && (
          <section className="project-more-works reveal-stagger">
            <div className="flex-between" style={{ marginBottom: 20 }}>
              <h2 className="heading-2">More Completed Works by <span className="text-gold">{m.name}</span></h2>
              <Link to={`/profile/${id}`} className="btn btn-outline btn-sm">
                View All Works &rarr;
              </Link>
            </div>
            <div className="project-more-grid">
              {m.projects
                .filter(p => String(p.id) !== String(project.id))
                .slice(0, 3)
                .map((p, idx) => {
                  const pCover = p.cover_url || p.photos?.[0] || m.photo_url;
                  return (
                    <Link
                      key={p.id || idx}
                      to={`/profile/${id}/project/${p.id || idx + 1}`}
                      className="project-more-card card"
                    >
                      <div className="project-more-photo">
                        <img src={pCover} alt={p.title} />
                      </div>
                      <div className="project-more-body">
                        <span className="project-more-cat">{p.category || 'ARCHITECTURE'}</span>
                        <h3 className="project-more-title">{p.title}</h3>
                        {p.location && <p className="project-more-loc">{p.location}</p>}
                      </div>
                    </Link>
                  );
                })}
            </div>
          </section>
        )}
      </div>
    </main>
  );
}
