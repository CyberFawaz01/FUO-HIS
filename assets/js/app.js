// =====================================================
// CA-RBAC HIS — Main JavaScript
// =====================================================

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
        setTimeout(() => {
            const a = new bootstrap.Alert(el);
            a.close();
        }, 5000);
    });

    // Activate tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // Activate popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { html: true, trigger: 'hover' });
    });
});

// AJAX helper
function ajaxPost(url, data, callback) {
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(callback)
    .catch(err => console.error('AJAX Error:', err));
}

// Show risk breakdown popover content
function riskFactorsHtml(factors) {
    if (!factors || factors.length === 0) return '<em>No factors applied</em>';
    return '<ul class="mb-0 ps-3">' +
        factors.map(f => `<li class="${f.weight > 0 ? 'text-danger' : 'text-success'}">${f.factor} (${f.weight > 0 ? '+' : ''}${f.weight})</li>`).join('') +
        '</ul>';
}

// Confirm dangerous actions
function confirmAction(msg, callback) {
    if (confirm(msg)) callback();
}

// Format timestamp
function formatTime(ts) {
    return new Date(ts).toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

// Live clock in page title
setInterval(() => {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-GB');
}, 1000);
