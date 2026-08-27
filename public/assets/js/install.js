(() => {
    const passwordButton = document.querySelector('[data-generate-install-password]');
    const password = document.querySelector('[data-install-password]');
    const confirm = document.querySelector('[data-install-password-confirm]');

    if (passwordButton && password && confirm && window.crypto?.getRandomValues) {
        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_+=';
        passwordButton.addEventListener('click', () => {
            const bytes = new Uint32Array(24);
            crypto.getRandomValues(bytes);
            let value = '';
            for (const n of bytes) value += alphabet[n % alphabet.length];
            password.value = value;
            confirm.value = value;
            password.type = 'text';
            confirm.type = 'text';
            password.focus();
            password.select();
        });
    }

    const adminPath = document.querySelector('[data-install-admin-path]');
    const adminButton = document.querySelector('[data-generate-admin-path]');
    const dashboardPreview = document.querySelector('[data-admin-dashboard-preview]');
    const loginPreview = document.querySelector('[data-admin-login-preview]');

    const updateAdminPreview = () => {
        if (!adminPath) return;
        const cleaned = adminPath.value.toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 64);
        if (cleaned !== adminPath.value) adminPath.value = cleaned;
        const path = cleaned || 'your-private-path';
        if (dashboardPreview) dashboardPreview.textContent = `/${path}`;
        if (loginPreview) loginPreview.textContent = `/${path}/login`;
    };

    if (adminPath) adminPath.addEventListener('input', updateAdminPreview);
    if (adminButton && adminPath && window.crypto?.getRandomValues) {
        adminButton.addEventListener('click', () => {
            const bytes = new Uint8Array(8);
            crypto.getRandomValues(bytes);
            adminPath.value = `manage-${Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')}`;
            updateAdminPreview();
            adminPath.focus();
            adminPath.select();
        });
    }
    updateAdminPreview();
})();
