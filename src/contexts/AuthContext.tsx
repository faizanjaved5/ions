import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';

interface Notification {
  id: number;
  message: string;
  time: string;
  read: boolean;
}

interface MenuItem {
  label: string;
  link: string;
  icon: string;
}

interface User {
  name: string;
  email: string;
  avatar: string;
  notifications: Notification[];
  menuItems: MenuItem[];
}

interface AuthContextType {
  isLoggedIn: boolean;
  user: User | null;
  refreshUserState: () => Promise<void>;
  logout: () => void;
  login: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
  userDataUrl?: string;
  initialUserData?: { isLoggedIn: boolean; user?: User | null };
}

export const AuthProvider = ({ children, userDataUrl, initialUserData }: AuthProviderProps) => {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Fetch user data from JSON
  const refreshUserState = useCallback(async () => {
    try {
      // If no URL is provided, but initial data exists, just re-apply it
      if (!userDataUrl && initialUserData) {
        setIsLoggedIn(!!initialUserData.isLoggedIn);
        setUser(initialUserData.isLoggedIn ? (initialUserData.user || null) : null);
        setLoading(false);
        return;
      }
      // Add cache busting to ensure we get fresh data
      const timestamp = new Date().getTime();
      const url = (userDataUrl && userDataUrl.length > 0) ? userDataUrl : '/userProfileData.json';
      const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}t=${timestamp}`);
      const data = await response.json();
      
      setIsLoggedIn(data.isLoggedIn);
      setUser(data.isLoggedIn ? data.user : null);
      setLoading(false);
    } catch (error) {
      console.error('Failed to refresh user state:', error);
      setLoading(false);
    }
  }, [userDataUrl, initialUserData]);

  // Initial load
  useEffect(() => {
    if (initialUserData) {
      setIsLoggedIn(!!initialUserData.isLoggedIn);
      setUser(initialUserData.isLoggedIn ? (initialUserData.user || null) : null);
      setLoading(false);
      return;
    }
    refreshUserState();
  }, [initialUserData, refreshUserState]);

  // Expose refresh function globally so PHP can call it
  useEffect(() => {
    window.refreshUserState = refreshUserState;
    
    return () => {
      delete window.refreshUserState;
    };
  }, [refreshUserState]);

  const logout = () => {
    setIsLoggedIn(false);
    setUser(null);
    // Your PHP logout endpoint would be called here
    // Then refresh to get updated JSON
    setTimeout(() => refreshUserState(), 100);
  };

  const login = () => {
    // Optimistically set logged in while backend updates JSON
    setIsLoggedIn(true);
    // Then we refresh to get the new data
    setTimeout(() => refreshUserState(), 100);
  };

  return (
    <AuthContext.Provider value={{ isLoggedIn, user, refreshUserState, logout, login }}>
      {children}
    </AuthContext.Provider>
  );
};
