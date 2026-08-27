(() => {
  const form = document.querySelector('[data-design-styles]');
  const preview = document.querySelector('[data-design-preview]');
  if (!form || !preview) return;
  const fontStacks = {
    system: 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
    humanist: '"Avenir Next",Avenir,"Segoe UI",system-ui,sans-serif',
    editorial: 'Iowan Old Style,"Palatino Linotype",Palatino,"Times New Roman",serif',
    modern: 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif',
    mono: 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace'
  };
  const radius = { small: '10px', medium: '22px', large: '36px' };
  const button = { small: '10px', medium: '18px', pill: '999px' };
  const shadow = { none: 'none', soft: '0 18px 50px rgba(47,41,38,.08)', strong: '0 24px 70px rgba(47,41,38,.16)' };
  const value = (key) => form.elements.namedItem(key)?.value || '';
  const luminance = (hex) => {
    const raw = String(hex || '').replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(raw)) return 0;
    const channels = [0,2,4].map((offset) => parseInt(raw.slice(offset, offset + 2), 16) / 255).map((v) => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
    return .2126 * channels[0] + .7152 * channels[1] + .0722 * channels[2];
  };
  const contrast = (a, b) => { const x = luminance(a); const y = luminance(b); return (Math.max(x,y) + .05) / (Math.min(x,y) + .05); };
  const renderWarnings = () => {
    const box = document.querySelector('[data-design-live-warnings]');
    if (!box) return;
    const server = document.querySelector('[data-design-server-warnings]');
    if (server) server.hidden = true;
    const warnings = [];
    [['text','background','Main text against page background'],['text','surface','Main text against cards and surfaces']].forEach(([fg,bg,label]) => {
      const ratio = contrast(value(fg), value(bg));
      if (ratio < 4.5) warnings.push(`${label} is ${ratio.toFixed(2)}:1. Aim for at least 4.5:1 for normal text.`);
    });
    const brandRatio = Math.max(contrast('#ffffff', value('brand')), contrast(value('text'), value('brand')));
    if (brandRatio < 4.5) warnings.push('Brand has limited contrast with both white and the selected Text color. Accent sections and buttons may be difficult to read.');
    box.hidden = warnings.length === 0;
    const list = box.querySelector('ul'); if (list) list.innerHTML = warnings.map((warning) => `<li>${warning.replace(/[&<>"]/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]))}</li>`).join('');
  };
  const render = () => {
    const canvas = preview.querySelector('.design-preview-canvas');
    canvas.style.background = value('background'); canvas.style.color = value('text');
    canvas.style.fontFamily = fontStacks[value('body_font')] || fontStacks.humanist;
    canvas.querySelector('h2').style.fontFamily = fontStacks[value('heading_font')] || fontStacks.editorial;
    canvas.querySelector('.design-preview-kicker').style.color = value('accent');
    const primary = canvas.querySelector('.design-preview-button'); primary.style.background = value('brand'); primary.style.color = contrast('#ffffff', value('brand')) >= contrast(value('text'), value('brand')) ? '#ffffff' : value('text'); primary.style.borderRadius = button[value('button_radius')] || button.pill;
    const card = canvas.querySelector('.design-preview-card'); card.style.background = value('surface'); card.style.borderColor = value('border'); card.style.borderRadius = radius[value('radius')] || radius.medium; card.style.boxShadow = shadow[value('shadow')] || shadow.soft;
    canvas.querySelector('a').style.color = value('accent'); canvas.querySelector('a').style.textDecoration = value('link_style') === 'underline' ? 'underline' : 'none';
    form.querySelectorAll('input[type="color"]').forEach((input) => { const code = input.closest('.design-color-control')?.querySelector('[data-color-value]'); if (code) code.textContent = input.value; });
    renderWarnings();
  };
  form.addEventListener('input', render); form.addEventListener('change', render); render();
})();
