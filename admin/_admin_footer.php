    </main>
<script>
(() => {
    const header = document.querySelector('.admin-header');
    const toggle = document.querySelector('.admin-header .menu-toggle');
    const nav = document.getElementById('admin-navigation');

    if (!header || !toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = header.classList.toggle('menu-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    });

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            header.classList.remove('menu-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open menu');
        });
    });
})();
</script>
</body>
</html>
