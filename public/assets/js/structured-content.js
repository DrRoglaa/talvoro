(() => {
  const form = document.querySelector('form.structured-entry-form');
  if (!(form instanceof HTMLFormElement)) return;

  const initRelationPicker = (picker) => {
    if (!(picker instanceof HTMLElement) || picker.dataset.relationReady === '1') return;
    picker.dataset.relationReady = '1';
    const input = picker.querySelector('[data-relation-query]');
    const selectedWrap = picker.querySelector('[data-relation-selected]');
    const results = picker.querySelector('[data-relation-results]');
    if (!(input instanceof HTMLInputElement) || !(selectedWrap instanceof HTMLElement) || !(results instanceof HTMLElement)) return;

    const searchUrl = String(picker.dataset.searchUrl || '');
    const fieldKey = String(picker.dataset.fieldKey || '');
    const inputName = String(picker.dataset.inputName || '');
    const currentEntryId = Number(picker.dataset.currentEntryId || 0);
    const multiple = picker.dataset.multiple === '1';
    let timer = 0;
    let request = 0;

    const selectedIds = () => new Set([...selectedWrap.querySelectorAll('[data-relation-selected-id]')]
      .map((node) => Number(node.dataset.relationSelectedId || 0)).filter((id) => id > 0));
    const setOpen = (open) => {
      results.hidden = !open;
      input.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    const clearResults = () => { results.replaceChildren(); setOpen(false); };
    const announceEmpty = (message) => {
      const empty = document.createElement('div');
      empty.className = 'relation-result-empty';
      empty.textContent = message;
      results.replaceChildren(empty);
      setOpen(true);
    };
    const removeChip = (chip) => {
      if (!(chip instanceof HTMLElement)) return;
      chip.remove();
      form.dispatchEvent(new Event('change', { bubbles: true }));
      input.focus();
    };
    const makeChip = (item) => {
      const chip = document.createElement('span');
      chip.className = 'relation-chip';
      chip.dataset.relationSelectedId = String(item.id);
      const label = document.createElement('span');
      label.append(document.createTextNode(String(item.title || `Entry #${item.id}`)));
      const status = document.createElement('small');
      status.textContent = String(item.status || '');
      if (status.textContent) label.append(' ', status);
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = `${inputName}[]`;
      hidden.value = String(item.id);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.dataset.relationRemove = '';
      remove.textContent = 'Remove';
      remove.setAttribute('aria-label', `Remove ${String(item.title || 'related entry')}`);
      chip.append(label, hidden, remove);
      return chip;
    };
    const select = (item) => {
      const id = Number(item?.id || 0);
      if (id < 1 || !inputName) return;
      if (!multiple) selectedWrap.replaceChildren();
      if (selectedIds().has(id)) { input.value = ''; clearResults(); return; }
      selectedWrap.append(makeChip(item));
      input.value = '';
      clearResults();
      form.dispatchEvent(new Event('change', { bubbles: true }));
      input.focus();
    };
    const render = (items) => {
      const chosen = selectedIds();
      const available = items.filter((item) => !chosen.has(Number(item.id || 0)));
      if (!available.length) { announceEmpty(input.value.trim() ? 'No matching entries.' : 'No more entries available.'); return; }
      const fragment = document.createDocumentFragment();
      available.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'relation-result';
        button.setAttribute('role', 'option');
        button.dataset.relationResult = String(item.id);
        const strong = document.createElement('strong');
        strong.textContent = String(item.title || `Entry #${item.id}`);
        const meta = document.createElement('small');
        meta.textContent = [item.status, item.slug ? `/${item.slug}` : ''].filter(Boolean).join(' · ');
        button.append(strong, meta);
        button.addEventListener('click', () => select(item));
        fragment.append(button);
      });
      results.replaceChildren(fragment);
      setOpen(true);
    };
    const search = async () => {
      if (!searchUrl || !fieldKey) return;
      const thisRequest = ++request;
      const url = new URL(searchUrl, window.location.origin);
      url.searchParams.set('field_key', fieldKey);
      url.searchParams.set('q', input.value.trim());
      if (currentEntryId > 0) url.searchParams.set('current_entry_id', String(currentEntryId));
      input.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => null);
        if (thisRequest !== request) return;
        if (!response.ok || !payload?.ok || !Array.isArray(payload.items)) { announceEmpty('Related entries could not be loaded.'); return; }
        render(payload.items);
      } catch (_) {
        if (thisRequest === request) announceEmpty('Related entries could not be loaded.');
      } finally {
        if (thisRequest === request) input.removeAttribute('aria-busy');
      }
    };
    const queue = () => { window.clearTimeout(timer); timer = window.setTimeout(search, 180); };
    input.addEventListener('input', queue);
    input.addEventListener('focus', () => { if (results.hidden) search(); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { clearResults(); return; }
      if (event.key === 'ArrowDown' && !results.hidden) {
        const first = results.querySelector('[data-relation-result]');
        if (first instanceof HTMLButtonElement) { event.preventDefault(); first.focus(); }
      }
    });
    selectedWrap.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-relation-remove]');
      if (remove) removeChip(remove.closest('[data-relation-selected-id]'));
    });
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) clearResults(); });
  };

  form.querySelectorAll('[data-relation-picker]').forEach(initRelationPicker);

  const title = form.querySelector('[data-structured-title]');
  const slug = form.querySelector('[data-structured-slug]');
  if (title instanceof HTMLInputElement && slug instanceof HTMLInputElement) {
    let manual = slug.value.trim() !== '';
    const slugify = (value) => String(value || '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'd')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 191);
    slug.addEventListener('input', () => { manual = slug.value.trim() !== ''; });
    title.addEventListener('input', () => {
      if (manual) return;
      slug.value = slugify(title.value);
      slug.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (!manual && title.value.trim()) slug.value = slugify(title.value);
  }

  const nextIndex = (wrap) => {
    const indexes = [...wrap.querySelectorAll('[data-repeater-item] input,[data-repeater-item] textarea,[data-repeater-item] select')]
      .map((node) => String(node.name || '').match(/\[(\d+)\]/)?.[1])
      .filter(Boolean).map(Number);
    return indexes.length ? Math.max(...indexes) + 1 : wrap.querySelectorAll('[data-repeater-item]').length;
  };

  const refreshNumbers = (wrap) => {
    [...wrap.querySelectorAll(':scope > [data-repeater-items] > [data-repeater-item]')].forEach((item, index) => {
      const heading = item.querySelector('.component-item-head strong');
      if (!heading) return;
      const base = heading.textContent.replace(/\s+\d+$/, '').trim();
      heading.textContent = `${base} ${index + 1}`;
    });
  };

  const appendRepeaterItem = (wrap, forcedIndex = null) => {
    const template = wrap.querySelector('template[data-repeater-template]');
    const items = wrap.querySelector('[data-repeater-items]');
    if (!(template instanceof HTMLTemplateElement) || !(items instanceof HTMLElement)) return null;
    const index = forcedIndex ?? nextIndex(wrap);
    const html = template.innerHTML.replaceAll('__INDEX__', String(index)).replaceAll('__NUMBER__', String(items.children.length + 1));
    const holder = document.createElement('template'); holder.innerHTML = html.trim();
    const node = holder.content.firstElementChild;
    if (!node) return null;
    items.appendChild(node);
    document.dispatchEvent(new CustomEvent('talvoro:init-rich-editors', { detail: { scope: node } }));
    refreshNumbers(wrap);
    return node;
  };

  form.addEventListener('click', (event) => {
    const add = event.target.closest('[data-repeater-add]');
    if (add) {
      const wrap = add.closest('[data-repeater]');
      if (!(wrap instanceof HTMLElement)) return;
      const node = appendRepeaterItem(wrap);
      if (!node) return;
      form.dispatchEvent(new Event('change', { bubbles: true }));
      node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      return;
    }
    const remove = event.target.closest('[data-repeater-remove]');
    if (remove) {
      const wrap = remove.closest('[data-repeater]');
      const item = remove.closest('[data-repeater-item]');
      if (!(wrap instanceof HTMLElement) || !(item instanceof HTMLElement)) return;
      const siblings = wrap.querySelectorAll(':scope > [data-repeater-items] > [data-repeater-item]');
      if (siblings.length <= 1) {
        item.querySelectorAll('input,textarea,select').forEach((node) => {
          if (node instanceof HTMLInputElement && ['checkbox','radio'].includes(node.type)) node.checked = false;
          else if (node instanceof HTMLSelectElement && node.multiple) [...node.options].forEach((option) => option.selected = false);
          else if ('value' in node) node.value = '';
        });
        item.querySelectorAll('[data-rich-editable]').forEach((node) => { node.innerHTML = ''; });
      } else item.remove();
      refreshNumbers(wrap);
      form.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  document.addEventListener('talvoro:prepare-structured-autosave', (event) => {
    const fields = event.detail?.fields;
    if (!fields || typeof fields !== 'object') return;
    form.querySelectorAll('[data-repeater][data-repeater-name]').forEach((wrap) => {
      const name = String(wrap.dataset.repeaterName || '');
      const match = name.match(/^fields\[([^\]]+)\]$/);
      if (!match) return;
      const desired = Array.isArray(fields[match[1]]) ? fields[match[1]].length : 0;
      const items = wrap.querySelector('[data-repeater-items]');
      if (!(items instanceof HTMLElement)) return;
      while (items.children.length < Math.max(1, desired)) appendRepeaterItem(wrap, items.children.length);
      while (items.children.length > Math.max(1, desired)) items.lastElementChild?.remove();
      refreshNumbers(wrap);
    });
  });
})();
