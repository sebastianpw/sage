const Toast = {
  container: null,

  init() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.id = 'toast-container';
      Object.assign(this.container.style, {
        position: 'fixed',
        bottom: '20px',
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: 100000,
        display: 'flex',
        flexDirection: 'column',
        gap: '10px',
        pointerEvents: 'none'
      });
      document.body.appendChild(this.container);
    }
  },

  show(message, type = '') {
    this.init();

    const toast = document.createElement('div');
    toast.textContent = message;

    // Force inline styles
    Object.assign(toast.style, {
      minWidth: '200px',
      maxWidth: '400px',
      padding: '12px 20px',
      borderRadius: '8px',
      textAlign: 'center',
      fontFamily: 'sans-serif',
      opacity: '0',
      transform: 'translateY(20px)',
      transition: 'opacity 0.5s, transform 0.5s',
      pointerEvents: 'auto',
      color: '#fff',
      boxShadow: '0 2px 6px rgba(0,0,0,0.2)',
      wordBreak: 'break-word'
    });

    // Background color inline
    const lowerType = type.toLowerCase();
    const colors = {
      success: '#4CAF50',
      error:   '#f44336',
      info:    '#2196F3',
      default: '#333'
    };
    toast.style.backgroundColor = colors[lowerType] || colors.default;

    this.container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
    });

    // Animate out
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      setTimeout(() => {
        if (this.container.contains(toast)) this.container.removeChild(toast);
      }, 500);
    }, 3000);
  }
};
