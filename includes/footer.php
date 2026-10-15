</main>
</div>
<script>
// Dropdown toggle
function toggleDropdown(id) {
    const menu = document.getElementById(id);
    if (menu) menu.classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    }
});

// Session timeout
const SESSION_MS = <?= SESSION_TIMEOUT * 1000 ?>;
let warnTimer;
function resetTimer() {
    clearTimeout(warnTimer);
    warnTimer = setTimeout(() => {
        if (confirm('Your session is about to expire. Click OK to stay logged in.')) {
            fetch('<?= APP_URL ?>/controllers/session_ping.php').then(resetTimer);
        } else {
            window.location = '<?= APP_URL ?>/logout.php?timeout=1';
        }
    }, SESSION_MS - 60000);
}
document.addEventListener('mousemove', resetTimer);
document.addEventListener('keydown', resetTimer);
resetTimer();

// Live clock
setInterval(() => {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-GB');
}, 1000);
</script>
</body>
</html>
