(() => {
  const shell = document.querySelector('[data-admin-shell]');
  const nav = document.querySelector('[data-admin-nav]');
  const toggle = document.querySelector('[data-admin-nav-toggle]');
  if (!shell || !nav || !toggle) return;

  const scrollPane = nav.querySelector('.cms-sidebar-nav');
  const closeButtons = document.querySelectorAll('[data-admin-nav-close]');
  const storageKey = 'talvoro.admin.sidebar.scroll.v1';

  const readScroll = () => {
    try {
      const value = Number(sessionStorage.getItem(storageKey));
      return Number.isFinite(value) && value >= 0 ? value : null;
    } catch (_) {
      return null;
    }
  };

  const saveScroll = () => {
    if (!scrollPane) return;
    try { sessionStorage.setItem(storageKey, String(Math.max(0, Math.round(scrollPane.scrollTop)))); } catch (_) {}
  };

  const revealActiveInsidePane = () => {
    if (!scrollPane) return;
    const active = scrollPane.querySelector('.cms-nav-link.is-active');
    if (!active) return;
    const pane = scrollPane.getBoundingClientRect();
    const item = active.getBoundingClientRect();
    if (item.top < pane.top) scrollPane.scrollTop -= (pane.top - item.top) + 12;
    if (item.bottom > pane.bottom) scrollPane.scrollTop += (item.bottom - pane.bottom) + 12;
  };

  if (scrollPane) {
    const stored = readScroll();
    requestAnimationFrame(() => {
      if (stored !== null) scrollPane.scrollTop = stored;
      else revealActiveInsidePane();
    });
    scrollPane.addEventListener('scroll', saveScroll, { passive: true });
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('pointerdown', saveScroll));
    window.addEventListener('pagehide', saveScroll);
  }

  const setOpen = (open) => {
    shell.classList.toggle('nav-open', open);
    document.body.classList.toggle('admin-nav-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) requestAnimationFrame(revealActiveInsidePane);
  };

  toggle.addEventListener('click', () => setOpen(!shell.classList.contains('nav-open')));
  closeButtons.forEach((button) => button.addEventListener('click', () => setOpen(false)));
  nav.addEventListener('click', (event) => {
    if (event.target.closest('a') && matchMedia('(max-width: 980px)').matches) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && shell.classList.contains('nav-open')) {
      setOpen(false);
      toggle.focus();
    }
  });
  matchMedia('(min-width: 981px)').addEventListener?.('change', (event) => { if (event.matches) setOpen(false); });
})();
