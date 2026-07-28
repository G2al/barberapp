const productsPageState = {
    products: [],
    category: "all",
    search: "",
    toastTimer: null,
};

document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    if (!localStorage.getItem("token")) {
        window.location.href = "index.html";
        return;
    }

    hydrateUserHeader();
    setupHeaderPanels();
    setupProductSearch();
    setupFavoritesDrawer();
    await Promise.all([loadAppConfiguration(), loadProducts()]);
});

function hydrateUserHeader() {
    const user = getUser();
    document.getElementById("welcomeName").textContent = user?.name?.trim() || "cliente";
}

async function loadAppConfiguration() {
    const location = document.getElementById("shopLocation");

    try {
        const response = await fetch(`${API_BASE}/app-config`, {
            headers: { "Accept": "application/json" },
        });
        const config = await response.json();
        location.textContent = config.location || "Via Toledo 156, Napoli";
    } catch {
        location.textContent = "Via Toledo 156, Napoli";
    }
}

function setupHeaderPanels() {
    const notificationButton = document.getElementById("notificationButton");
    const logoutButton = document.getElementById("logoutButton");
    const notificationPanel = document.getElementById("notificationPanel");

    const closePanels = () => {
        notificationPanel.classList.remove("open");
        notificationButton.setAttribute("aria-expanded", "false");
    };

    notificationButton.addEventListener("click", (event) => {
        event.stopPropagation();
        const shouldOpen = !notificationPanel.classList.contains("open");
        closePanels();
        notificationPanel.classList.toggle("open", shouldOpen);
        notificationButton.setAttribute("aria-expanded", String(shouldOpen));
    });

    logoutButton.addEventListener("click", logout);

    notificationPanel.addEventListener("click", (event) => event.stopPropagation());
    document.addEventListener("click", closePanels);
}

function setupProductSearch() {
    document.getElementById("productSearch").addEventListener("input", (event) => {
        productsPageState.search = event.target.value.trim().toLocaleLowerCase("it");
        renderProducts();
    });
}

function setupFavoritesDrawer() {
    document.getElementById("openFavorites").addEventListener("click", () => {
        renderFavoritesList();
        setFavoritesDrawerOpen(true);
    });
    document.getElementById("closeFavorites").addEventListener("click", () => setFavoritesDrawerOpen(false));
    document.getElementById("favoritesOverlay").addEventListener("click", () => setFavoritesDrawerOpen(false));

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") setFavoritesDrawerOpen(false);
    });
}

function setFavoritesDrawerOpen(isOpen) {
    const drawer = document.getElementById("favoritesDrawer");
    const overlay = document.getElementById("favoritesOverlay");

    drawer.classList.toggle("open", isOpen);
    overlay.classList.toggle("open", isOpen);
    drawer.setAttribute("aria-hidden", String(!isOpen));
    document.getElementById("openFavorites").setAttribute("aria-expanded", String(isOpen));
    document.body.style.overflow = isOpen ? "hidden" : "";
}

async function loadProducts() {
    try {
        const response = await apiGet("/products");
        productsPageState.products = Array.isArray(response.products) ? response.products : [];
        renderCategoryTabs();
        renderProducts();
        updateFavoritesCount();
    } catch {
        document.getElementById("productsContainer").innerHTML = `
            <div class="catalog-message">
                <i class="bi bi-exclamation-triangle"></i>
                Impossibile caricare il catalogo. Riprova tra poco.
            </div>`;
    }
}

function renderCategoryTabs() {
    const tabs = document.getElementById("categoryTabs");
    const categories = [...new Set(productsPageState.products.map((product) => product.category).filter(Boolean))]
        .sort((first, second) => first.localeCompare(second, "it"));
    const items = [{ value: "all", label: "Tutti" }, ...categories.map((category) => ({ value: category, label: category }))];

    tabs.innerHTML = "";
    items.forEach((item) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "category-tab";
        button.classList.toggle("active", productsPageState.category === item.value);
        button.textContent = item.label;
        button.addEventListener("click", () => {
            productsPageState.category = item.value;
            renderCategoryTabs();
            renderProducts();
        });
        tabs.appendChild(button);
    });
}

function renderProducts() {
    const container = document.getElementById("productsContainer");
    const products = productsPageState.products.filter((product) => {
        const categoryMatches = productsPageState.category === "all"
            || product.category === productsPageState.category;
        const searchableText = `${product.name || ""} ${product.category || ""} ${product.description || ""}`
            .toLocaleLowerCase("it");
        const searchMatches = !productsPageState.search || searchableText.includes(productsPageState.search);

        return categoryMatches && searchMatches;
    });

    container.innerHTML = "";

    if (!products.length) {
        container.innerHTML = `
            <div class="catalog-message">
                <i class="bi bi-bag-x"></i>
                Nessun prodotto corrisponde alla ricerca.
            </div>`;
        return;
    }

    products.forEach((product) => container.appendChild(createProductCard(product)));
}

