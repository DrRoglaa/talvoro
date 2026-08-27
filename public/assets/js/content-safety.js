(() => {
  const form = document.querySelector('form[data-content-safety-form]');
  if (!(form instanceof HTMLFormElement)) return;

  const autosaveUrl = String(form.dataset.autosaveUrl || '');
  const status = form.querySelector('[data-autosave-status]') || document.querySelector('[data-autosave-status]');
  let dirty = false;
  let submitting = false;
  let saving = false;
  let lastAutosaveHash = '';
  let timer = null;

  const syncEditors = () => {
    // Read rich-text state directly instead of dispatching synthetic input
    // events. Synthetic bubbling here would recursively trigger markDirty().
    form.querySelectorAll('[data-rich-editor]').forEach((wrap) => {
      const editable = wrap.querySelector('[data-rich-editable]');
      const source = wrap.querySelector('[data-rich-source]');
      const hidden = wrap.querySelector('[data-rich-hidden]');
      if (!(hidden instanceof HTMLInputElement || hidden instanceof HTMLTextAreaElement)) return;
      if (source instanceof HTMLTextAreaElement && !source.hidden) hidden.value = source.value;
      else if (editable instanceof HTMLElement) hidden.value = editable.innerHTML;
    });
    document.dispatchEvent(new CustomEvent('talvoro:page-builder-sync'));
  };

  const formFingerprint = () => {
    syncEditors();
    const data = new FormData(form);
    const pairs = [];
    for (const [key, value] of data.entries()) {
      if (key === '_csrf') continue;
      if (value instanceof File) {
        if (value.name || value.size) pairs.push([key, `file:${value.name}:${value.size}:${value.lastModified}`]);
        continue;
      }
      pairs.push([key, String(value)]);
    }
    pairs.sort((a, b) => a[0].localeCompare(b[0]) || a[1].localeCompare(b[1]));
    return JSON.stringify(pairs);
  };

  let initialFingerprint = formFingerprint();

  const hasPendingFiles = () => [...form.querySelectorAll('input[type="file"]')]
    .some((input) => input instanceof HTMLInputElement && input.files && input.files.length > 0);

  const markDirty = () => {
    if (submitting) return;
    dirty = formFingerprint() !== initialFingerprint;
    if (dirty && status) status.textContent = autosaveUrl ? 'Unsaved changes · autosave pending' : 'Unsaved changes';
    if (dirty && autosaveUrl) scheduleAutosave();
  };

  const scheduleAutosave = () => {
    clearTimeout(timer);
    timer = setTimeout(() => void autosave(), 12000);
  };

  const autosave = async () => {
    if (!autosaveUrl || !dirty || saving || submitting) return;
    syncEditors();
    const fingerprint = formFingerprint();
    if (fingerprint === lastAutosaveHash) return;
    saving = true;
    if (status) status.textContent = 'Autosaving…';
    try {
      const payload = new FormData(form);
      for (const [key, value] of [...payload.entries()]) {
        if (value instanceof File) payload.delete(key);
      }
      const response = await fetch(autosaveUrl, {
        method: 'POST', body: payload, credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.ok) throw new Error('Autosave failed');
      lastAutosaveHash = fingerprint;
      const time = new Date();
      if (status) status.textContent = hasPendingFiles()
        ? `Text autosaved at ${time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} · selected files require Save`
        : `Autosaved at ${time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    } catch (_) {
      if (status) status.textContent = 'Autosave unavailable · keep this tab open';
    } finally {
      saving = false;
    }
  };

  form.addEventListener('input', markDirty, true);
  form.addEventListener('change', markDirty, true);
  form.addEventListener('submit', () => {
    syncEditors();
    submitting = true;
    clearTimeout(timer);
  }, true);

  window.addEventListener('beforeunload', (event) => {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });

  // Periodic safety net in addition to the input debounce.
  window.setInterval(() => void autosave(), 30000);

  const recovery = document.querySelector('[data-autosave-recovery]');
  const restoreButton = recovery?.querySelector('[data-restore-autosave]');
  const payloadNode = recovery?.querySelector('[data-autosave-payload]');
  restoreButton?.addEventListener('click', () => {
    let payload = {};
    try { payload = JSON.parse(payloadNode?.textContent || '{}'); } catch (_) { payload = {}; }
    if (!payload || typeof payload !== 'object') return;

    const setNamedValue = (name, value) => {
      const field = form.elements.namedItem(name);
      if (!field) return false;
      if (field instanceof RadioNodeList) {
        const nodes = [...field];
        if (nodes.every((node) => node instanceof HTMLInputElement && node.type === 'checkbox')) {
          const selected = new Set((Array.isArray(value) ? value : [value]).map((item) => String(item)));
          nodes.forEach((node) => { node.checked = selected.has(node.value); });
        } else {
          nodes.forEach((node) => { if (node instanceof HTMLInputElement && node.type === 'radio') node.checked = node.value === String(value); });
        }
      } else if (field instanceof HTMLSelectElement && field.multiple) {
        const selected = new Set((Array.isArray(value) ? value : [value]).map((item) => String(item)));
        [...field.options].forEach((option) => { option.selected = selected.has(option.value); });
      } else if (field instanceof HTMLInputElement && field.type === 'checkbox') {
        field.checked = Boolean(value) && value !== '0';
      } else if ('value' in field) {
        field.value = String(value ?? '');
      }
      return true;
    };
    const restoreNested = (prefix, value) => {
      if (Array.isArray(value)) {
        if (value.every((item) => item === null || ['string','number','boolean'].includes(typeof item))) {
          if (!setNamedValue(prefix + '[]', value)) setNamedValue(prefix, value);
          return;
        }
        value.forEach((item, index) => restoreNested(`${prefix}[${index}]`, item));
        return;
      }
      if (value && typeof value === 'object') {
        Object.entries(value).forEach(([key, item]) => restoreNested(`${prefix}[${key}]`, item));
        return;
      }
      setNamedValue(prefix, value);
    };

    for (const [name, value] of Object.entries(payload)) {
      if (name === 'body_html') {
        form.querySelectorAll('[data-rich-hidden]').forEach((field) => { field.value = String(value || ''); });
        form.querySelectorAll('[data-rich-editable]').forEach((field) => { field.innerHTML = String(value || ''); });
        form.querySelectorAll('[data-rich-source]').forEach((field) => { field.value = String(value || ''); });
        continue;
      }
      if (name === 'page_blocks_json') {
        const hidden = form.querySelector('[data-page-blocks-json]');
        if (hidden) hidden.value = String(value || '[]');
        let blocks = [];
        try { blocks = JSON.parse(String(value || '[]')); } catch (_) { blocks = []; }
        document.dispatchEvent(new CustomEvent('talvoro:restore-blocks', { detail: { blocks } }));
        continue;
      }
      if (name === 'category_ids' && Array.isArray(value)) {
        const selected = new Set(value.map((item) => String(item)));
        form.querySelectorAll('input[name="category_ids[]"]').forEach((field) => { field.checked = selected.has(field.value); });
        continue;
      }
      if (name === 'fields' && value && typeof value === 'object') {
        document.dispatchEvent(new CustomEvent('talvoro:prepare-structured-autosave', { detail: { fields: value } }));
        restoreNested('fields', value);
        document.dispatchEvent(new CustomEvent('talvoro:init-rich-editors', { detail: { scope: form } }));
        continue;
      }
      const field = form.elements.namedItem(name);
      if (!field) continue;
      if (field instanceof RadioNodeList) {
        [...field].forEach((node) => { if (node instanceof HTMLInputElement && node.type === 'radio') node.checked = node.value === String(value); });
      } else if (field instanceof HTMLInputElement && field.type === 'checkbox') {
        field.checked = Boolean(value) && value !== '0';
      } else if ('value' in field) {
        field.value = String(value ?? '');
      }
    }

    recovery.remove();
    dirty = true;
    if (status) status.textContent = 'Autosave restored · review and Save changes';
    form.dispatchEvent(new Event('change', { bubbles: true }));
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
})();
