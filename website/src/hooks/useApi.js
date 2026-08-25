import { useState } from 'react';
import { useEffect } from 'react';

/**
 * Custom hook for fetching data from PHP API endpoints.
 * @param {string} endpoint - e.g. '/api/members.php'
 * @returns {{ data, loading, error }}
 */
export function useApi(endpoint) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(endpoint, { headers: { Accept: 'application/json' } })
      .then((r) => {
        if (!r.ok) throw new Error(`API error ${r.status}`);
        return r.json();
      })
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

    return () => { cancelled = true; };
  }, [endpoint]);

  return { data, loading, error };
}
