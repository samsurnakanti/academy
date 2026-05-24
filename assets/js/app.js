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
const appSplash = document.getElementById('app-splash');
const isInstalledApp = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const appAnalyticsUrl = document.body.dataset.appAnalyticsUrl;
const appAnalyticsToken = document.body.dataset.appAnalyticsToken;

if (isInstalledApp) {
    document.body.classList.add('is-installed-app-launch');
    document.body.classList.add('is-installed-app');
} else {
    document.body.classList.remove('is-installed-app-launch');
}

if (appSplash && isInstalledApp) {
    const hideSplash = () => {
        appSplash.classList.add('is-hidden');
        window.setTimeout(() => appSplash.remove(), 460);
    };

    window.addEventListener('load', () => {
        window.setTimeout(hideSplash, 850);
    });

    window.setTimeout(hideSplash, 2600);
} else if (appSplash) {
    appSplash.remove();
}

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

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const serviceWorkerUrl = document.body.dataset.serviceWorkerUrl;

        if (serviceWorkerUrl) {
            navigator.serviceWorker.register(serviceWorkerUrl).catch(() => {});
        }
    });
}

const installAppButton = document.getElementById('install-app-button');
const installAppHelp = document.getElementById('install-app-help');
let installPromptEvent = null;

window.addEventListener('beforeinstallprompt', (event) => {
    if (!installAppButton) {
        return;
    }

    event.preventDefault();
    installPromptEvent = event;
    installAppButton.disabled = false;
    installAppButton.textContent = 'Install App';

    if (installAppHelp) {
        installAppHelp.textContent = 'Tap Install App and confirm when your browser asks.';
    }
});

if (installAppButton) {
    installAppButton.addEventListener('click', async () => {
        if (installPromptEvent) {
            installPromptEvent.prompt();
            await installPromptEvent.userChoice.catch(() => null);
            installPromptEvent = null;
            installAppButton.hidden = true;
            return;
        }

        const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);

        if (installAppHelp) {
            installAppHelp.textContent = isIos
                ? 'In Safari, tap Share, then Add to Home Screen. Chrome on iPhone cannot install directly.'
                : 'Use your browser menu and choose Install app or Add to Home screen. On Android, open this page in Chrome if the install option is missing.';
        }
    });
}

const appInstallStorageKey = 'elldy_app_install_key';
const appLaunchStorageKey = 'elldy_last_installed_launch_day';

const bytesToHex = (bytes) => Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

const appStorage = () => {
    try {
        return window.localStorage;
    } catch (error) {
        return null;
    }
};

const appInstallKey = () => {
    const storage = appStorage();

    if (!storage) {
        return '';
    }

    let key = storage.getItem(appInstallStorageKey);

    if (!key || !/^[a-f0-9]{64}$/.test(key)) {
        const bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        key = bytesToHex(bytes);
        storage.setItem(appInstallStorageKey, key);
    }

    return key;
};

function trackAppInstallEvent(eventType) {
    const storage = appStorage();

    if (!appAnalyticsUrl || !appAnalyticsToken || !storage || !window.crypto || !window.crypto.getRandomValues) {
        return;
    }

    const installKey = appInstallKey();

    if (!installKey) {
        return;
    }

    const formData = new FormData();
    formData.append('csrf_token', appAnalyticsToken);
    formData.append('event_type', eventType);
    formData.append('install_key', installKey);
    formData.append('platform', window.navigator.platform || '');

    window.fetch(appAnalyticsUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
}

window.addEventListener('appinstalled', () => {
    document.body.classList.add('is-installed-app');
    trackAppInstallEvent('appinstalled');

    if (installAppButton) {
        installAppButton.hidden = true;
    }
});

if (isInstalledApp) {
    const storage = appStorage();
    const today = new Date().toISOString().slice(0, 10);

    if (storage && storage.getItem(appLaunchStorageKey) !== today) {
        storage.setItem(appLaunchStorageKey, today);
        trackAppInstallEvent('installed_launch');
    }
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

    const progressUrl = video.dataset.progressUrl;
    const csrfToken = video.dataset.csrfToken;
    const enrollmentId = video.dataset.enrollmentId;
    const materialId = video.dataset.materialId;
    const startSeconds = Number.parseFloat(video.dataset.startSeconds || '0');
    let lastSavedAt = 0;
    let hasRestoredPosition = false;

    const saveProgress = (force = false) => {
        if (!progressUrl || !csrfToken || !enrollmentId || !materialId || !Number.isFinite(video.duration) || video.duration <= 0) {
            return;
        }

        const now = Date.now();
        const isComplete = video.ended || ((video.currentTime / video.duration) >= 0.9);

        if (!force && !isComplete && now - lastSavedAt < 10000) {
            return;
        }

        lastSavedAt = now;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('enrollment_id', enrollmentId);
        formData.append('material_id', materialId);
        formData.append('watched_seconds', String(Math.max(0, video.currentTime)));
        formData.append('duration_seconds', String(Math.max(0, video.duration)));

        window.fetch(progressUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => {});
    };

    video.addEventListener('loadedmetadata', () => {
        if (hasRestoredPosition || !Number.isFinite(startSeconds) || startSeconds <= 0 || !Number.isFinite(video.duration)) {
            return;
        }

        if (startSeconds < video.duration - 5) {
            video.currentTime = startSeconds;
        }

        hasRestoredPosition = true;
    });

    video.addEventListener('timeupdate', () => saveProgress(false));
    video.addEventListener('pause', () => saveProgress(true));
    video.addEventListener('ended', () => saveProgress(true));
    window.addEventListener('beforeunload', () => saveProgress(true));
});
