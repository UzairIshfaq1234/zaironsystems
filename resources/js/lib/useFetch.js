import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Builds a `useFetch(path, params)` hook bound to one API client (the admin's or the portal's).
 * GET `path` with `params`; refetches when either changes. Keeps the previous data while
 * reloading (so tables and charts don't flash) and ignores out-of-order responses.
 */
export function createUseFetch(api) {
    return function useFetch(path, params) {
        const [state, setState] = useState({ data: null, error: null, loading: true });
        const key = JSON.stringify(params ?? {});
        const latest = useRef(0);

        const load = useCallback(() => {
            if (!path) return;
            const id = ++latest.current;
            setState((s) => ({ ...s, loading: true, error: null }));
            api.get(path, JSON.parse(key))
                .then((data) => id === latest.current && setState({ data, error: null, loading: false }))
                .catch((error) => id === latest.current && setState((s) => ({ ...s, error, loading: false })));
        }, [path, key]);

        useEffect(load, [load]);

        const setData = useCallback((update) => setState((s) => ({ ...s, data: typeof update === 'function' ? update(s.data) : update })), []);

        return { ...state, reload: load, setData };
    };
}

export function useDebounced(value, delay = 300) {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(t);
    }, [value, delay]);
    return debounced;
}