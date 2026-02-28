import { useState, useEffect } from 'react';

export interface User {
    id: string;
    nome: string;
    email: string;
    telefone: string;
}

const USER_KEY = 'loggi_user_session';

export const useAuth = () => {
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const savedUser = localStorage.getItem(USER_KEY);
        if (savedUser) {
            try {
                setUser(JSON.parse(savedUser));
            } catch (e) {
                localStorage.removeItem(USER_KEY);
            }
        }
        setLoading(false);
    }, []);

    const login = (userData: User) => {
        setUser(userData);
        localStorage.setItem(USER_KEY, JSON.stringify(userData));
    };

    const logout = () => {
        setUser(null);
        localStorage.removeItem(USER_KEY);
    };

    return { user, login, logout, loading };
};
