document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        if (!window.confirm(element.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const siteHeader = document.querySelector('.site-header');
const brandLabel = document.querySelector('.brand-label');
const menuToggle = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('.main-nav');
const brandPhrases = [
    'Academy',
    'Data Intelligence',
    'Business Intelligence (BI)',
    'Data Analytics',
];

if (siteHeader) {
    const syncHeaderState = () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
        const isScrolled = scrollTop > 8;
        siteHeader.classList.toggle('is-scrolled', isScrolled);
    };

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
    document.addEventListener('scroll', syncHeaderState, { passive: true });
}

if (brandLabel) {
    let phraseIndex = 0;

    window.setInterval(() => {
        brandLabel.classList.add('is-changing');

        window.setTimeout(() => {
            phraseIndex = (phraseIndex + 1) % brandPhrases.length;
            brandLabel.textContent = brandPhrases[phraseIndex];
            brandLabel.classList.remove('is-changing');
        }, 280);
    }, 2400);
}

if (siteHeader && menuToggle && mainNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = siteHeader.classList.toggle('menu-open');
        menuToggle.setAttribute('aria-expanded', String(isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    });

    mainNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            siteHeader.classList.remove('menu-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open menu');
        });
    });
}

document.querySelectorAll('.video-frame.native-player').forEach((frame) => {
    const video = frame.querySelector('.academy-video');
    const toggle = frame.querySelector('.video-toggle');

    if (!video || !toggle) {
        return;
    }

    const syncVideoState = () => {
        const isPlaying = !video.paused && !video.ended;
        frame.classList.toggle('is-playing', isPlaying);
        toggle.setAttribute('aria-label', isPlaying ? 'Pause video' : 'Play video');
        const icon = toggle.querySelector('.video-toggle-icon');
        if (icon) {
            icon.classList.toggle('play', !isPlaying);
            icon.classList.toggle('pause', isPlaying);
        }
    };

    toggle.addEventListener('click', () => {
        if (video.paused || video.ended) {
            video.play();
        } else {
            video.pause();
        }
    });

    video.addEventListener('click', () => {
        if (video.paused || video.ended) {
            video.play();
        } else {
            video.pause();
        }
    });

    video.addEventListener('play', syncVideoState);
    video.addEventListener('pause', syncVideoState);
    video.addEventListener('ended', syncVideoState);
    syncVideoState();
});
