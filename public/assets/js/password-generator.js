(() => {
  const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-_';
  const required = [
    'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'abcdefghijkmnopqrstuvwxyz',
    '23456789',
    '!@#$%&*+-_'
  ];

  function randomIndex(max) {
    const limit = Math.floor(256 / max) * max;
    const bytes = new Uint8Array(1);
    do { crypto.getRandomValues(bytes); } while (bytes[0] >= limit);
    return bytes[0] % max;
  }

  function pick(chars) { return chars[randomIndex(chars.length)]; }

  function strongPassword(length = 20) {
    const out = required.map(pick);
    while (out.length < length) out.push(pick(alphabet));
    for (let i = out.length - 1; i > 0; i -= 1) {
      const j = randomIndex(i + 1);
      [out[i], out[j]] = [out[j], out[i]];
    }
    return out.join('');
  }

  document.querySelectorAll('[data-password-generator]').forEach((form) => {
    const input = form.querySelector('[data-generated-password]');
    const generate = form.querySelector('[data-generate-password]');
    const copy = form.querySelector('[data-copy-password]');
    if (!input) return;

    generate?.addEventListener('click', () => {
      input.value = strongPassword();
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.focus();
      input.select();
    });

    copy?.addEventListener('click', async () => {
      if (!input.value) input.value = strongPassword();
      try {
        await navigator.clipboard.writeText(input.value);
        const old = copy.textContent;
        copy.textContent = 'Copied';
        setTimeout(() => { copy.textContent = old; }, 1200);
      } catch {
        input.focus();
        input.select();
        document.execCommand('copy');
      }
    });
  });
})();
