(function () {
    const launchLoaderVisible = shouldShowLaunchLoader();
    const state = {
        loaderCount: launchLoaderVisible ? 1 : 0,
        confirmResolve: null,
    };

    function shouldShowLaunchLoader() {
        const isStandalone = window.matchMedia("(display-mode: standalone)").matches
            || window.navigator.standalone === true;

        if (!isStandalone) return false;

        try {
            if (window.sessionStorage.getItem("gaetabet-launch-loader-shown") === "1") {
                return false;
            }

            window.sessionStorage.setItem("gaetabet-launch-loader-shown", "1");
        } catch {
            // Il loader resta disponibile anche se lo storage del browser e' bloccato.
        }

        return true;
    }

    function mountUi() {
        if (document.getElementById("appLoader")) return;

        document.body.insertAdjacentHTML("beforeend", `
            <div id="appLoader" class="app-loader${launchLoaderVisible ? " show" : ""}" role="status" aria-live="polite">
                <div class="app-loader-inner">
                    <div class="app-loader-mark">
                        <span class="app-loader-ring"></span>
                        <span class="app-loader-icon"><i class="bi bi-scissors"></i></span>
                    </div>
                    <p id="appLoaderLabel" class="app-loader-label">Prepariamo il tuo stile</p>
                </div>
            </div>
            <div id="appToastStack" class="app-toast-stack" aria-live="polite"></div>
            <div id="appConfirmOverlay" class="app-confirm-overlay" aria-hidden="true">
                <section id="appConfirmDialog" class="app-confirm" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle">
                    <span class="app-confirm-icon"><i id="appConfirmIcon" class="bi bi-question-lg"></i></span>
                    <h2 id="appConfirmTitle" class="app-confirm-title">Conferma</h2>
                    <p id="appConfirmMessage" class="app-confirm-message"></p>
                    <div class="app-confirm-actions">
                        <button type="button" id="appConfirmCancel" class="app-confirm-button">Annulla</button>
                        <button type="button" id="appConfirmAccept" class="app-confirm-button primary">Conferma</button>
                    </div>
                </section>
            </div>
        `);

        document.getElementById("appConfirmCancel").addEventListener("click", () => closeConfirm(false));
        document.getElementById("appConfirmAccept").addEventListener("click", () => closeConfirm(true));
        document.getElementById("appConfirmOverlay").addEventListener("click", (event) => {
            if (event.target === event.currentTarget) closeConfirm(false);
        });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && state.confirmResolve) closeConfirm(false);
        });
    }

    function loaderShow(label = "Caricamento in corso") {
        mountUi();
        state.loaderCount += 1;
        document.getElementById("appLoaderLabel").textContent = label;
        document.getElementById("appLoader").classList.add("show");
    }

    function loaderHide() {
        state.loaderCount = Math.max(0, state.loaderCount - 1);
        if (state.loaderCount > 0) return;

        document.getElementById("appLoader")?.classList.remove("show");
    }

    function loaderClear() {
        state.loaderCount = 0;
        document.getElementById("appLoader")?.classList.remove("show");
    }

    function toast(message, type = "info", options = {}) {
        mountUi();
        const normalizedType = type === "danger" ? "error" : type;
        const config = {
            success: { title: "Operazione completata", icon: "bi-check-lg" },
            error: { title: "Qualcosa non va", icon: "bi-exclamation-lg" },
            info: { title: "Informazione", icon: "bi-info-lg" },
        }[normalizedType] || { title: "Informazione", icon: "bi-info-lg" };

        const item = document.createElement("article");
        item.className = `app-toast ${normalizedType}`;
        item.innerHTML = `
            <span class="app-toast-icon"><i class="bi ${config.icon}"></i></span>
            <div>
                <p class="app-toast-title">${escapeHtml(options.title || config.title)}</p>
                <p class="app-toast-message">${escapeHtml(message || "")}</p>
            </div>
            <button type="button" class="app-toast-close" aria-label="Chiudi"><i class="bi bi-x-lg"></i></button>
        `;

        const remove = () => {
            item.classList.remove("show");
            window.setTimeout(() => item.remove(), 190);
        };

        item.querySelector(".app-toast-close").addEventListener("click", remove);
        document.getElementById("appToastStack").appendChild(item);
        requestAnimationFrame(() => item.classList.add("show"));
        window.setTimeout(remove, options.duration || 3400);
        return item;
    }

    function confirmDialog(message, options = {}) {
        mountUi();
        if (state.confirmResolve) closeConfirm(false);

        const isDanger = options.type === "danger";
        const dialog = document.getElementById("appConfirmDialog");
        dialog.classList.toggle("danger", isDanger);
        document.getElementById("appConfirmIcon").className =
            `bi ${isDanger ? "bi-exclamation-lg" : "bi-question-lg"}`;
        document.getElementById("appConfirmTitle").textContent = options.title || "Conferma operazione";
        document.getElementById("appConfirmMessage").textContent = message || "";
        document.getElementById("appConfirmCancel").textContent = options.cancelText || "Annulla";
        document.getElementById("appConfirmAccept").textContent = options.confirmText || "Conferma";

        const overlay = document.getElementById("appConfirmOverlay");
        overlay.classList.add("show");
        overlay.setAttribute("aria-hidden", "false");

        return new Promise((resolve) => {
            state.confirmResolve = resolve;
            window.setTimeout(() => document.getElementById("appConfirmAccept").focus(), 180);
        });
    }

    function closeConfirm(result) {
        const resolve = state.confirmResolve;
        if (!resolve) return;

        state.confirmResolve = null;
        const overlay = document.getElementById("appConfirmOverlay");
        overlay.classList.remove("show");
        overlay.setAttribute("aria-hidden", "true");
        resolve(result);
    }

    function setButtonState(button, status, options = {}) {
        if (!button) return;

        window.clearTimeout(Number(button.dataset.appStateTimer || 0));

        if (!button.dataset.appOriginalHtml) {
            button.dataset.appOriginalHtml = button.innerHTML;
        }

        button.classList.remove("app-button-loading", "app-button-success", "app-button-error");
        button.classList.add("app-button-state");

        if (status === "reset") {
            button.innerHTML = button.dataset.appOriginalHtml;
            button.disabled = Boolean(options.disabled);
            return;
        }

        const states = {
            loading: {
                className: "app-button-loading",
                icon: '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>',
                label: options.label || "Operazione in corso...",
            },
            success: {
                className: "app-button-success",
                icon: '<i class="bi bi-check-lg" aria-hidden="true"></i>',
                label: options.label || "Operazione riuscita",
            },
            error: {
                className: "app-button-error",
                icon: '<i class="bi bi-exclamation-lg" aria-hidden="true"></i>',
                label: options.label || "Operazione non riuscita",
            },
        };

        const next = states[status];
        if (!next) return;

        button.disabled = true;
        button.classList.add(next.className);
        button.innerHTML = `<span class="app-button-state-content">${next.icon}<span>${escapeHtml(next.label)}</span></span>`;

        if (options.resetAfter) {
            button.dataset.appStateTimer = String(window.setTimeout(() => {
                setButtonState(button, "reset", { disabled: Boolean(options.disabledAfterReset) });
                options.onReset?.();
            }, options.resetAfter));
        }
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.appLoader = { show: loaderShow, hide: loaderHide, clear: loaderClear };
    window.appToast = toast;
    window.appConfirm = confirmDialog;
    window.appButtonState = setButtonState;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", mountUi, { once: true });
    } else {
        mountUi();
    }

    window.addEventListener("load", loaderHide, { once: true });
    window.addEventListener("unhandledrejection", () => {
        loaderClear();
        toast("Controlla la connessione e riprova tra qualche istante.", "error", {
            title: "Operazione non riuscita",
        });
    });
})();
