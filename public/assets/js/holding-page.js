(() => {
    const countdown = document.querySelector('[data-countdown]');
    if (!countdown) return;

    const targetRaw = countdown.getAttribute('data-countdown-at');
    const target = targetRaw ? new Date(targetRaw).getTime() : NaN;
    if (!Number.isFinite(target)) return;

    const days = countdown.querySelector('[data-days]');
    const hours = countdown.querySelector('[data-hours]');
    const minutes = countdown.querySelector('[data-minutes]');
    const seconds = countdown.querySelector('[data-seconds]');

    const update = () => {
        const remaining = Math.max(0, target - Date.now());

        const totalSeconds = Math.floor(remaining / 1000);
        const d = Math.floor(totalSeconds / 86400);
        const h = Math.floor((totalSeconds % 86400) / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;

        days.textContent = String(d);
        hours.textContent = String(h).padStart(2, '0');
        minutes.textContent = String(m).padStart(2, '0');
        seconds.textContent = String(s).padStart(2, '0');
    };

    update();
    window.setInterval(update, 1000);
})();
