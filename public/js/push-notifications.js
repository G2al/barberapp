(() => {
    const state = {
        publicKey: null,
        subscription: null,
    };

    function elements() {
        return {
            card: document.getElementById('pushNotificationCard'),
            status: document.getElementById('pushNotificationStatus'),
            toggle: document.getElementById('pushNotificationToggle'),
            test: document.getElementById('pushNotificationTest'),
        };
    }

    function isIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent);
    }

    function isInstalledApp() {
        return navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches;
    }

    function isPushSupported() {
        return 'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window;
    }

    function urlBase64ToUint8Array(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);

        return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
    }

    async function pushApiRequest(path, options = {}) {
        const response = await fetch(`${API_BASE}${path}`, {
            ...options,
            headers: {
                'Authorization': `Bearer ${getToken()}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.status === false) {
            throw new Error(payload.message || 'Operazione non riuscita.');
        }

        return payload;
    }

    function setBusy(busy) {
        const { toggle, test } = elements();
        toggle.disabled = busy;
        test.disabled = busy;
    }

    function render() {
        const { card, status, toggle, test } = elements();
        if (!card) return;

        card.hidden = false;

        if (isIos() && !isInstalledApp()) {
            status.textContent = 'Su iPhone apri Aletta Barber dalla schermata Home per attivare le notifiche.';
            toggle.hidden = true;
            test.hidden = true;
            return;
        }

        if (!isPushSupported()) {
            status.textContent = 'Le notifiche push non sono supportate su questo browser.';
            toggle.hidden = true;
            test.hidden = true;
            return;
        }

        if (!state.publicKey) {
            status.textContent = 'Le notifiche saranno disponibili dopo la configurazione del server.';
            toggle.hidden = true;
            test.hidden = true;
            return;
        }

        if (Notification.permission === 'denied') {
            status.textContent = 'Le notifiche sono bloccate. Riattivale dalle impostazioni del dispositivo.';
            toggle.hidden = true;
            test.hidden = true;
            return;
        }

        toggle.hidden = false;
        toggle.disabled = false;

        if (state.subscription) {
            status.textContent = 'Le notifiche sono attive su questo dispositivo.';
            toggle.innerHTML = '<i class="bi bi-bell-slash me-2"></i>Disattiva';
            toggle.classList.remove('btn-app');
            toggle.classList.add('btn-outline-secondary');
            test.hidden = false;
        } else {
            status.textContent = 'Ricevi conferme, annullamenti e promemoria delle tue prenotazioni.';
            toggle.innerHTML = '<i class="bi bi-bell me-2"></i>Attiva notifiche';
            toggle.classList.add('btn-app');
            toggle.classList.remove('btn-outline-secondary');
            test.hidden = true;
        }
    }

    async function subscriptionPayload(subscription) {
        const json = subscription.toJSON();

        return {
            endpoint: json.endpoint,
            keys: json.keys,
            content_encoding: PushManager.supportedContentEncodings?.[0] || 'aes128gcm',
        };
    }

    async function enableNotifications() {
        setBusy(true);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                throw new Error('Permesso per le notifiche non concesso.');
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(state.publicKey),
            });

            await pushApiRequest('/push/subscriptions', {
                method: 'POST',
                body: JSON.stringify(await subscriptionPayload(subscription)),
            });

            state.subscription = subscription;
            render();
        } catch (error) {
            const { status } = elements();
            status.textContent = error.message || 'Impossibile attivare le notifiche.';
        } finally {
            setBusy(false);
        }
    }

    async function disableNotifications() {
        setBusy(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = state.subscription ||
                await registration.pushManager.getSubscription();

            if (subscription) {
                await pushApiRequest('/push/subscriptions', {
                    method: 'DELETE',
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });
                await subscription.unsubscribe();
            }

            state.subscription = null;
            render();
        } catch (error) {
            const { status } = elements();
            status.textContent = error.message || 'Impossibile disattivare le notifiche.';
        } finally {
            setBusy(false);
        }
    }

    async function sendTestNotification() {
        setBusy(true);

        try {
            const result = await pushApiRequest('/push/test', {
                method: 'POST',
                body: JSON.stringify({}),
            });
            elements().status.textContent = result.message;
        } catch (error) {
            elements().status.textContent = error.message || 'Invio della notifica di prova non riuscito.';
        } finally {
            setBusy(false);
        }
    }

    async function initialize() {
        const { card, toggle, test } = elements();
        if (!card || !getToken()) return;

        toggle.addEventListener('click', () => {
            if (state.subscription) {
                disableNotifications();
            } else {
                enableNotifications();
            }
        });
        test.addEventListener('click', sendTestNotification);

        render();

        if (!isPushSupported() || (isIos() && !isInstalledApp())) return;

        try {
            const config = await pushApiRequest('/push/config');
            state.publicKey = config.supported ? config.public_key : null;

            if (state.publicKey) {
                const registration = await navigator.serviceWorker.ready;
                state.subscription = await registration.pushManager.getSubscription();

                if (state.subscription) {
                    await pushApiRequest('/push/subscriptions', {
                        method: 'POST',
                        body: JSON.stringify(await subscriptionPayload(state.subscription)),
                    });
                }
            }
        } catch (error) {
            elements().status.textContent = error.message || 'Impossibile verificare le notifiche.';
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', initialize);
})();
