// This script now ONLY handles the interactive parts (the toggle button).
// The initial theme is set by the inline script in the <head>.
(function() {
  const KEY = 'spw_theme';
  const root = document.documentElement;
  
  function applyTheme(theme) {
    if (theme === 'dark') {
      root.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
      root.setAttribute('data-theme', 'light');
    } else {
      root.removeAttribute('data-theme');
    }
  }

  function getSavedTheme() {
    return localStorage.getItem(KEY);
  }

  function saveTheme(theme) {
    if (theme === null || theme === 'system') {
      localStorage.removeItem(KEY);
    } else {
      localStorage.setItem(KEY, theme);
    }
  }
  
  function updateToggleButton() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    const currentTheme = getSavedTheme();
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    let isDarkNow = false;
    if (currentTheme === 'dark' || (currentTheme === null && prefersDark)) {
        isDarkNow = true;
    }

    if (isDarkNow) {
      toggle.textContent = '☀️';
      toggle.setAttribute('aria-pressed', 'true');
      toggle.title = 'Switch to light theme';
    } else {
      toggle.textContent = '🌙';
      toggle.setAttribute('aria-pressed', 'false');
      toggle.title = 'Switch to system preference';
    }
  }

  // Attach the click listener when the DOM is ready
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
      toggle.addEventListener('click', () => {
        const current = getSavedTheme();
        let nextTheme;

        if (current === 'dark') {
          nextTheme = 'light';
        } else if (current === 'light') {
          nextTheme = null; // Go back to system
        } else { // Was system, so go to dark
          nextTheme = 'dark';
        }

        saveTheme(nextTheme);
        applyTheme(nextTheme);
        updateToggleButton();
      });
      
      updateToggleButton();
    }
    
    // Listen for OS theme changes to update the button icon if in system mode
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (getSavedTheme() === null) {
        updateToggleButton();
      }
    });
  });
})();

