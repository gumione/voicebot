import { createContext, useContext, useState, useEffect, type ReactNode } from 'react';
import authApi, { type MeUser } from '../api/authApi';

interface AuthContextType {
    user: MeUser | null;
    token: string | null;
    login: (email: string, password: string) => Promise<void>;
    logout: () => void;
    isLoading: boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
    const [user, setUser] = useState<MeUser | null>(null);
    const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'));
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        if (token) {
            authApi.me()
                .then((res) => setUser(res.data.data))
                .catch(() => {
                    localStorage.removeItem('token');
                    setToken(null);
                })
                .finally(() => setIsLoading(false));
        } else {
            // No token: nothing to verify, mark auth bootstrap as done.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setIsLoading(false);
        }
    }, [token]);

    const login = async (email: string, password: string) => {
        const res = await authApi.login({ email, password });
        const accessToken = res.data.token;
        localStorage.setItem('token', accessToken);
        setToken(accessToken);
    };

    const logout = () => {
        localStorage.removeItem('token');
        setToken(null);
        setUser(null);
    };

    return (
        <AuthContext.Provider value={{ user, token, login, logout, isLoading }}>
            {children}
        </AuthContext.Provider>
    );
};

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
};
