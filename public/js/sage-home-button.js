/*
   File: /js/sage-home-button.js
   (Corrected version: Icon has no surrounding box)
   Drop into your project and include with:
   <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
*/

(function () {
  'use strict';

  const ROOT_ID = 'sage-home-button-root-v1';

  // --- Configuration ---
  const currentScript = document.currentScript || (function () {
    const scripts = document.getElementsByTagName('script');
    return scripts[scripts.length - 1];
  })();

  const cfg = {
    home: (currentScript && currentScript.getAttribute('data-home')) || '/dashboard.php',
    size: parseInt(currentScript && currentScript.getAttribute('data-size')) || 50,
    offsetX: parseInt(currentScript && currentScript.getAttribute('data-offset-x')) || 6,
    offsetY: parseInt(currentScript && currentScript.getAttribute('data-offset-y')) || 11
    // bgAlpha is no longer needed
  };

  const HUGE_ZINDEX = 2147483647;

  let rootEl = null;
  let shadowRoot = null;

  function create() {
    try {
      // Don't re-create if it already exists
      if (document.getElementById(ROOT_ID)) {
        return;
      }

      rootEl = document.createElement('div');
      rootEl.id = ROOT_ID;

      const cssText = [
        `position:fixed !important`,
        `top:${cfg.offsetY}px !important`,
        `left:${cfg.offsetX}px !important`,
        `width:${cfg.size}px !important`,
        `height:${cfg.size}px !important`,
        `display:inline-block !important`,
        `z-index:${HUGE_ZINDEX} !important`,
        `margin:0 !important`,
        `padding:0 !important`,
        `border:0 !important`,
        `background:transparent !important`,
        `pointer-events:auto !important`,
        `touch-action:none !important`,
        `overscroll-behavior: none !important`,
        `filter: grayscale(80%);`,
        `opacity:0.7 !important`,
        `transform:none !important`,
        `box-sizing:border-box !important`
      ].join('; ');
      rootEl.setAttribute('style', cssText);

      shadowRoot = rootEl.attachShadow({ mode: 'open' });

      const wrapper = document.createElement('div');
      wrapper.setAttribute('part', 'wrapper');

      const a = document.createElement('a');
      a.setAttribute('part', 'link');
      a.setAttribute('href', cfg.home);
      a.setAttribute('aria-label', 'SAGE Dashboard (home)');
      a.setAttribute('title', 'SAGE Dashboard');
      a.setAttribute('role', 'button');
      a.setAttribute('rel', 'noopener noreferrer');

      const icon = document.createElement('span');
      icon.setAttribute('part', 'icon');
      icon.textContent = '🧭';
      icon.setAttribute('aria-hidden', 'true');

      a.appendChild(icon);
      wrapper.appendChild(a);

      const style = document.createElement('style');
      style.textContent = `
        :host { all: initial; }
        div[part="wrapper"] {
          width: ${cfg.size}px; height: ${cfg.size}px;
          display: inline-flex; align-items: center; justify-content: center;
          border-radius: 8px; user-select: none; box-sizing: border-box;
          margin: 0; padding: 0; line-height: 1;
        }
        a[part="link"] {
          display: inline-flex; align-items: center; justify-content: center;
          width: 100%; height: 100%; text-decoration: none; border-radius: inherit;
          cursor: pointer; font-size: ${Math.max(12, Math.round(cfg.size * 0.6))}px;
          color: inherit;
          /* --- THE KEY CHANGES ARE HERE --- */
          background: transparent;
          border: none;
          box-shadow: none;
        }
        /* The light mode query is no longer necessary as there is no background */
        span[part="icon"] { display:inline-block; transform: translateY(1px); }
        a[part="link"] { -webkit-tap-highlight-color: rgba(0,0,0,0); touch-action: manipulation; }
      `;

      shadowRoot.appendChild(style);
      shadowRoot.appendChild(wrapper);

      // Event Listeners
      a.setAttribute('draggable', 'false');
      a.addEventListener('dragstart', (ev) => ev.preventDefault(), { passive: false });
      a.addEventListener('touchstart', () => {}, { passive: true }); // Keep for iOS interaction
      a.addEventListener('keydown', function (ev) {
        if (ev.key === ' ' || ev.key === 'Enter') {
          ev.preventDefault();
          window.location.href = a.href;
        }
      });

      // Append the element to the page
      (document.body || document.documentElement).appendChild(rootEl);

    } catch (err) {
      console.error('SAGE Home Button init error:', err);
    }
  }

  // --- API ---
  const api = {
    setHome: function (url) {
      cfg.home = String(url || '/dashboard.php');
      if (shadowRoot) {
        const a = shadowRoot.querySelector('a[part="link"]');
        if (a) a.setAttribute('href', cfg.home);
      }
      try { if (currentScript) currentScript.setAttribute('data-home', cfg.home); } catch (e) {}
    },
    destroy: function () {
      try {
        if (rootEl && rootEl.parentNode) rootEl.parentNode.removeChild(rootEl);
        rootEl = null;
        shadowRoot = null;
        delete window.SAGEHomeButton;
      } catch (e) {}
    },
    getElement: function () { return rootEl; },
    getHome: function () { return cfg.home; }
  };

  Object.defineProperty(window, 'SAGEHomeButton', {
    configurable: true,
    enumerable: false,
    value: api
  });

  // --- Initialization ---
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    create();
  } else {
    document.addEventListener('DOMContentLoaded', create, { once: true });
  }

})();

