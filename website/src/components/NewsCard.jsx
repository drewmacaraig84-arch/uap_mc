import './NewsCard.css';

export default function NewsCard({ item }) {
  const date = item.date_posted
    ? new Date(item.date_posted).toLocaleDateString('en-PH', {
        year: 'numeric', month: 'long', day: 'numeric'
      })
    : '';

  return (
    <article className="news-card card">
      <div className="news-card-date">{date}</div>
      <h3 className="news-card-title">{item.title}</h3>
      <p className="news-card-summary">{item.summary}</p>
    </article>
  );
}