function createProductCard(product) {
    const card = document.createElement("article");
    card.className = "product-card";
    card.dataset.productId = String(product.id);
    card.innerHTML = `
        <div class="product-image">
            ${product.image
                ? `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name || "Prodotto")}">`
                : '<i class="bi bi-bag"></i>'}
            <button
                type="button"
                class="favorite-button ${product.is_favorite ? "active" : ""}"
                data-product-id="${escapeHtml(product.id)}"
                aria-label="${product.is_favorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti"}"
                aria-pressed="${String(Boolean(product.is_favorite))}"
                title="${product.is_favorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti"}">
                <i class="bi ${product.is_favorite ? "bi-heart-fill" : "bi-heart"}"></i>
            </button>
        </div>
        <div class="product-copy">
            <p class="product-category">${escapeHtml(product.category || "Prodotto")}</p>
            <h3 class="product-name">${escapeHtml(product.name || "Prodotto")}</h3>
            ${product.description
                ? `<p class="product-description">${escapeHtml(product.description)}</p>`
                : ""}
        </div>`;

    card.querySelector(".favorite-button").addEventListener("click", (event) => {
        toggleFavorite(product, event.currentTarget);
    });

    const image = card.querySelector(".product-image img");
    image?.addEventListener("error", () => {
        image.replaceWith(createFallbackIcon());
    }, { once: true });

    return card;
}

async function toggleFavorite(product, button) {
    const wasFavorite = Boolean(product.is_favorite);
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    try {
        if (wasFavorite) {
            await apiDelete(`/favorites/${product.id}`);
        } else {
            await apiPost(`/favorites/${product.id}`, {});
        }

        product.is_favorite = !wasFavorite;
        syncFavoriteButtons(product);
        updateFavoritesCount();
        renderFavoritesList();
        showFeedback(product.is_favorite ? "Aggiunto ai preferiti" : "Rimosso dai preferiti");
    } catch {
        button.disabled = false;
        button.innerHTML = `<i class="bi ${wasFavorite ? "bi-heart-fill" : "bi-heart"}"></i>`;
        showFeedback("Impossibile aggiornare i preferiti");
    }
}

function syncFavoriteButtons(product) {
    document.querySelectorAll(`.favorite-button[data-product-id="${product.id}"]`).forEach((favoriteButton) => {
        favoriteButton.disabled = false;
        favoriteButton.classList.toggle("active", Boolean(product.is_favorite));
        favoriteButton.classList.remove("favorite-pop");
        favoriteButton.setAttribute("aria-pressed", String(Boolean(product.is_favorite)));
        favoriteButton.setAttribute(
            "aria-label",
            product.is_favorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti"
        );
        favoriteButton.title = product.is_favorite ? "Rimuovi dai preferiti" : "Aggiungi ai preferiti";
        favoriteButton.innerHTML = `<i class="bi ${product.is_favorite ? "bi-heart-fill" : "bi-heart"}"></i>`;
        void favoriteButton.offsetWidth;
        favoriteButton.classList.add("favorite-pop");
    });
}

function renderFavoritesList() {
    const list = document.getElementById("favoritesList");
    const favorites = productsPageState.products.filter((product) => product.is_favorite);
    list.innerHTML = "";

    if (!favorites.length) {
        list.innerHTML = `
            <div class="favorites-empty">
                <i class="bi bi-heart"></i>
                Non hai ancora aggiunto prodotti ai preferiti.
            </div>`;
        return;
    }

    favorites.forEach((product) => {
        const item = document.createElement("article");
        item.className = "favorite-list-item";
        item.innerHTML = `
            <div class="favorite-list-image">
                ${product.image
                    ? `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name || "Prodotto")}">`
                    : '<i class="bi bi-bag"></i>'}
            </div>
            <div class="favorite-list-copy">
                <p class="favorite-list-category">${escapeHtml(product.category || "Prodotto")}</p>
                <h3 class="favorite-list-name">${escapeHtml(product.name || "Prodotto")}</h3>
            </div>
            <button
                type="button"
                class="remove-favorite"
                aria-label="Rimuovi ${escapeHtml(product.name || "prodotto")} dai preferiti"
                title="Rimuovi dai preferiti">
                <i class="bi bi-heart-fill"></i>
            </button>`;

        item.querySelector(".remove-favorite").addEventListener("click", (event) => {
            toggleFavorite(product, event.currentTarget);
        });

        const image = item.querySelector("img");
        image?.addEventListener("error", () => {
            image.replaceWith(createFallbackIcon());
        }, { once: true });

        list.appendChild(item);
    });
}

function updateFavoritesCount() {
    const count = productsPageState.products.filter((product) => product.is_favorite).length;
    const total = document.getElementById("openFavorites");
    document.getElementById("favoritesCount").textContent = String(count);
    total.classList.remove("favorite-pop");
    void total.offsetWidth;
    total.classList.add("favorite-pop");
}

function showFeedback(message) {
    if (window.appToast) {
        const isError = /impossibile|errore/i.test(message);
        window.appToast(message, isError ? "error" : "success");
        return;
    }

    const toast = document.getElementById("feedbackToast");
    window.clearTimeout(productsPageState.toastTimer);
    toast.textContent = message;
    toast.classList.add("show");
    productsPageState.toastTimer = window.setTimeout(() => toast.classList.remove("show"), 1800);
}

function createFallbackIcon() {
    const icon = document.createElement("i");
    icon.className = "bi bi-bag";
    return icon;
}

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/service-worker.js").catch(() => null);
}
