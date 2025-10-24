/**
 * React hook for theme management
 * Drop-in replacement for next-themes useTheme hook
 */

import { useState, useEffect, useCallback } from 'react';
import { Theme, getCurrentTheme, setTheme, toggleTheme } from '@/utils/theme';

export interface UseThemeReturn {
  theme: Theme | undefined;
  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
}

/**
 * React hook for accessing and managing theme state
 * Compatible with next-themes useTheme API
 */
export function useTheme(): UseThemeReturn {
  const [theme, setThemeState] = useState<Theme | undefined>(undefined);

  // Initialize theme on mount
  useEffect(() => {
    const currentTheme = getCurrentTheme();
    setThemeState(currentTheme);
  }, []);

  // Listen for theme changes from other sources (like PHP pages or global scripts)
  useEffect(() => {
    const handleThemeChange = (event: CustomEvent) => {
      setThemeState(event.detail.theme);
    };

    const handleStorageChange = () => {
      const currentTheme = getCurrentTheme();
      setThemeState(currentTheme);
    };

    // Listen for custom theme-change events
    window.addEventListener('theme-change', handleThemeChange as EventListener);

    // Listen for localStorage changes (from other tabs)
    window.addEventListener('storage', handleStorageChange);

    // Watch for body attribute changes (from external scripts)
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
          const newTheme = document.body.getAttribute('data-theme');
          if (newTheme === 'light' || newTheme === 'dark') {
            setThemeState(newTheme as Theme);
          }
        }
      });
    });

    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['data-theme']
    });

    return () => {
      window.removeEventListener('theme-change', handleThemeChange as EventListener);
      window.removeEventListener('storage', handleStorageChange);
      observer.disconnect();
    };
  }, []);

  const handleSetTheme = useCallback((newTheme: Theme) => {
    setTheme(newTheme);
    setThemeState(newTheme);
  }, []);

  const handleToggleTheme = useCallback(() => {
    const newTheme = toggleTheme();
    setThemeState(newTheme);
    return newTheme;
  }, []);

  return {
    theme,
    setTheme: handleSetTheme,
    toggleTheme: handleToggleTheme,
  };
}
