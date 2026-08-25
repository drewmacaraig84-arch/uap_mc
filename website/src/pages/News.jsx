import { useApi } from '../hooks/useApi';
import NewsCard from '../components/NewsCard';
import { IconNewspaper } from '../components/Icons';
import './News.css';

export default function News() {
  const { data: news, loading, error } = useApi('/api/news.php');

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
            {news.map((n) => <NewsCard key={n.id} item={n} />)}
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
    </main>
  );
}
