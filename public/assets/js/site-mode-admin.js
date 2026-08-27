(() => {
    const form = document.querySelector('[data-site-mode-form]');
    if (!form) return;

    const toggle = form.querySelector('[data-development-toggle]');
    const handling = form.querySelector('[data-search-handling]');
    const title = form.querySelector('[data-visitor-title]');
    const summary = form.querySelector('[data-visitor-summary]');

    const render = () => {
        if (!toggle || !handling || !title || !summary) return;

        if (!toggle.checked) {
            title.textContent = 'Public website';
            summary.textContent = 'Public website is live.';
            return;
        }

        title.textContent = 'Development holding page';

        summary.textContent = handling.value === 'maintenance'
            ? 'Maintenance mode: public requests receive HTTP 503 and noindex.'
            : 'Pre-launch mode: the homepage remains HTTP 200 and indexable; other public routes redirect to it.';
    };

    toggle?.addEventListener('change', render);
    handling?.addEventListener('change', render);
    render();
})();
