import { useState, useEffect } from 'react';

// In-memory response cache and in-flight promise tracker
const memoryCache = new Map();
const inFlightRequests = new Map();

function getCachedData(endpoint) {
  if (memoryCache.has(endpoint)) {
    return memoryCache.get(endpoint);
  }
  try {
    const raw = localStorage.getItem(`uap_api_${endpoint}`);
    if (raw) {
      const parsed = JSON.parse(raw);
      memoryCache.set(endpoint, parsed);
      return parsed;
    }
  } catch {
    // Ignore storage errors
  }
  return null;
}

function setCachedData(endpoint, data) {
  if (!data) return;
  memoryCache.set(endpoint, data);
  try {
    localStorage.setItem(`uap_api_${endpoint}`, JSON.stringify(data));
  } catch {
    // Ignore storage errors (quota exceeded, etc.)
  }
}

/**
 * Custom hook for fetching data from PHP API endpoints with stale-while-revalidate caching.
 * @param {string} endpoint - e.g. '/api/settings.php'
 * @returns {{ data, loading, error, refetch: () => void }}
 */
export function useApi(endpoint) {
  const cached = getCachedData(endpoint);
  const [data, setData] = useState(cached);
  const [loading, setLoading] = useState(!cached);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;

    // If no cache was present, show loading state
    if (!memoryCache.has(endpoint)) {
      setLoading(true);
    }
    setError(null);

    // Reuse existing in-flight promise if multiple components query the same endpoint simultaneously
    let fetchPromise = inFlightRequests.get(endpoint);
    if (!fetchPromise) {
      fetchPromise = fetch(endpoint, { headers: { Accept: 'application/json' } })
        .then((r) => {
          if (!r.ok) throw new Error(`API error ${r.status}`);
          return r.json();
        })
        .then((d) => {
          setCachedData(endpoint, d);
          return d;
        })
        .finally(() => {
          inFlightRequests.delete(endpoint);
        });
      inFlightRequests.set(endpoint, fetchPromise);
    }

    fetchPromise
      .then((d) => {
        if (!cancelled) {
          setData(d);
          setLoading(false);
        }
      })
      .catch((e) => {
        if (!cancelled) {
          setError(e.message);
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [endpoint]);

  return { data, loading, error };
}

