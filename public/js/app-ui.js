(function () {
    const launchLoaderVisible = shouldShowLaunchLoader();
    const state = {
        loaderCount: launchLoaderVisible ? 1 : 0,
        confirmResolve: null,
        routeTransitionActive: false,
    };

    function shouldShowLaunchLoader() {
        const isStandalone = window.matchMedia("(display-mode: standalone)").matches
            || window.navigator.standalone === true;

        if (!isStandalone) return false;

        const path = window.location.pathname.replace(/\/+$/, "") || "/";
        return path === "/" || path === "/index.html";
    }

    function mountUi() {
        if (document.getElementById("appLoader")) return;

        let enterFromNavigation = false;
        try {
            enterFromNavigation = window.sessionStorage.getItem("gaetabet-route-transition") === "1";
            window.sessionStorage.removeItem("gaetabet-route-transition");
        } catch {
            // La transizione funziona anche senza sessionStorage.
        }

        document.body.insertAdjacentHTML("beforeend", `
            <div id="appLoader" class="app-loader${launchLoaderVisible ? " show" : ""}" role="status" aria-live="polite">
                <div class="app-loader-inner">
                    <div class="app-loader-mark">
                        <span class="app-loader-ring"></span>
                        <span class="app-loader-icon">
                            <img src="/images/stile-infinito-logo-white.png" alt="" width="160" height="160">
                        </span>
                    </div>
                    <p id="appLoaderLabel" class="app-loader-label">Stiamo aprendo l'app</p>
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
            <div id="appRouteTransition" class="app-route-transition${enterFromNavigation ? " show" : ""}" aria-hidden="true"></div>
        `);

        document.getElementById("appConfirmCancel").addEventListener("click", () => closeConfirm(false));
        document.getElementById("appConfirmAccept").addEventListener("click", () => closeConfirm(true));
        document.getElementById("appConfirmOverlay").addEventListener("click", (event) => {
            if (event.target === event.currentTarget) closeConfirm(false);
        });
        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && state.confirmResolve) closeConfirm(false);
        });

        if (enterFromNavigation) {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    document.getElementById("appRouteTransition")?.classList.remove("show");
                });
            });
        }
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

    function navigateWithTransition(url, options = {}) {
        if (state.routeTransitionActive) return;

        mountUi();
        state.routeTransitionActive = true;

        try {
            window.sessionStorage.setItem("gaetabet-route-transition", "1");
        } catch {
            // Nessun dato applicativo dipende dalla transizione.
        }

        const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        const delay = reducedMotion ? 0 : (options.delay ?? 230);

        document.body.classList.add("app-route-leaving");
        document.getElementById("appRouteTransition")?.classList.add("show");

        window.setTimeout(() => {
            window.location.assign(url);
        }, delay);
    }

    function setupAppExperience() {
        setupBottomNavigation();
        setupRevealAnimations();
    }

    function setupBottomNavigation() {
        const navigation = document.querySelector(".bottom-nav");
        if (!navigation || navigation.dataset.appNavigationReady === "true") return;

        const items = Array.from(navigation.querySelectorAll(".nav-item"));
        const activeItem = navigation.querySelector(".nav-item.active") || items[0];
        if (!items.length || !activeItem) return;

        navigation.dataset.appNavigationReady = "true";
        navigation.classList.add("app-bottom-nav");

        const indicator = document.createElement("span");
        indicator.className = "app-nav-indicator";
        indicator.setAttribute("aria-hidden", "true");
        navigation.appendChild(indicator);

        items.forEach((item) => {
            item.setAttribute("aria-current", item === activeItem ? "page" : "false");
        });

        const moveIndicator = (item, animate = true) => {
            if (!item) return;

            const indicatorWidth = 34;
            const offset = item.offsetLeft + ((item.offsetWidth - indicatorWidth) / 2);
            indicator.style.setProperty("--app-nav-offset", `${offset}px`);
            indicator.classList.toggle("animate", animate);
        };

        const setActiveItem = (href, animate = true) => {
            const targetPath = new URL(href, window.location.href).pathname;
            const item = items.find((candidate) => (
                new URL(candidate.href, window.location.href).pathname === targetPath
            ));
            if (!item) return;

            items.forEach((navItem) => {
                const isActive = navItem === item;
                navItem.classList.toggle("active", isActive);
                navItem.setAttribute("aria-current", isActive ? "page" : "false");
            });
            moveIndicator(item, animate);
        };

        window.appBottomNavigation = {
            setActive: setActiveItem,
        };

        moveIndicator(activeItem, false);
        window.requestAnimationFrame(() => {
            navigation.classList.add("ready");
            moveIndicator(activeItem, false);
        });

        items.forEach((item) => {
            item.addEventListener("click", (event) => {
                if (
                    event.defaultPrevented
                    || event.button !== 0
                    || event.metaKey
                    || event.ctrlKey
                    || event.shiftKey
                    || event.altKey
                ) {
                    return;
                }

                const currentItem = navigation.querySelector(".nav-item.active") || activeItem;

                if (item === currentItem) {
                    event.preventDefault();
                    item.classList.remove("app-nav-reselect");
                    void item.offsetWidth;
                    item.classList.add("app-nav-reselect");
                    window.scrollTo({ top: 0, behavior: "smooth" });
                    return;
                }

                event.preventDefault();

                items.forEach((navItem) => {
                    const isActive = navItem === item;
                    navItem.classList.toggle("active", isActive);
                    navItem.setAttribute("aria-current", isActive ? "page" : "false");
                });
                moveIndicator(item);

                window.requestAnimationFrame(() => {
                    if (window.AppSpa?.navigate) {
                        window.AppSpa.navigate(item.href);
                    } else {
                        window.location.assign(item.href);
                    }
                });
            });
        });

        window.addEventListener("resize", () => {
            moveIndicator(navigation.querySelector(".nav-item.active"), false);
        }, { passive: true });
    }

    function setupRevealAnimations() {
        const selector = [
            ".booking-card",
            ".product-card",
            ".favorite-list-item",
            ".profile-field",
            ".staff-option",
            ".service-select-option",
            ".time-period",
        ].join(",");

        const reveal = (element) => {
            if (!(element instanceof HTMLElement) || element.dataset.appRevealed === "true") return;

            const siblings = element.parentElement
                ? Array.from(element.parentElement.children).filter((child) => child.matches?.(selector))
                : [];
            const index = Math.max(0, siblings.indexOf(element));

            element.dataset.appRevealed = "true";
            element.classList.add("app-reveal-item");
            element.style.setProperty("--app-item-index", String(Math.min(index, 7)));
            element.style.setProperty("--app-item-delay", `${Math.min(index, 7) * 24}ms`);
            window.requestAnimationFrame(() => element.classList.add("app-revealed"));
        };

        document.querySelectorAll(selector).forEach(reveal);

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    if (node.matches(selector)) reveal(node);
                    node.querySelectorAll(selector).forEach(reveal);
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
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
    window.appNavigate = navigateWithTransition;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            mountUi();
            setupAppExperience();
        }, { once: true });
    } else {
        mountUi();
        setupAppExperience();
    }

    window.addEventListener("load", loaderHide, { once: true });
    window.addEventListener("unhandledrejection", () => {
        loaderClear();
        toast("Controlla la connessione e riprova tra qualche istante.", "error", {
            title: "Operazione non riuscita",
        });
    });
})();
