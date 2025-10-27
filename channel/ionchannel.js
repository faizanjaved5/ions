// ION Geographic Hierarchy - Theme Toggle
(function() {
  const themeToggle = document.getElementById('theme-toggle');
  const body = document.body;
  
  // Check for saved theme preference (from data-theme attribute set by PHP)
  let currentTheme = body.getAttribute('data-theme') || 'dark';
  
  // Also check localStorage for client-side persistence
  const savedTheme = localStorage.getItem('ion-theme') || currentTheme;
  if (savedTheme !== currentTheme) {
    currentTheme = savedTheme;
    body.setAttribute('data-theme', currentTheme);
  }
  
  // Apply dark class to body for compatibility
  body.classList.toggle('dark', currentTheme === 'dark');
  updateIcon(currentTheme);
  
  themeToggle.addEventListener('click', function() {
    const isDark = currentTheme === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    
    // Update body attribute and class
    body.setAttribute('data-theme', newTheme);
    body.classList.toggle('dark');
    
    // Save to localStorage
    localStorage.setItem('ion-theme', newTheme);
    
    // Save to cookie for server-side persistence
    document.cookie = `theme=${newTheme}; path=/; max-age=31536000`; // 1 year
    
    currentTheme = newTheme;
    updateIcon(newTheme);
  });
  
  function updateIcon(theme) {
    // Show/hide sun and moon paths
    const sunPaths = themeToggle.querySelectorAll('.theme-sun');
    const moonPaths = themeToggle.querySelectorAll('.theme-moon');
    
    if (theme === 'light') {
      sunPaths.forEach(el => el.style.display = 'none');
      moonPaths.forEach(el => el.style.display = 'block');
    } else {
      sunPaths.forEach(el => el.style.display = 'block');
      moonPaths.forEach(el => el.style.display = 'none');
    }
  }
})();

