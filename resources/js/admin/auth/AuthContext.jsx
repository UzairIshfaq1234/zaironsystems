import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, setCsrf } from '../lib/api';

const AuthContext = createContext(null);

/** Holds the signed-in admin and their permissions. `can()` is cosmetic; the server enforces every check. */
export function AuthProvider({ children }) {
    const [user, setUser] = useState(undefined); // undefined = still checking, null = signed out

    useEffect(() => {
        api.get('/me')
            .then((data) => setUser(data.user))
            .catch(() => setUser(null));
        const onUnauthorized = () => setUser(null);
        window.addEventListener('admin:unauthorized', onUnauthorized);
        return () => window.removeEventListener('admin:unauthorized', onUnauthorized);
    }, []);

    const login = useCallback(async (credentials) => {
        const data = await api.post('/login', credentials);
        setUser(data.user);
        return data.user;
    }, []);

    const logout = useCallback(async () => {
        try {
            const data = await api.post('/logout');
            setCsrf(data?.csrf);
        } finally {
            setUser(null);
        }
    }, []);

    const value = useMemo(
        () => ({
            user,
            setUser,
            login,
            logout,
            can: (permission) => !!user?.permissions?.includes(permission),
        }),
        [user, login, logout],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
