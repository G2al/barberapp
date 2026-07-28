(function () {
    let configurationPromise = null;

    function supportsPush() {
        return "serviceWorker" in navigator
            && "PushManager" in window
            && "Notification" in window;
    }

    async function apiRequest(path, options = {}) {
        const response = await fetch(`${window.API_BASE || "/api"}${path}`, {
            ...options,
            headers: {
                "Accept": "application/json",
                "Authorization": `Bearer ${localStorage.getItem("token") || ""}`,
                ...(options.body ? { "Content-Type": "application/json" } : {}),
                ...(options.headers || {}),
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || "Operazione non riuscita.");
        }

        return data;
    }

    function getConfiguration() {
        if (!configurationPromise) {
            configurationPromise = apiRequest("/push/config").catch((error) => {
                configurationPromise = null;
                throw error;
            });
        }

        return configurationPromise;
    }

    function base64ToUint8Array(value) {
        const padding = "=".repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, "+").replace(/_/g, "/");
        const raw = window.atob(base64);

        return Uint8Array.from(raw, (character) => character.charCodeAt(0));
    }

    async function currentSubscription() {
        const registration = await navigator.serviceWorker.ready;
        return registration.pushManager.getSubscription();
    }

    async function saveSubscription(subscription) {
        const json = subscription.toJSON();

        await apiRequest("/push/subscriptions", {
            method: "POST",
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: json.keys,
                content_encoding: "aes128gcm",
            }),
        });
    }

    function render(panel, state) {
        const {
            kind,
            title,
            message,
            actionLabel,
            action,
        } = state;

        panel.innerHTML = `
            <div class="push-panel-status push-panel-status--${kind}">
                <span class="push-status-dot" aria-hidden="true"></span>
                <div>
                    <strong>${title}</strong>
                    <p>${message}</p>
                </div>
            </div>
            ${actionLabel ? `<button type="button" class="push-panel-action">${actionLabel}</button>` : ""}
        `;

        const button = panel.querySelector(".push-panel-action");
        if (!button || !action) return;

        button.addEventListener("click", async () => {
            button.disabled = true;
            button.textContent = "Attendi...";

            try {
                await action();
            } catch (error) {
                render(panel, errorState(error.message));
            }
        }, { once: true });
    }

    function errorState(message) {
        return {
            kind: "error",
            title: "Non e stato possibile continuare",
            message: message || "Riprova tra qualche istante.",
            actionLabel: "Riprova",
            action: () => mount(),
        };
    }

    async function enable(panel, config) {
        const permission = await Notification.requestPermission();

        if (permission !== "granted") {
            await updatePanel(panel, config);
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64ToUint8Array(config.public_key),
            });
        }

        await saveSubscription(subscription);
        await updatePanel(panel, config);

        window.appToast?.("Notifiche push attivate.", "success");
    }

    async function disable(panel, config) {
        const subscription = await currentSubscription();

        if (subscription) {
            await apiRequest("/push/subscriptions", {
                method: "DELETE",
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
            await subscription.unsubscribe();
        }

        await updatePanel(panel, config);
        window.appToast?.("Notifiche push disattivate.", "success");
    }

    async function updatePanel(panel, config) {
        if (!panel?.isConnected) return;

        if (!config.enabled) {
            render(panel, {
                kind: "off",
                title: "Notifiche non disponibili",
                message: "Il servizio push non e attivo in questo momento.",
            });
            return;
        }

        if (!supportsPush()) {
            render(panel, {
                kind: "off",
                title: "Browser non compatibile",
                message: "Questo dispositivo non supporta le notifiche push.",
            });
            return;
        }

        if (Notification.permission === "denied") {
            render(panel, {
                kind: "error",
                title: "Notifiche bloccate",
                message: "Riattivale dalle impostazioni del browser o della PWA.",
            });
            return;
        }

        const subscription = await currentSubscription();

        if (subscription) {
            await saveSubscription(subscription);
            render(panel, {
                kind: "on",
                title: "Notifiche attive",
                message: "Riceverai conferme, annullamenti e promemoria.",
                actionLabel: "Disattiva",
                action: () => disable(panel, config),
            });
            return;
        }

        render(panel, {
            kind: "off",
            title: "Attiva le notifiche",
            message: "Ricevi gli aggiornamenti delle tue prenotazioni anche ad app chiusa.",
            actionLabel: "Attiva notifiche",
            action: () => enable(panel, config),
        });
    }

    async function mount(options = {}) {
        const panel = options.panel || document.getElementById("notificationPanel");
        if (!panel) return;

        render(panel, {
            kind: "loading",
            title: "Notifiche",
            message: "Verifica in corso...",
        });

        try {
            const config = await getConfiguration();
            await updatePanel(panel, config);
        } catch (error) {
            render(panel, errorState(error.message));
        }
    }

    window.appPushNotifications = { mount };
})();
