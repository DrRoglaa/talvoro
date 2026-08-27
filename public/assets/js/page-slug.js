(() => {
  const field = document.querySelector('[data-page-url-field]');
  if (!field) return;

  const title = document.querySelector('[data-page-title]');
  const path = field.querySelector('[data-page-path]');
  const mode = field.querySelector('[data-path-mode]');
  const edit = field.querySelector('[data-path-edit]');
  const auto = field.querySelector('[data-path-auto]');
  const help = field.querySelector('[data-path-help]');
  const isNew = field.dataset.isNew === '1';
  if (!title || !path || !mode || !edit || !auto) return;

  const slugifySegment = (value) => String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/č/g, 'c').replace(/ć/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'd')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  const slugifyPath = (value) => String(value || '')
    .split('/')
    .map(slugifySegment)
    .filter(Boolean)
    .join('/');

  const automaticValue = () => slugifySegment(title.value) || '';

  const setReadonly = (readonly) => {
    path.readOnly = readonly;
    path.setAttribute('aria-readonly', readonly ? 'true' : 'false');
    field.classList.toggle('is-editing', !readonly);
    auto.hidden = readonly && mode.value === 'auto';
    edit.textContent = readonly ? 'Edit URL' : 'Done';
  };

  const useAutomatic = () => {
    mode.value = 'auto';
    path.value = automaticValue();
    setReadonly(true);
    if (help) help.textContent = 'Talvoro creates this automatically from the title. If the URL already exists, Talvoro safely adds a number when you save.';
    path.dispatchEvent(new Event('change', { bubbles: true }));
  };

  edit.addEventListener('click', () => {
    if (path.readOnly) {
      mode.value = 'manual';
      setReadonly(false);
      auto.hidden = false;
      if (help) help.textContent = 'You are customizing the public URL. Talvoro will normalize spaces and accented characters when you save.';
      path.focus();
      path.select();
    } else {
      path.value = slugifyPath(path.value);
      setReadonly(true);
      auto.hidden = false;
      if (help) help.textContent = 'Custom URL. Changing the page title will not change this URL.';
      path.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  auto.addEventListener('click', useAutomatic);

  path.addEventListener('blur', () => {
    if (mode.value !== 'manual') return;
    path.value = slugifyPath(path.value);
    path.dispatchEvent(new Event('change', { bubbles: true }));
  });

  title.addEventListener('input', () => {
    if (mode.value !== 'auto') return;
    path.value = automaticValue();
    path.dispatchEvent(new Event('input', { bubbles: true }));
  });

  if (isNew && mode.value === 'auto') {
    if (!path.value) path.value = automaticValue();
    setReadonly(true);
  } else {
    setReadonly(true);
    auto.hidden = false;
  }
})();
