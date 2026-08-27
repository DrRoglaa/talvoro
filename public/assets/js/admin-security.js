(() => {
    const input = document.querySelector('[data-security-admin-path]');
    const generate = document.querySelector('[data-security-generate-path]');
    const dashboard = document.querySelector('[data-security-dashboard-preview]');
    const login = document.querySelector('[data-security-login-preview]');
    if (!input) return;

    const update = () => {
        const cleaned = input.value.toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 64);
        if (cleaned !== input.value) input.value = cleaned;
        const path = cleaned || 'your-private-path';
        if (dashboard) dashboard.textContent = `${window.location.origin}/${path}`;
        if (login) login.textContent = `${window.location.origin}/${path}/login`;
    };

    input.addEventListener('input', update);
    if (generate && window.crypto?.getRandomValues) {
        generate.addEventListener('click', () => {
            const bytes = new Uint8Array(8);
            crypto.getRandomValues(bytes);
            input.value = `manage-${Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')}`;
            update();
            input.focus();
            input.select();
        });
    }
    update();
})();
