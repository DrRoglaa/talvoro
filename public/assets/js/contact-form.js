(() => {
  const focusResult = () => {
    const target = document.querySelector('[data-contact-focus]');
    if (!target) return;
    try { target.focus({ preventScroll: true }); } catch { target.focus(); }
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
    target.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', focusResult, { once: true });
  else focusResult();
})();
