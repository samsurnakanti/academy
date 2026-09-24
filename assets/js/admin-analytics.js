(() => {
    const status = document.getElementById('elldy-analytics-status');
    const columns = document.getElementById('elldy-columns');
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
        columns.disabled = true;
        refreshButton.disabled = true;
        setStatus('Loading analytics...');
        try {
            analytics = window.ElldyEmbed.mount({
                container: '#elldy-analytics',
                frameUrl: 'https://elldy.com/secure-embed/427b8160-df90-440b-980a-5ea89ec9184a/frame/',
                title: 'Elldy Academy performance indicators',
                width: '100%',
                height: 400,
                autoHeight: true,
                layout: {
                    mode: 'grid',
                    columns: columns.value === 'auto' ? 'auto' : Number(columns.value),
                    minCardWidth: 220,
                    gap: 16,
                    cardHeight: 180
                },
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
                    columns.disabled = false;
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

    columns.addEventListener('change', () => {
        if (!analytics || !ready) return;
        try {
            analytics.setLayout({
                mode: 'grid',
                columns: columns.value === 'auto' ? 'auto' : Number(columns.value)
            });
        } catch (error) {
            setStatus('The layout could not be changed. Reload the page and try again.');
        }
    });
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
