(() => {
  const form = document.querySelector('[data-content-model-form]');
  if (!form) return;
  const publicToggle = form.querySelector('[data-model-public]');
  const urlsToggle = form.querySelector('input[name="has_urls"]');
  const revisionsToggle = form.querySelector('[data-model-revisions]');

  const setDependent = (input, enabled, reason) => {
    if (!input) return;
    if (!enabled && !input.disabled) { input.dataset.previousChecked = input.checked ? '1' : '0'; input.checked = false; }
    if (enabled && input.disabled && input.dataset.previousChecked === '1') input.checked = true;
    input.disabled = !enabled;
    const card = input.closest('.toggle-card');
    if (card) {
      card.classList.toggle('is-dependent-disabled', !enabled);
      card.setAttribute('aria-disabled', enabled ? 'false' : 'true');
      if (!enabled) card.title = reason;
      else card.removeAttribute('title');
    }
  };

  const update = () => {
    const isPublic = Boolean(publicToggle?.checked);
    form.querySelectorAll('[data-requires-public]').forEach((input) => {
      setDependent(input, isPublic, 'Enable Public content first.');
    });
    const hasUrls = isPublic && Boolean(urlsToggle?.checked);
    form.querySelectorAll('[data-requires-urls]').forEach((input) => {
      setDependent(input, hasUrls, 'Enable Public content and Individual URLs first.');
    });
    const hasRevisions = Boolean(revisionsToggle?.checked);
    form.querySelectorAll('[data-requires-revisions]').forEach((input) => {
      setDependent(input, hasRevisions, 'Enable Revision history first.');
    });
  };

  form.addEventListener('change', (event) => {
    if (event.target.matches('[data-model-public], input[name="has_urls"], [data-model-revisions]')) update();
  });
  update();
})();
