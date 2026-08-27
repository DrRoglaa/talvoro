(() => {
  const machineKey = (value, separator = '_') => String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, separator)
    .replace(new RegExp(`^${separator}+|${separator}+$`, 'g'), '')
    .slice(0, 100);

  const bindGenerated = (source, target, separator) => {
    if (!(source instanceof HTMLInputElement) || !(target instanceof HTMLInputElement) || target.readOnly) return;
    let automatic = target.value.trim() === '' || target.value.trim() === machineKey(source.value, separator);
    target.addEventListener('input', () => { automatic = false; });
    source.addEventListener('input', () => {
      if (!automatic) return;
      target.value = machineKey(source.value, separator);
      target.dispatchEvent(new Event('change', { bubbles: true }));
    });
  };

  document.querySelectorAll('[data-schema-field-form]').forEach((form) => {
    const select = form.querySelector('[data-field-type]');
    if (select instanceof HTMLSelectElement) {
      const sync = () => {
        const type = select.value;
        form.querySelectorAll('[data-options-for]').forEach((node) => {
          const supported = String(node.getAttribute('data-options-for') || '').split(/\s+/).filter(Boolean);
          node.hidden = !supported.includes(type);
        });
      };
      select.addEventListener('change', sync);
      sync();
    }
    bindGenerated(form.querySelector('[data-field-label]'), form.querySelector('[data-field-key]'), '_');
  });

  document.querySelectorAll('.model-editor-form').forEach((form) => {
    bindGenerated(form.querySelector('[data-model-singular]'), form.querySelector('[data-model-key]'), '_');
    bindGenerated(form.querySelector('[data-model-plural]'), form.querySelector('[data-model-slug]'), '-');
  });

  document.querySelectorAll('[data-component-form]').forEach((form) => {
    bindGenerated(form.querySelector('[data-component-name]'), form.querySelector('[data-component-key]'), '-');
  });
})();
