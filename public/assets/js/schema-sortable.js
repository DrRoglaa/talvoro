(() => {
  document.querySelectorAll('[data-schema-sortable]').forEach((list) => {
    const url = String(list.dataset.reorderUrl || '');
    const token = String(list.dataset.reorderToken || '');
    if (!url || !token) return;
    let dragged = null;
    let saving = false;
    let saveAgain = false;

    const rows = () => [...list.querySelectorAll('[data-field-id]')];
    const syncButtons = () => {
      const current = rows();
      current.forEach((row, index) => {
        const up = row.querySelector('[data-move-field="up"]');
        const down = row.querySelector('[data-move-field="down"]');
        if (up instanceof HTMLButtonElement) up.disabled = index === 0;
        if (down instanceof HTMLButtonElement) down.disabled = index === current.length - 1;
      });
    };

    const persist = async () => {
      if (saving) { saveAgain = true; return; }
      saving = true;
      list.classList.add('is-saving');
      const body = new FormData();
      body.append('_csrf', token);
      rows().forEach((row) => body.append('order[]', String(row.dataset.fieldId || '')));
      try {
        const response = await fetch(url, {
          method: 'POST', body, credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) throw new Error(payload.error || 'Could not save field order.');
        rows().forEach((row, index) => {
          const order = row.querySelector('.schema-order');
          if (order) order.textContent = `#${(index + 1) * 10}`;
        });
        list.classList.remove('has-error');
        list.classList.add('is-saved');
        setTimeout(() => list.classList.remove('is-saved'), 900);
      } catch (error) {
        list.classList.add('has-error');
        const message = error instanceof Error ? error.message : 'Could not save field order.';
        window.alert(`${message}\n\nRefresh the page before trying again.`);
      } finally {
        saving = false;
        list.classList.remove('is-saving');
        if (saveAgain) { saveAgain = false; void persist(); }
      }
    };

    list.addEventListener('click', (event) => {
      const button = event.target.closest('[data-move-field]');
      if (!(button instanceof HTMLButtonElement)) return;
      const row = button.closest('[data-field-id]');
      if (!row) return;
      const direction = button.dataset.moveField;
      if (direction === 'up') {
        const previous = row.previousElementSibling;
        if (previous) list.insertBefore(row, previous);
      } else if (direction === 'down') {
        const next = row.nextElementSibling;
        if (next) list.insertBefore(next, row);
      }
      syncButtons();
      void persist();
    });

    list.addEventListener('dragstart', (event) => {
      const row = event.target.closest('[data-field-id]');
      if (!row) return;
      dragged = row;
      row.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', String(row.dataset.fieldId || ''));
    });
    list.addEventListener('dragover', (event) => {
      if (!dragged) return;
      event.preventDefault();
      const row = event.target.closest('[data-field-id]');
      if (!row || row === dragged) return;
      const rect = row.getBoundingClientRect();
      list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? row : row.nextSibling);
    });
    list.addEventListener('dragend', () => {
      if (!dragged) return;
      dragged.classList.remove('is-dragging');
      dragged = null;
      syncButtons();
      void persist();
    });

    syncButtons();
  });
})();
