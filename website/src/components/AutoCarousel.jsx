import { useState, useEffect, useRef, useCallback } from 'react';
import { IconArrowLeft, IconArrowRight } from './Icons';
import './AutoCarousel.css';

export default function AutoCarousel({
  items = [],
  renderItem,
  cardMinWidth = 300,
  gap = 24,
  autoSlideInterval = 3000,
  className = '',
  emptyText = 'No items available.'
}) {
  const containerRef = useRef(null);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const [visibleCount, setVisibleCount] = useState(1);
  const [itemWidth, setItemWidth] = useState(cardMinWidth);
  const [touchStartX, setTouchStartX] = useState(null);

  // Measure container and compute visible cards & actual item width
  const updateDimensions = useCallback(() => {
    if (!containerRef.current) return;
    const containerWidth = containerRef.current.clientWidth;
    if (containerWidth <= 0) return;

    // Calculate how many cards fit in the current container width
    const count = Math.max(1, Math.floor((containerWidth + gap) / (cardMinWidth + gap)));
    const actualVisible = Math.min(count, items.length > 0 ? items.length : 1);
    setVisibleCount(actualVisible);

    // If items can all fit, divide width equally among them or use max size
    if (items.length > 0 && items.length <= count) {
      // Fit all items across the width or keep cardMinWidth with max limit
      const calcWidth = Math.min(380, Math.floor((containerWidth - (actualVisible - 1) * gap) / actualVisible));
      setItemWidth(Math.max(cardMinWidth, calcWidth));
    } else {
      // Calculate responsive card width for sliding track
      const calcWidth = Math.floor((containerWidth - (actualVisible - 1) * gap) / actualVisible);
      setItemWidth(Math.max(cardMinWidth, calcWidth));
    }
  }, [items.length, cardMinWidth, gap]);

  useEffect(() => {
    updateDimensions();
    const ro = new ResizeObserver(() => updateDimensions());
    if (containerRef.current) {
      ro.observe(containerRef.current);
    }
    window.addEventListener('resize', updateDimensions);
    return () => {
      ro.disconnect();
      window.removeEventListener('resize', updateDimensions);
    };
  }, [updateDimensions]);

  const maxIndex = Math.max(0, items.length - visibleCount);
  const canSlide = items.length > visibleCount;

  // Auto-slide effect
  useEffect(() => {
    if (!canSlide || isPaused || autoSlideInterval <= 0) return;

    const timer = setInterval(() => {
      setCurrentIndex((prev) => (prev >= maxIndex ? 0 : prev + 1));
    }, autoSlideInterval);

    return () => clearInterval(timer);
  }, [canSlide, isPaused, maxIndex, autoSlideInterval]);

  // Adjust index if visible count changes
  useEffect(() => {
    if (currentIndex > maxIndex) {
      setCurrentIndex(maxIndex);
    }
  }, [maxIndex, currentIndex]);

  const handlePrev = () => {
    setCurrentIndex((prev) => (prev <= 0 ? maxIndex : prev - 1));
  };

  const handleNext = () => {
    setCurrentIndex((prev) => (prev >= maxIndex ? 0 : prev + 1));
  };

  // Touch swipe handlers
  const handleTouchStart = (e) => {
    setTouchStartX(e.touches[0].clientX);
    setIsPaused(true);
  };

  const handleTouchMove = (e) => {
    if (touchStartX === null) return;
    const diff = touchStartX - e.touches[0].clientX;
    if (Math.abs(diff) > 50) {
      if (diff > 0) {
        handleNext();
      } else {
        handlePrev();
      }
      setTouchStartX(null);
    }
  };

  const handleTouchEnd = () => {
    setTouchStartX(null);
    setIsPaused(false);
  };

  if (!items || items.length === 0) {
    return (
      <div className="carousel-empty">
        <p className="muted">{emptyText}</p>
      </div>
    );
  }

  // Calculate translation offset
  const offset = currentIndex * (itemWidth + gap);

  return (
    <div
      className={`auto-carousel-wrapper ${className}`}
      ref={containerRef}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
      onTouchStart={handleTouchStart}
      onTouchMove={handleTouchMove}
      onTouchEnd={handleTouchEnd}
    >
      {/* Sliding Viewport */}
      <div className={`auto-carousel-viewport ${!canSlide ? 'static-view' : ''}`}>
        <div
          className="auto-carousel-track"
          style={{
            transform: canSlide ? `translateX(-${offset}px)` : 'none',
            gap: `${gap}px`,
            justifyContent: !canSlide ? 'center' : 'flex-start'
          }}
        >
          {items.map((item, idx) => (
            <div
              key={item.id || idx}
              className="auto-carousel-slide"
              style={{
                width: !canSlide && items.length === 1 ? 'min(100%, 360px)' : `${itemWidth}px`,
                flex: `0 0 ${!canSlide && items.length === 1 ? 'min(100%, 360px)' : `${itemWidth}px`}`
              }}
            >
              {renderItem(item, idx)}
            </div>
          ))}
        </div>
      </div>

      {/* Navigation Arrows & Indicators (rendered only if items overflow view) */}
      {canSlide && (
        <div className="auto-carousel-controls">
          <button
            type="button"
            className="carousel-nav-btn prev"
            onClick={handlePrev}
            aria-label="Previous Slide"
            title="Previous"
          >
            <IconArrowLeft size={16} />
          </button>

          <div className="carousel-dots" role="tablist">
            {Array.from({ length: maxIndex + 1 }).map((_, i) => (
              <button
                key={i}
                type="button"
                className={`carousel-dot ${i === currentIndex ? 'active' : ''}`}
                onClick={() => setCurrentIndex(i)}
                aria-label={`Go to slide ${i + 1}`}
                role="tab"
                aria-selected={i === currentIndex}
              />
            ))}
          </div>

          <button
            type="button"
            className="carousel-nav-btn next"
            onClick={handleNext}
            aria-label="Next Slide"
            title="Next"
          >
            <IconArrowRight size={16} />
          </button>
        </div>
      )}
    </div>
  );
}
