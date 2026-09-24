(() => {
    const status = document.getElementById('elldy-analytics-status');
    const refreshButton = document.getElementById('elldy-refresh');
    let analytics = null;
    let timer = null;
    let ready = false;
    let refreshing = false;

    const setStatus = message => {
        status.textContent = message;
        status.hidden = !message;
    };
    const showError = () => {
        refreshing = false;
        refreshButton.disabled = !analytics;
        setStatus('Analytics could not be loaded. Try refreshing the values.');
    };
    const refresh = () => {
        if (!analytics || refreshing) return;
        refreshing = true;
        refreshButton.disabled = true;
        setStatus('Refreshing values...');
        try {
            Promise.resolve(analytics.refresh()).catch(showError);
        } catch (error) {
            showError();
        }
    };
    const mount = () => {
        if (analytics) return;
        ready = false;
        refreshing = false;
        refreshButton.disabled = true;
        setStatus('Loading analytics...');
        try {
            analytics = window.ElldyEmbed.mount({
                container: '#elldy-analytics',
                frameUrl: 'https://elldy.com/secure-embed/a80836d0-fe92-41d4-a8ac-874f58a696df/frame/',
                title: 'Elldy Academy analytics dashboard',
                width: '100%',
                height: 600,
                autoHeight: true,
                layout: { mode: 'saved' },
                getToken: async () => {
                    const response = await fetch('elldy_token.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {'X-CSRFToken': document.querySelector('meta[name="csrf-token"]').content}
                    });
                    if (!response.ok) throw new Error('Dashboard access denied');
                    return response.json();
                },
                onLoad: () => {
                    ready = true;
                    refreshing = false;
                    refreshButton.disabled = false;
                    setStatus('');
                },
                onError: showError
            });
            timer = window.setInterval(() => {
                if (ready) refresh();
            }, 60000);
        } catch (error) {
            showError();
        }
    };

    refreshButton.addEventListener('click', refresh);
    window.addEventListener('pagehide', () => {
        window.clearInterval(timer);
        timer = null;
        ready = false;
        refreshing = false;
        if (analytics) {
            analytics.destroy();
            analytics = null;
        }
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) mount();
    });
    mount();
})();
