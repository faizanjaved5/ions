/**
 * Vanilla JavaScript Theme System
 * Replaces next-themes with a simpler, more reliable approach
 * Uses data-theme attribute on body element for better shadow DOM compatibility
 */

export type Theme = 'light' | 'dark';

// Storage keys
const STORAGE_KEY = 'ion-theme';
const COOKIE_KEY = 'theme';

// Default theme
const DEFAULT_THEME: Theme = 'dark';

/**
 * Get theme from localStorage, cookie, or return default
 */
export function getInitialTheme(): Theme {
  // Try localStorage first
  if (typeof window !== 'undefined') {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
      return stored as Theme;
    }

    // Try cookie as fallback
    const cookieMatch = document.cookie.match(new RegExp('(^| )' + COOKIE_KEY + '=([^;]+)'));
    if (cookieMatch && (cookieMatch[2] === 'light' || cookieMatch[2] === 'dark')) {
      return cookieMatch[2] as Theme;
    }
  }

  return DEFAULT_THEME;
}

/**
 * Set theme on body element and save to storage
 */
export function setTheme(theme: Theme): void {
  if (typeof window === 'undefined') return;

  // Set data-theme attribute on body
  document.body.setAttribute('data-theme', theme);

  // Save to localStorage
  localStorage.setItem(STORAGE_KEY, theme);

  // Save to cookie (for PHP compatibility)
  document.cookie = `${COOKIE_KEY}=${theme}; path=/; max-age=31536000`; // 1 year

  // Dispatch custom event for React components to listen
  window.dispatchEvent(new CustomEvent('theme-change', { detail: { theme } }));
}

/**
 * Toggle between light and dark themes
 */
export function toggleTheme(): Theme {
  const currentTheme = getCurrentTheme();
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';
  setTheme(newTheme);
  return newTheme;
}

/**
 * Get current theme from body attribute
 */
export function getCurrentTheme(): Theme {
  if (typeof window === 'undefined') return DEFAULT_THEME;
  
  const bodyTheme = document.body.getAttribute('data-theme');
  return (bodyTheme === 'light' || bodyTheme === 'dark') ? bodyTheme as Theme : getInitialTheme();
}

/**
 * Initialize theme - must be called before React renders
 */
export function initTheme(): void {
  if (typeof window === 'undefined') return;

  const theme = getInitialTheme();
  setTheme(theme);
  
  console.log('🎨 ION Theme initialized:', theme);
}

/**
 * Create global theme API
 */
export function createGlobalThemeAPI() {
  if (typeof window === 'undefined') return;

  const globalAPI = {
    getTheme: getCurrentTheme,
    setTheme,
    toggleTheme,
    initTheme,
    getInitialTheme,
  };

  // Expose globally for PHP pages and other scripts
  (window as any).IONTheme = globalAPI;
  (window as any).toggleTheme = toggleTheme;
  (window as any).IONToggleTheme = toggleTheme;
  (window as any).switchTheme = toggleTheme;

  return globalAPI;
}

// Initialize global API immediately
if (typeof window !== 'undefined') {
  createGlobalThemeAPI();
}
