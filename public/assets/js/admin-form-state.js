(() => {
  const main = document.querySelector('.admin-main');
  if (!main) return;

  const STORAGE_KEY = 'talvoro.admin.form-return.v1';
  const MAX_AGE_MS = 5 * 60 * 1000;

  const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();

  const readState = () => {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      return parsed;
    } catch (_) {
      return null;
    }
  };

  const clearState = () => {
    try { sessionStorage.removeItem(STORAGE_KEY); } catch (_) {}
  };

  const sectionForForm = (form) => {
    const explicit = normalize(form.dataset.returnSection);
    if (explicit) {
      const byId = document.getElementById(form.dataset.returnSection);
      if (byId) return { element: byId, key: `id:${form.dataset.returnSection}` };
    }

    const section = form.closest('[data-admin-scroll-section], section, article, .card, .danger-zone, .editor-layout, .settings-panel');
    if (!section) return null;

    if (section.id) return { element: section, key: `id:${section.id}` };

    const explicitKey = normalize(section.getAttribute('data-admin-scroll-section'));
    if (explicitKey) return { element: section, key: `data:${explicitKey}` };

    const heading = section.querySelector('h1, h2, h3, .section-heading strong');
    const label = normalize(heading?.textContent);
    if (!label) return null;

    const candidates = Array.from(main.querySelectorAll('section, article, .card, .danger-zone, .editor-layout, .settings-panel'))
      .filter((candidate) => {
        const candidateHeading = candidate.querySelector('h1, h2, h3, .section-heading strong');
        return normalize(candidateHeading?.textContent) === label;
      });
    const index = Math.max(0, candidates.indexOf(section));
    return { element: section, key: `heading:${label}:${index}` };
  };

  const sectionFromKey = (key) => {
    if (!key) return null;
    if (key.startsWith('id:')) return document.getElementById(key.slice(3));
    if (key.startsWith('data:')) {
      const wanted = key.slice(5);
      return Array.from(main.querySelectorAll('[data-admin-scroll-section]'))
        .find((element) => normalize(element.getAttribute('data-admin-scroll-section')) === wanted) || null;
    }
    if (key.startsWith('heading:')) {
      const lastColon = key.lastIndexOf(':');
      if (lastColon <= 'heading:'.length) return null;
      const label = key.slice('heading:'.length, lastColon);
      const index = Number(key.slice(lastColon + 1));
      const candidates = Array.from(main.querySelectorAll('section, article, .card, .danger-zone, .editor-layout, .settings-panel'))
        .filter((candidate) => {
          const heading = candidate.querySelector('h1, h2, h3, .section-heading strong');
          return normalize(heading?.textContent) === label;
        });
      return candidates[Number.isFinite(index) ? index : 0] || candidates[0] || null;
    }
    return null;
  };

  const storeReturnPosition = (form) => {
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.noScrollRestore !== undefined) return;
    if (form.target && form.target !== '_self') return;

    const section = sectionForForm(form);
    const sectionRect = section?.element.getBoundingClientRect();
    const state = {
      path: window.location.pathname,
      scrollY: Math.max(0, Math.round(window.scrollY)),
      sectionKey: section?.key || '',
      sectionViewportTop: sectionRect ? Math.round(sectionRect.top) : null,
      storedAt: Date.now(),
    };

    try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (_) {}
  };

  main.addEventListener('click', (event) => {
    const target = event.target.closest('[data-confirm]');
    if (!target) return;
    const message = String(target.dataset.confirm || 'Are you sure?');
    if (!window.confirm(message)) event.preventDefault();
  }, true);

  main.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement) storeReturnPosition(form);
  }, true);

  if (window.location.hash) {
    clearState();
    return;
  }

  const state = readState();
  if (!state) return;

  const age = Date.now() - Number(state.storedAt || 0);
  if (state.path !== window.location.pathname || !Number.isFinite(age) || age < 0 || age > MAX_AGE_MS) {
    clearState();
    return;
  }

  const restore = () => {
    const section = sectionFromKey(String(state.sectionKey || ''));
    let targetY = Number(state.scrollY || 0);

    if (section && Number.isFinite(Number(state.sectionViewportTop))) {
      const documentTop = section.getBoundingClientRect().top + window.scrollY;
      targetY = Math.max(0, Math.round(documentTop - Number(state.sectionViewportTop)));
    }

    window.scrollTo({ top: targetY, left: 0, behavior: 'auto' });
    clearState();
  };

  // Defer until the admin layout has finished its first layout pass. This also
  // avoids fighting the browser's default post-redirect scroll restoration.
  requestAnimationFrame(() => requestAnimationFrame(restore));
})();
