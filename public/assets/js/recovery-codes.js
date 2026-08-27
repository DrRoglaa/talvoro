(() => {
  const root = document.querySelector('[data-recovery-codes]');
  if (!root) return;
  const codes = [...root.querySelectorAll('.recovery-code-grid code')].map((node) => node.textContent.trim()).filter(Boolean);
  if (!codes.length) return;
  const text = ['Talvoro recovery codes', '', ...codes, '', 'Each code can be used once. Keep this file private.'].join('\n');
  const status = root.querySelector('[data-recovery-status]');
  const setStatus = (message) => { if (status) status.textContent = message; };

  root.querySelector('[data-copy-recovery]')?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(codes.join('\n'));
      setStatus('Recovery codes copied to the clipboard.');
    } catch (_) {
      setStatus('Could not access the clipboard. Select and copy the codes manually.');
    }
  });

  root.querySelector('[data-download-recovery]')?.addEventListener('click', () => {
    const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'talvoro-recovery-codes.txt';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    setStatus('Recovery codes downloaded. Store the file somewhere private.');
  });
})();
