document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        if (!window.confirm(element.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const siteHeader = document.querySelector('.site-header');
const brandLabel = document.querySelector('.brand-label');
const brandPhrases = [
    'Academy',
    'Data Intelligence',
    'Business Intelligence (BI)',
    'Data Analytics',
    'Business Understanding',
];

if (siteHeader && brandLabel && !brandLabel.textContent.includes('Admin')) {
    let phraseIndex = 0;
    let characterIndex = 0;
    let deleting = false;
    let typingStarted = false;

    const typePhrase = () => {
        const phrase = brandPhrases[phraseIndex];
        brandLabel.textContent = phrase.slice(0, characterIndex);

        if (!deleting && characterIndex < phrase.length) {
            characterIndex++;
            window.setTimeout(typePhrase, 85);
            return;
        }

        if (!deleting && characterIndex === phrase.length) {
            deleting = true;
            window.setTimeout(typePhrase, 1200);
            return;
        }

        if (deleting && characterIndex > 0) {
            characterIndex--;
            window.setTimeout(typePhrase, 45);
            return;
        }

        deleting = false;
        phraseIndex = (phraseIndex + 1) % brandPhrases.length;
        window.setTimeout(typePhrase, 260);
    };

    const syncHeaderState = () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
        const isScrolled = scrollTop > 8;
        siteHeader.classList.toggle('is-scrolled', isScrolled);

        if (isScrolled && !typingStarted) {
            typingStarted = true;
            brandLabel.classList.add('is-typing');
            typePhrase();
        }
    };

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });
    document.addEventListener('scroll', syncHeaderState, { passive: true });
}
