(() => {
    const state = {
        publicKey: null,
        subscription: null,
        error: null,
    };

    function elements() {
        return {
            trigger: document.getElementById('pushNotificationMenu'),
            indicator: document.getElementById('pushNotificationIndicator'),
            icon: document.getElementById('pushNotificationIcon'),
            sheet: document.getElementById('pushNotificationSheet'),
            status: document.getElementById('pushNotificationStatus'),
            help: document.getElementById('pushNotificationHelp'),
            toggle: document.getElementById('pushNotificationToggle'),
            test: document.getElementById('pushNotificationTest'),
            toast: document.getElementById('pushNotificationToast'),
            toastBody: document.getElementById('pushNotificationToastBody'),
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

    function showToast(message) {
        const { toast, toastBody } = elements();
        if (!toast || !toastBody || !window.bootstrap?.Toast) return;

        toastBody.textContent = message;
        window.bootstrap.Toast.getOrCreateInstance(toast, {
            delay: 2800,
        }).show();
    }

    function closeSheet() {
        const { sheet } = elements();
        if (!sheet || !window.bootstrap?.Offcanvas) return;

        window.bootstrap.Offcanvas.getInstance(sheet)?.hide();
    }

    function setTriggerState(iconName, indicatorState = null) {
        const { icon, indicator } = elements();

        icon.className = `bi ${iconName} icon-md`;
        indicator.classList.remove('is-active', 'is-blocked');

        if (indicatorState) {
            indicator.classList.add(indicatorState);
        }
    }

    function render() {
        const { trigger, status, help, toggle, test } = elements();
        if (!trigger) return;

        trigger.hidden = false;
        help.hidden = true;
        help.textContent = '';
        toggle.hidden = false;
        test.hidden = true;
        setTriggerState('bi-bell');

        if (isIos() && !isInstalledApp()) {
            status.textContent = 'Su iPhone apri Aletta Barber dalla schermata Home per attivare le notifiche.';
            help.textContent = 'Apri il sito in Safari, tocca Condividi e scegli “Aggiungi alla schermata Home”.';
            help.hidden = false;
            toggle.hidden = true;
            return;
        }

        if (!isPushSupported()) {
            status.textContent = 'Le notifiche push non sono supportate su questo browser.';
            help.textContent = 'Prova ad aggiornare il browser o utilizza Safari, Chrome o Edge.';
            help.hidden = false;
            toggle.hidden = true;
            return;
        }

        if (state.error) {
            status.textContent = state.error;
            help.textContent = 'Controlla la connessione e riprova tra poco.';
            help.hidden = false;
            toggle.hidden = true;
            setTriggerState('bi-bell-slash', 'is-blocked');
            return;
        }

        if (!state.publicKey) {
            status.textContent = 'Le notifiche saranno disponibili dopo la configurazione del server.';
            toggle.hidden = true;
            return;
        }

        if (Notification.permission === 'denied') {
            status.textContent = 'Le notifiche sono bloccate. Riattivale dalle impostazioni del dispositivo.';
            help.textContent = 'Apri le impostazioni delle notifiche del dispositivo e abilita Aletta Barber.';
            help.hidden = false;
            toggle.hidden = true;
            setTriggerState('bi-bell-slash', 'is-blocked');
            return;
        }

        toggle.disabled = false;

        if (state.subscription) {
            status.textContent = 'Le notifiche sono attive su questo dispositivo.';
            toggle.innerHTML = '<i class="bi bi-bell-slash me-2"></i>Disattiva';
            toggle.classList.remove('btn-app');
            toggle.classList.add('btn-outline-secondary');
            test.hidden = false;
            setTriggerState('bi-bell-fill', 'is-active');
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
            state.error = null;
            render();
            closeSheet();
            showToast('Notifiche attivate su questo dispositivo.');
        } catch (error) {
            if (Notification.permission === 'denied') {
                render();
            } else {
                const { status } = elements();
                status.textContent = error.message || 'Impossibile attivare le notifiche.';
            }
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
            state.error = null;
            render();
            closeSheet();
            showToast('Notifiche disattivate su questo dispositivo.');
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
            closeSheet();
            showToast(result.message);
        } catch (error) {
            elements().status.textContent = error.message || 'Invio della notifica di prova non riuscito.';
        } finally {
            setBusy(false);
        }
    }

    async function initialize() {
        const { trigger, toggle, test } = elements();
        if (!trigger || !getToken()) return;

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
            state.error = null;

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
            state.error = error.message || 'Impossibile verificare le notifiche.';
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', initialize);
})();
