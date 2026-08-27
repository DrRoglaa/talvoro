(() => {
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  const safeStatus = (value) => {
    const status = String(value || '').toLowerCase();
    return ['published','scheduled','draft','active','inactive','private'].includes(status) ? status : 'draft';
  };

  const createLinkDialog = (wrap, editable, exec, sync) => {
    const form = wrap.closest('form');
    const searchUrl = form?.dataset.internalLinkUrl || '';
    let savedRange = null;
    const backdrop = document.createElement('div');
    backdrop.className = 'internal-link-backdrop';
    backdrop.hidden = true;
    backdrop.innerHTML = `<section class="internal-link-dialog" role="dialog" aria-modal="true" aria-label="Insert link">
      <div class="block-palette-head"><div><p class="eyebrow">Internal link</p><h2>Link to content</h2><p>Search Talvoro content or enter any safe URL.</p></div><button type="button" class="icon-button" data-link-close>×</button></div>
      <label class="palette-search"><span>Search Pages, Posts, Categories and structured content</span><input type="search" data-link-search placeholder="Start typing a title…" autocomplete="off"></label>
      <div class="internal-link-results" data-link-results><p class="field-help">Search your site content above.</p></div>
      <div class="internal-link-custom"><label>Or enter URL<input type="text" data-link-custom placeholder="https://example.com, /about, #section"></label><button type="button" class="button" data-link-use-custom>Use URL</button></div>
      <div class="form-inline-error" data-link-error hidden></div>
    </section>`;
    document.body.appendChild(backdrop);

    const rememberSelection = () => {
      const selection = window.getSelection();
      savedRange = selection && selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
    };
    const restoreSelection = () => {
      editable.focus();
      if (!savedRange) return;
      const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(savedRange);
    };
    const close = () => { backdrop.hidden = true; document.body.classList.remove('modal-open'); };
    const safeHref = (href) => {
      if (href.startsWith('/') && !href.startsWith('//')) return true;
      if (href.startsWith('#')) return true;
      try { return ['http:', 'https:', 'mailto:'].includes(new URL(href, location.origin).protocol); }
      catch { return false; }
    };
    const useLink = (href) => {
      href = String(href || '').trim(); if (!href) return;
      const error = backdrop.querySelector('[data-link-error]');
      if (!safeHref(href)) { error.textContent = 'Use an internal path, page anchor, or a safe http, https or mailto URL.'; error.hidden = false; return; }
      error.hidden = true;
      restoreSelection(); exec('createLink', href); sync(); close();
    };
    const open = () => {
      rememberSelection(); backdrop.hidden = false; document.body.classList.add('modal-open');
      const search = backdrop.querySelector('[data-link-search]'); search.value = ''; backdrop.querySelector('[data-link-custom]').value = '';
      const error = backdrop.querySelector('[data-link-error]'); error.hidden = true; error.textContent = '';
      backdrop.querySelector('[data-link-results]').innerHTML = '<p class="field-help">Search your site content above.</p>'; search.focus();
    };

    let request = 0; let timer = 0;
    backdrop.addEventListener('input', (event) => {
      const search = event.target.closest('[data-link-search]'); if (!search || !searchUrl) return;
      clearTimeout(timer); const q = search.value.trim();
      if (!q) { backdrop.querySelector('[data-link-results]').innerHTML = '<p class="field-help">Search your site content above.</p>'; return; }
      timer = setTimeout(async () => {
        const current = ++request;
        try {
          const response = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await response.json(); if (current !== request) return;
          const items = Array.isArray(data.items) ? data.items : [];
          backdrop.querySelector('[data-link-results]').innerHTML = items.length ? items.map((item) => {
            const status = safeStatus(item.status);
            return `<button type="button" data-link-href="${escapeHtml(item.url)}"><span><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(item.type)} · ${escapeHtml(item.meta)}</small></span><span class="status-pill ${status}">${escapeHtml(item.status)}</span></button>`;
          }).join('') : '<p class="field-help">No matching content found. You can still enter a URL below.</p>';
        } catch { backdrop.querySelector('[data-link-results]').innerHTML = '<p class="field-help">Search is temporarily unavailable. Enter a URL below.</p>'; }
      }, 180);
    });
    backdrop.addEventListener('click', (event) => {
      if (event.target === backdrop || event.target.closest('[data-link-close]')) return close();
      const result = event.target.closest('[data-link-href]'); if (result) return useLink(result.dataset.linkHref);
      if (event.target.closest('[data-link-use-custom]')) return useLink(backdrop.querySelector('[data-link-custom]').value);
    });
    backdrop.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); if (event.key === 'Enter' && event.target.matches('[data-link-custom]')) { event.preventDefault(); useLink(event.target.value); } });
    return open;
  };

  const initEditors = (scope = document) => {
    scope.querySelectorAll('[data-rich-editor]').forEach((wrap) => {
      if (wrap.dataset.richInitialized === '1') return;
      wrap.dataset.richInitialized = '1';
    const editable = wrap.querySelector('[data-rich-editable]');
    const source = wrap.querySelector('[data-rich-source]');
    const hidden = wrap.querySelector('[data-rich-hidden]');
    const toolbar = wrap.querySelector('[data-rich-toolbar]');
    if (!editable || !source || !hidden || !toolbar) return;

    let sourceMode = false;
    const sync = () => { hidden.value = sourceMode ? source.value : editable.innerHTML; };
    const exec = (command, value = null) => { editable.focus(); document.execCommand(command, false, value); sync(); };
    const block = (tag) => exec('formatBlock', tag);
    const currentBlock = () => {
      const selection = window.getSelection(); if (!selection || selection.rangeCount === 0) return null;
      let node = selection.anchorNode; if (node?.nodeType === Node.TEXT_NODE) node = node.parentElement;
      if (!(node instanceof Element)) return null; return node.closest('p,h2,h3,h4,blockquote,pre,li');
    };
    const align = (value) => {
      editable.focus(); let target = currentBlock();
      if (!target) { document.execCommand('formatBlock', false, 'P'); target = currentBlock(); }
      if (!target) return; target.classList.remove('rt-align-left', 'rt-align-center', 'rt-align-right'); target.classList.add(`rt-align-${value}`); sync();
    };
    const openLinkDialog = createLinkDialog(wrap, editable, exec, sync);

    toolbar.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-command],button[data-block],button[data-action]'); if (!button) return;
      event.preventDefault();
      if (button.dataset.command) return exec(button.dataset.command);
      if (button.dataset.block) return block(button.dataset.block);
      switch (button.dataset.action) {
        case 'link': openLinkDialog(); break;
        case 'clear': exec('removeFormat'); currentBlock()?.classList.remove('rt-align-left','rt-align-center','rt-align-right'); sync(); break;
        case 'align': align(button.dataset.align || 'left'); break;
        case 'code': block('pre'); break;
        case 'html':
          sourceMode = !sourceMode;
          if (sourceMode) { source.value = editable.innerHTML; editable.hidden = true; source.hidden = false; source.focus(); }
          else { editable.innerHTML = source.value; source.hidden = true; editable.hidden = false; editable.focus(); }
          button.classList.toggle('is-active', sourceMode); sync(); break;
        default: break;
      }
    });
      editable.addEventListener('input', sync); source.addEventListener('input', sync); wrap.closest('form')?.addEventListener('submit', sync); sync();
    });
  };
  initEditors(document);
  document.addEventListener('talvoro:init-rich-editors', (event) => {
    const scope = event.detail?.scope instanceof Element ? event.detail.scope : document;
    initEditors(scope);
  });
})();
