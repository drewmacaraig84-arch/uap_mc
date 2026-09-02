import { useState, useEffect } from 'react';
import { useApi } from '../hooks/useApi';
import NewsCard from '../components/NewsCard';
import { IconNewspaper } from '../components/Icons';
import './News.css';

export default function News() {
  const { data: news, loading, error } = useApi('/api/news.php');
  const [selectedNews, setSelectedNews] = useState(null);

  // Close modal with Escape key and prevent background scroll
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setSelectedNews(null);
    };
    if (selectedNews) {
      window.addEventListener('keydown', handleKeyDown);
      document.body.style.overflow = 'hidden';
    }
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [selectedNews]);

  const selectedDate = selectedNews?.date_posted
    ? new Date(selectedNews.date_posted).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      })
    : '';

  return (
    <main className="page-container">
      <div className="news-page-hero">
        <div className="container reveal-pop">
          <p className="eyebrow">Latest from the Chapter</p>
          <h1 className="display-2">News &amp; <span className="text-gold">Announcements</span></h1>
          <p className="body-lg muted" style={{ marginTop: 12, maxWidth: 520 }}>
            Stay up to date with the latest chapter news, events, and professional development opportunities.
          </p>
        </div>
      </div>

      <div className="container news-body">
        {error ? (
          <div className="news-state reveal-pop">
            <p className="muted">Unable to load news. Please try again later.</p>
          </div>
        ) : loading ? (
          <div className="grid-3">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="skeleton" style={{ height: 200, borderRadius: 'var(--r-lg)' }} />
            ))}
          </div>
        ) : news?.length > 0 ? (
          <div className="grid-3 reveal-stagger">
            {news.map((n) => (
              <NewsCard key={n.id} item={n} onClick={() => setSelectedNews(n)} />
            ))}
          </div>
        ) : (
          <div className="news-state reveal-pop">
            <div className="news-empty-icon" style={{ color: 'var(--c-gold)' }}>
              <IconNewspaper size={44} />
            </div>
            <h3>No announcements yet</h3>
            <p className="muted">Check back soon for chapter news and updates.</p>
          </div>
        )}
      </div>

      {/* FULL ANNOUNCEMENT MODAL */}
      {selectedNews && (
        <div className="news-modal-backdrop" onClick={() => setSelectedNews(null)}>
          <div
            className={`news-modal-card card ${selectedNews.image_url ? 'has-modal-hero' : ''}`}
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-labelledby="news-modal-heading"
          >
            <button
              type="button"
              className="news-modal-close"
              onClick={() => setSelectedNews(null)}
              aria-label="Close modal"
            >
              &times;
            </button>

            {/* High-Resolution Hero Banner */}
            {selectedNews.image_url && (
              <div className="news-modal-hero-wrap">
                <img
                  src={selectedNews.image_url}
                  alt={selectedNews.title}
                  className="news-modal-hero-img"
                  onError={(e) => {
                    e.currentTarget.parentElement.style.display = 'none';
                  }}
                />
                <div className="news-modal-hero-overlay" />
              </div>
            )}

            <div className="news-modal-body">
              <div className="news-modal-header">
                <span className="news-modal-date">{selectedDate}</span>
                <h2 id="news-modal-heading" className="news-modal-title">
                  {selectedNews.title}
                </h2>
              </div>

              <div className="news-modal-divider" />

              <div className="news-modal-content">
                {selectedNews.summary.split('\n').map((paragraph, index) => (
                  paragraph.trim() ? <p key={index}>{paragraph}</p> : <br key={index} />
                ))}
              </div>

              <div className="news-modal-footer">
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setSelectedNews(null)}
                >
                  Close Announcement
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}


