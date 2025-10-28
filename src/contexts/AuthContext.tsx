import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

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
}

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  // Fetch user data from JSON
  const refreshUserState = async () => {
    try {
      // Add cache busting to ensure we get fresh data
      const timestamp = new Date().getTime();
      const response = await fetch(`/userProfileData.json?t=${timestamp}`);
      const data = await response.json();
      
      setIsLoggedIn(data.isLoggedIn);
      setUser(data.isLoggedIn ? data.user : null);
      setLoading(false);
    } catch (error) {
      console.error('Failed to refresh user state:', error);
      setLoading(false);
    }
  };

  // Initial load
  useEffect(() => {
    refreshUserState();
  }, []);

  // Expose refresh function globally so PHP can call it
  useEffect(() => {
    // @ts-ignore - Adding to window object
    window.refreshUserState = refreshUserState;
    
    return () => {
      // @ts-ignore - Cleanup
      delete window.refreshUserState;
    };
  }, []);

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
