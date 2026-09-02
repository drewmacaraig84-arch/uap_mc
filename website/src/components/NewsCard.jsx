import './NewsCard.css';

export default function NewsCard({ item, onClick }) {
  const date = item.date_posted
    ? new Date(item.date_posted).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric'
      })
    : '';

  const hasImage = Boolean(item.image_url);

  return (
    <article
      className={`news-card card ${hasImage ? 'has-cover' : ''}`}
      onClick={() => onClick && onClick(item)}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      onKeyDown={(e) => {
        if (onClick && (e.key === 'Enter' || e.key === ' ')) {
          e.preventDefault();
          onClick(item);
        }
      }}
    >
      {hasImage && (
        <div className="news-card-cover-wrap">
          <img
            src={item.image_url}
            alt={item.title}
            className="news-card-cover-img"
            loading="lazy"
            onError={(e) => {
              e.currentTarget.parentElement.style.display = 'none';
            }}
          />
          <div className="news-card-cover-overlay" />
          <div className="news-card-date-badge">{date}</div>
        </div>
      )}

      <div className="news-card-body">
        {!hasImage && <div className="news-card-date">{date}</div>}
        <h3 className="news-card-title">{item.title}</h3>
        <p className="news-card-summary">{item.summary}</p>
        <div className="news-card-more">
          <span>Read Announcement</span>
          <span className="news-card-arrow">→</span>
        </div>
      </div>
    </article>
  );
}


