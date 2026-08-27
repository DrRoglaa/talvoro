(() => {
  const uploadForm = document.querySelector('[data-media-upload]');
  const dropzone = document.querySelector('[data-media-dropzone]');
  const fileInput = document.querySelector('[data-media-file-input]');
  const fileName = document.querySelector('[data-media-file-name]');

  const showFile = (file) => {
    if (!fileName) return;
    fileName.textContent = file ? `${file.name} · ${(file.size / (1024 * 1024)).toFixed(file.size >= 1024 * 1024 ? 1 : 2)} MB` : 'No file selected';
    dropzone?.classList.toggle('has-file', Boolean(file));
  };

  if (fileInput) {
    fileInput.addEventListener('change', () => showFile(fileInput.files?.[0] || null));
  }

  if (dropzone && fileInput) {
    ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (event) => {
      event.preventDefault();
      dropzone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (event) => {
      event.preventDefault();
      dropzone.classList.remove('is-dragging');
    }));
    dropzone.addEventListener('drop', (event) => {
      const files = event.dataTransfer?.files;
      if (!files?.length) return;
      try {
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        fileInput.files = transfer.files;
        showFile(files[0]);
      } catch (_) {
        fileInput.click();
      }
    });
  }

  const status = document.querySelector('[data-media-copy-status]');
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-media-url]');
    if (!button) return;
    const value = button.getAttribute('data-copy-media-url') || '';
    try {
      await navigator.clipboard.writeText(new URL(value, window.location.origin).href);
      const old = button.textContent;
      button.textContent = 'Copied';
      if (status) status.textContent = 'Media URL copied to the clipboard.';
      window.setTimeout(() => { button.textContent = old; }, 1400);
    } catch (_) {
      if (status) status.textContent = 'Could not copy automatically. Open Edit details and copy the public URL manually.';
    }
  });

  uploadForm?.addEventListener('submit', () => {
    const submit = uploadForm.querySelector('button[type="submit"]');
    if (submit) {
      submit.disabled = true;
      submit.textContent = 'Uploading…';
    }
  });
})();
