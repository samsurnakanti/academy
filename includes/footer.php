    </main>
    <?php
    $bottomUser = current_user();
    $currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $bottomNavItems = [
        [
            'label' => 'Home',
            'url' => public_url(),
            'match' => ['index.php', ''],
            'icon' => '<path d="M3 10.6 12 3l9 7.6V21h-6v-6H9v6H3V10.6Z"/>',
        ],
        [
            'label' => 'Programs',
            'url' => public_url('programs'),
            'match' => ['programs.php'],
            'icon' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Zm2.5-.5a.5.5 0 0 0-.5.5v12.55c.16-.03.33-.05.5-.05H18V5H6.5Z"/>',
        ],
        [
            'label' => $bottomUser ? 'Dashboard' : 'Login',
            'url' => $bottomUser ? public_url('dashboard.php') : public_url('login.php'),
            'match' => $bottomUser ? ['dashboard.php', 'learn.php'] : ['login.php'],
            'icon' => '<path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/>',
        ],
        [
            'label' => $bottomUser ? 'Profile' : 'Certificate',
            'url' => $bottomUser ? public_url('profile.php') : public_url('certification'),
            'match' => $bottomUser ? ['profile.php'] : ['certification.php'],
            'icon' => $bottomUser
                ? '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0H4Z"/>'
                : '<path d="M12 3 5 6v5c0 4.4 2.8 8.4 7 9.8 4.2-1.4 7-5.4 7-9.8V6l-7-3Zm-1 11.3-2.3-2.3 1.4-1.4.9.9 3.4-3.4 1.4 1.4-4.8 4.8Z"/>',
        ],
        [
            'label' => 'Support',
            'url' => 'https://wa.me/919490238737?text=Hi%20Elldy%20Academy%2C%20I%20need%20support%20regarding%20my%20course.',
            'match' => [],
            'icon' => '<path d="M12 3a8.5 8.5 0 0 0-7.3 12.86L3.5 21l5.26-1.16A8.5 8.5 0 1 0 12 3Zm-3 6.2c.16-.44.36-.46.68-.46h.5c.18 0 .42.06.64.5.23.48.72 1.7.78 1.82.07.14.1.3.02.47-.25.52-.53.76-.9 1.12.44.76 1.18 1.46 1.94 1.88.5.28.9.45 1.2.56.4-.42.76-.96.96-1.28.14-.22.32-.25.54-.17.22.08 1.52.72 1.78.86.26.13.44.2.5.32.06.12.06.7-.16 1.38-.22.68-1.3 1.3-1.78 1.35-.46.05-1.05.07-1.7-.1-.4-.1-.9-.3-1.56-.58-2.74-1.18-4.52-3.9-4.66-4.08-.14-.18-1.1-1.46-1.1-2.8 0-1.34.7-2 .94-2.26.24-.26.52-.33.7-.33Z"/>',
            'external' => true,
        ],
    ];
    ?>
    <nav class="mobile-bottom-nav" aria-label="Mobile navigation">
        <?php foreach ($bottomNavItems as $item): ?>
            <?php $isActive = in_array($currentPage, $item['match'], true); ?>
            <a
                class="<?= $isActive ? 'active' : '' ?>"
                href="<?= e($item['url']) ?>"
                <?php if (!empty($item['external'])): ?>target="_blank" rel="noopener"<?php endif; ?>
            >
                <svg viewBox="0 0 24 24" aria-hidden="true"><?= $item['icon'] ?></svg>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <footer class="site-footer">
        <div class="footer-brand">
            <strong>Elldy Academy</strong>
            <p>The official learning initiative of the Elldy platform, created to build awareness, educate learners in data analytics, and connect business-case thinking with practical BI learning.</p>
        </div>
        <div class="footer-links">
            <strong>Explore</strong>
            <a href="<?= e(public_url('about')) ?>">About</a>
            <a href="<?= e(public_url('programs')) ?>">Programs</a>
            <a href="<?= e(public_url('blog')) ?>">Blog</a>
            <a href="<?= e(public_url('certification')) ?>">Certification Details</a>
            <a href="<?= e(public_url('contact')) ?>">Contact</a>
        </div>
        <div class="footer-links">
            <strong>Legal</strong>
            <a href="<?= e(public_url('terms')) ?>">Terms & Conditions</a>
            <a href="<?= e(public_url('privacy')) ?>">Privacy Policy</a>
        </div>
        <div class="footer-contact">
            <strong>Contact</strong>
            <p><span>WhatsApp</span><a href="https://wa.me/919490238737" target="_blank" rel="noopener">+91 94902 38737</a></p>
            <p><span>Email</span><a href="mailto:Info@arklytics.in">Info@arklytics.in</a></p>
        </div>
    </footer>
    <a
        class="support-whatsapp"
        href="https://wa.me/919490238737?text=Hi%20Elldy%20Academy%2C%20I%20need%20support%20regarding%20my%20course."
        target="_blank"
        rel="noopener"
        aria-label="Chat with Elldy Academy support on WhatsApp"
    >
        <span class="support-whatsapp-icon" aria-hidden="true"></span>
        <span>Chat with us</span>
    </a>
    <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
