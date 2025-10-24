export {};

declare global {
  interface Window {
    refreshUserState: () => Promise<void>;
    __ION_PROFILE_HREF?: string;
    __ION_PROFILE_DATA?: unknown;
    
    // Theme system globals
    IONTheme?: {
      getTheme: () => 'light' | 'dark';
      setTheme: (theme: 'light' | 'dark') => void;
      toggleTheme: () => 'light' | 'dark';
      initTheme: () => void;
      getInitialTheme: () => 'light' | 'dark';
    };
    toggleTheme?: () => 'light' | 'dark';
    IONToggleTheme?: () => 'light' | 'dark';
    switchTheme?: () => 'light' | 'dark';
  }
}
