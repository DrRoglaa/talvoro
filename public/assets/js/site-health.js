(() => {
  const checks = [...document.querySelectorAll('.js-404-select')];
  const all = document.querySelector('[data-404-select-all]');
  const form = document.querySelector('[data-404-bulk]');
  if (!form || checks.length === 0) return;

  const hidden = form.querySelector('[data-404-paths]');
  const button = form.querySelector('[data-404-dismiss-selected]');

  const sync = () => {
    const selected = checks.filter((item) => item.checked).map((item) => item.value);
    if (hidden) hidden.value = selected.join('\n');
    if (button) {
      button.disabled = selected.length === 0;
      button.textContent = selected.length ? `Dismiss selected (${selected.length})` : 'Dismiss selected';
    }
    if (all) all.checked = selected.length === checks.length && checks.length > 0;
  };

  all?.addEventListener('change', () => {
    checks.forEach((item) => { item.checked = all.checked; });
    sync();
  });
  checks.forEach((item) => item.addEventListener('change', sync));
  form.addEventListener('submit', (event) => {
    sync();
    if (!hidden?.value || !window.confirm('Dismiss the selected 404 monitor entries?')) {
      event.preventDefault();
    }
  });
  sync();
})();
