(function () {
    const routes = {
        "/dashboard.html": { key: "dashboard", script: "/js/dashboard.js", order: 0 },
        "/my-bookings.html": { key: "bookings", script: "/js/my-bookings.js", order: 1 },
        "/products.html": { key: "products", script: "/js/products.js", order: 2 },
        "/profile.html": { key: "profile", script: "/js/profile.js", order: 3 },
    };

    const pageCache = new Map();
    const scriptPromises = new Map();
    let currentPath = normalizePath(window.location.pathname);
    let navigationId = 0;
    let navigating = false;
    let portalHost = null;
    let pageStyle = null;

    const currentRoute = routes[currentPath];
    if (!currentRoute) return;

    window.AppSpa = {
        active: true,
        navigate,
        get currentPath() {
            return currentPath;
        },
    };

    function normalizePath(value) {
        const url = new URL(value || "/dashboard.html", window.location.origin);
        let path = url.pathname;

        if (path === "/" || path.endsWith("/dashboard")) {
            path = "/dashboard.html";
        }

        return path.startsWith("/") ? path : `/${path}`;
    }

    function initialize() {
        const shell = document.querySelector(".app-shell");
        const navigation = document.querySelector(".bottom-nav");
        if (!shell || !navigation) return;

        pageStyle = document.head.querySelector("[data-spa-page-style]");

        portalHost = document.createElement("div");
        portalHost.id = "spaPagePortals";
        navigation.before(portalHost);

        Array.from(document.body.children).forEach((element) => {
            if (
                element === shell
                || element === navigation
                || element === portalHost
                || element.tagName === "SCRIPT"
                || ["appLoader", "appToastStack", "appConfirmOverlay", "appRouteTransition"].includes(element.id)
            ) {
                return;
            }

            portalHost.appendChild(element);
        });

        history.replaceState({ appSpa: true, path: currentPath }, "", window.location.href);
        window.addEventListener("popstate", () => {
            void navigate(window.location.href, { historyMode: "none" });
        });

        window.addEventListener("pageshow", (event) => {
            if (event.persisted) {
                window.appBottomNavigation?.setActive(currentPath, false);
            }
        });

        window.AppPages?.[currentRoute.key]?.mount();
        schedulePrefetch();
    }

    async function navigate(href, options = {}) {
        const destination = new URL(href, window.location.href);
        const targetPath = normalizePath(destination.pathname);
        const targetRoute = routes[targetPath];

        if (!targetRoute) {
            window.location.assign(destination.href);
            return;
        }

        if (targetPath === currentPath) {
            window.scrollTo({ top: 0, behavior: "smooth" });
            window.appBottomNavigation?.setActive(targetPath);
            return;
        }

        const requestId = ++navigationId;
        const previousPath = currentPath;
        const previousRoute = routes[previousPath];
        const direction = targetRoute.order > previousRoute.order ? "forward" : "back";

        navigating = true;
        document.body.classList.add("spa-is-navigating");
        window.appBottomNavigation?.setActive(targetPath);

        try {
            const [page] = await Promise.all([
                getPage(targetPath),
                ensureModule(targetRoute),
                waitForMinimumFrame(),
            ]);

            if (requestId !== navigationId) return;

            await swapPage(page, targetRoute, targetPath, direction);

            if (options.historyMode !== "none") {
                history.pushState({ appSpa: true, path: targetPath }, "", destination.href);
            }
        } catch (error) {
            console.error("Navigazione dinamica non disponibile:", error);
            window.appBottomNavigation?.setActive(previousPath);
            window.location.assign(destination.href);
        } finally {
            if (requestId === navigationId) {
                navigating = false;
                document.body.classList.remove("spa-is-navigating");
            }
        }
    }

    async function swapPage(page, targetRoute, targetPath, direction) {
        const currentShell = document.querySelector(".app-shell");
        if (!currentShell || !page.shell) {
            throw new Error("Struttura della pagina non valida.");
        }

        const canUsePreparedTransition = (
            typeof document.startViewTransition === "function"
            && !window.matchMedia("(prefers-reduced-motion: reduce)").matches
        );

        if (canUsePreparedTransition) {
            document.documentElement.dataset.spaDirection = direction;

            const transition = document.startViewTransition(() => (
                commitPage(page, targetRoute, targetPath, currentShell)
            ));

            try {
                await transition.finished;
            } finally {
                delete document.documentElement.dataset.spaDirection;
            }

            return;
        }

        currentShell.classList.add(direction === "forward" ? "spa-leave-left" : "spa-leave-right");
        await delay(90);

        const nextShell = page.shell;
        nextShell.classList.add(direction === "forward" ? "spa-enter-right" : "spa-enter-left");
        await commitPage(page, targetRoute, targetPath, currentShell);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                nextShell.classList.add("spa-enter-active");
            });
        });

        window.setTimeout(() => {
            nextShell.classList.remove("spa-enter-right", "spa-enter-left", "spa-enter-active");
        }, 260);
    }

    async function commitPage(page, targetRoute, targetPath, currentShell) {
        window.AppPages?.[routes[currentPath].key]?.unmount?.();

        document.title = page.title;
        document.body.className = page.bodyClass;
        document.body.classList.add("spa-is-navigating");

        if (pageStyle) {
            pageStyle.textContent = page.styleText;
        }

        const nextShell = page.shell;
        currentShell.replaceWith(nextShell);

        portalHost.replaceChildren(...page.portals);
        currentPath = targetPath;
        window.scrollTo({ top: 0, left: 0, behavior: "auto" });
        window.appBottomNavigation?.setActive(targetPath);

        await Promise.resolve(window.AppPages?.[targetRoute.key]?.mount?.()).catch((error) => {
            console.error(`Errore durante l'apertura della sezione ${targetRoute.key}:`, error);
            window.appToast?.("Non è stato possibile caricare completamente la sezione.", "error");
        });
    }

    async function getPage(path) {
        let html = pageCache.get(path);

        if (!html) {
            const response = await fetch(path, {
                headers: {
                    "Accept": "text/html",
                    "X-App-Navigation": "spa",
                },
            });

            if (!response.ok) {
                throw new Error(`Pagina non disponibile: ${response.status}`);
            }

            html = await response.text();
            pageCache.set(path, html);
        }

        const parsed = new DOMParser().parseFromString(html, "text/html");
        const shell = parsed.querySelector(".app-shell");
        const navigation = parsed.querySelector(".bottom-nav");
        const style = parsed.head.querySelector("[data-spa-page-style]");
        const portals = Array.from(parsed.body.children).filter((element) => (
            element !== shell
            && element !== navigation
            && element.tagName !== "SCRIPT"
        ));

        return {
            title: parsed.title,
            bodyClass: parsed.body.className,
            styleText: style?.textContent || "",
            shell,
            portals,
        };
    }

    function ensureModule(route) {
        if (window.AppPages?.[route.key]) return Promise.resolve();
        if (scriptPromises.has(route.script)) return scriptPromises.get(route.script);

        const promise = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = route.script;
            script.dataset.spaModule = route.key;
            script.onload = resolve;
            script.onerror = () => reject(new Error(`Script non disponibile: ${route.script}`));
            document.body.appendChild(script);
        });

        scriptPromises.set(route.script, promise);
        return promise;
    }

    function schedulePrefetch() {
        const prefetch = async () => {
            for (const [path, route] of Object.entries(routes)) {
                if (path === currentPath) continue;
                await getPage(path).catch(() => null);
                await ensureModule(route).catch(() => null);
                await delay(60);
            }
        };

        window.setTimeout(() => {
            if ("requestIdleCallback" in window) {
                window.requestIdleCallback(() => void prefetch(), { timeout: 1800 });
            } else {
                void prefetch();
            }
        }, 900);
    }

    function waitForMinimumFrame() {
        if (!navigating) return Promise.resolve();
        return new Promise((resolve) => requestAnimationFrame(() => resolve()));
    }

    function delay(milliseconds) {
        return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, { once: true });
    } else {
        initialize();
    }
})();
