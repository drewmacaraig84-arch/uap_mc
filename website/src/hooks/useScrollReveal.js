import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

/**
 * Global Scroll Reveal Hook.
 * Observes all target elements and applies the `.is-revealed` class
 * with pop-up animation when they scroll into the viewport.
 */
export function useScrollReveal() {
  const location = useLocation();

  useEffect(() => {
    // Scroll window to top on route change
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });

    const SELECTORS = [
      '.reveal',
      '.reveal-pop',
      '.reveal-scale',
      '.reveal-left',
      '.reveal-right',
      '.reveal-stagger',
      '[data-reveal]',
      '.card',
      '.section-header',
      '.stat-item',
      '.mission-card',
      '.timeline-item',
      '.about-feature',
      '.about-visual',
      '.contact-card',
      '.contact-form-wrap',
      '.profile-hero',
      '.profile-section',
      '.gallery-thumb',
      '.cta-section .container',
      '.stats-grid',
      '.featured-grid',
    ].join(',');

    const observerOptions = {
      root: null,
      rootMargin: '0px 0px -50px 0px',
      threshold: 0.08,
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          // Once revealed, unobserve to keep performance high
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    const observeElements = () => {
      const elements = document.querySelectorAll(SELECTORS);
      elements.forEach((el) => {
        // If element doesn't have a specific reveal class, default to base reveal
        if (
          !el.classList.contains('reveal') &&
          !el.classList.contains('reveal-pop') &&
          !el.classList.contains('reveal-scale') &&
          !el.classList.contains('reveal-left') &&
          !el.classList.contains('reveal-right') &&
          !el.classList.contains('reveal-stagger') &&
          !el.hasAttribute('data-reveal')
        ) {
          el.classList.add('reveal');
        }

        if (!el.classList.contains('is-revealed')) {
          observer.observe(el);
        }
      });
    };

    // Initial scan after render
    const timer = setTimeout(observeElements, 60);

    // MutationObserver to auto-catch dynamically rendered content (API responses)
    const mutationObserver = new MutationObserver(() => {
      observeElements();
    });

    mutationObserver.observe(document.body, {
      childList: true,
      subtree: true,
    });

    return () => {
      clearTimeout(timer);
      observer.disconnect();
      mutationObserver.disconnect();
    };
  }, [location.pathname, location.search]);
}
