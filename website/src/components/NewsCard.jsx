import './NewsCard.css';

export default function NewsCard({ item, onClick }) {
  const date = item.date_posted
    ? new Date(item.date_posted).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric'
      })
    : '';

  return (
    <article
      className="news-card card"
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
      <div className="news-card-date">{date}</div>
      <h3 className="news-card-title">{item.title}</h3>
      <p className="news-card-summary">{item.summary}</p>
      <div className="news-card-more">
        <span>Read Announcement</span>
        <span className="news-card-arrow">→</span>
      </div>
    </article>
  );
}

