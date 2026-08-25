import { useEffect, useState } from 'react';
import { IconX, IconChevronLeft, IconChevronRight } from './Icons';
import './Lightbox.css';

export default function Lightbox({ images, startIndex = 0, onClose }) {
  const [current, setCurrent] = useState(startIndex);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowRight') setCurrent((i) => (i + 1) % images.length);
      if (e.key === 'ArrowLeft') setCurrent((i) => (i - 1 + images.length) % images.length);
    };
    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [images.length, onClose]);

  if (!images?.length) return null;
  const img = images[current];

  return (
    <div className="lightbox-backdrop" onClick={onClose}>
      <button className="lightbox-close" onClick={onClose} aria-label="Close">
        <IconX size={20} />
      </button>

      {images.length > 1 && (
        <>
          <button className="lightbox-nav prev" onClick={(e) => { e.stopPropagation(); setCurrent((i) => (i - 1 + images.length) % images.length); }} aria-label="Previous">
            <IconChevronLeft size={28} />
          </button>
          <button className="lightbox-nav next" onClick={(e) => { e.stopPropagation(); setCurrent((i) => (i + 1) % images.length); }} aria-label="Next">
            <IconChevronRight size={28} />
          </button>
        </>
      )}

      <div className="lightbox-content" onClick={(e) => e.stopPropagation()}>
        <img src={img.url} alt={img.description || ''} className="lightbox-img" />
        {img.description && <p className="lightbox-caption">{img.description}</p>}
        {images.length > 1 && (
          <p className="lightbox-counter">{current + 1} / {images.length}</p>
        )}
      </div>
    </div>
  );
}
