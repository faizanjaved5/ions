import {
  createContext,
  useContext,
  useState,
  useEffect,
  ReactNode,
} from "react";

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
    throw new Error("useAuth must be used within an AuthProvider");
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

  // Fetch or read user data
  const refreshUserState = async () => {
    try {
      // 1) Inline data provided by embedders (PHP) wins
      if (typeof window !== "undefined" && window.__ION_PROFILE_DATA) {
        const data = window.__ION_PROFILE_DATA as {
          isLoggedIn?: boolean;
          user?: User;
        };
        setIsLoggedIn(Boolean(data?.isLoggedIn));
        setUser(data?.isLoggedIn ? data.user ?? null : null);
        setLoading(false);
        return;
      }

      // 2) Otherwise fetch from configured URL
      const timestamp = new Date().getTime();
      const overrideUrl: string | undefined =
        typeof window !== "undefined" ? window.__ION_PROFILE_HREF : undefined;
      const isLocalhost =
        typeof window !== "undefined" &&
        /^(localhost|127\.0\.0\.1|0\.0\.0\.0)$/i.test(window.location.hostname);
      const baseUrl =
        overrideUrl ||
        (isLocalhost
          ? "/userProfileData.json"
          : "https://iblog.bz/menu/userProfileData.json");
      const response = await fetch(`${baseUrl}?t=${timestamp}`);
      const data = await response.json();

      setIsLoggedIn(data.isLoggedIn);
      setUser(data.isLoggedIn ? data.user : null);
      setLoading(false);
    } catch (error) {
      console.error("Failed to refresh user state:", error);
      setLoading(false);
    }
  };

  // Initial load
  useEffect(() => {
    refreshUserState();
  }, []);

  // Expose refresh function globally so PHP can call it
  useEffect(() => {
    window.refreshUserState = refreshUserState;

    return () => {
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
    <AuthContext.Provider
      value={{ isLoggedIn, user, refreshUserState, logout, login }}
    >
      {children}
    </AuthContext.Provider>
  );
};
