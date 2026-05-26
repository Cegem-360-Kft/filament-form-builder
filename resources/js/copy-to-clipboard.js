window.addEventListener('ffb-copy-to-clipboard', (event) => {
    const json = event.detail?.json ?? '';
    if (!json) return;
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(json).catch(() => fallback(json));
    } else {
        fallback(json);
    }
});

function fallback(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
}
