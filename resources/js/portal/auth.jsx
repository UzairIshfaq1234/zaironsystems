import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, setCsrf } from './lib/api';

const AuthContext = createContext(null);

/** The signed-in employee. `employee` is undefined while checking, null when signed out. */
export function AuthProvider({ children }) {
    const [employee, setEmployee] = useState(undefined);

    useEffect(() => {
        api.get('/me')
            .then((data) => setEmployee(data.employee))
            .catch(() => setEmployee(null));
        const onUnauthorized = () => setEmployee(null);
        window.addEventListener('portal:unauthorized', onUnauthorized);
        return () => window.removeEventListener('portal:unauthorized', onUnauthorized);
    }, []);

    const login = useCallback(async (credentials) => {
        const data = await api.post('/login', credentials);
        setEmployee(data.employee);
        return data.employee;
    }, []);

    const logout = useCallback(async () => {
        try {
            const data = await api.post('/logout');
            setCsrf(data?.csrf);
        } finally {
            setEmployee(null);
        }
    }, []);

    /** Re-reads the employee (e.g. after a leave request changes the balances). */
    const refresh = useCallback(() => api.get('/me').then((data) => setEmployee(data.employee)).catch(() => {}), []);

    const value = useMemo(() => ({ employee, login, logout, refresh }), [employee, login, logout, refresh]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
