document.addEventListener("DOMContentLoaded", () => {
    const favoritesToggle = document.getElementById("favoritesToggle");
    const favoritesSidebar = document.getElementById("favoritesSidebar");
    const favoritesOverlay = document.getElementById("favoritesOverlay");
    const favoritesList = document.getElementById("favoritesList");
    const favoritesCount = document.getElementById("favoritesCount");
    const closeFavorites = document.getElementById("closeFavorites");

    if (!favoritesToggle || !favoritesSidebar || !favoritesOverlay || !favoritesList) return;

    const updateFavoritesCount = (count) => {
        if (!favoritesCount) return;
        favoritesCount.textContent = count;
        favoritesCount.style.display = count ? "inline-block" : "none";
    };

    const renderFavorites = (favorites) => {
        updateFavoritesCount(favorites.length);

        if (!favorites.length) {
            favoritesList.innerHTML = `<div class="text-muted small">Nessun preferito.</div>`;
            return;
        }

        favoritesList.innerHTML = favorites.map((product) => {
            const image = product.image
                ? `<img src="${product.image}" alt="${product.name}" class="me-2 rounded" style="width:42px;height:42px;object-fit:cover;">`
                : "";

            return `
                <div class="d-flex align-items-center justify-content-between mb-2 fav-item">
                    <div class="d-flex align-items-center">
                        ${image}
                        <div>
                            <div class="fw-semibold">${product.name}</div>
                            <div class="text-muted small">${product.category || ""}</div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-link text-danger remove-fav" data-product="${product.id}" title="Rimuovi">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
        }).join("");

        favoritesList.querySelectorAll(".remove-fav").forEach((button) => {
            button.addEventListener("click", async (event) => {
                event.preventDefault();
                const productId = button.getAttribute("data-product");
                const original = button.innerHTML;

                try {
                    button.disabled = true;
                    button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>`;
                    await apiDelete(`/favorites/${productId}`);
                    await loadFavorites();
                } catch (error) {
                    console.error(error);
                    favoritesList.innerHTML = `<div class="text-danger small">Errore nel rimuovere il preferito.</div>`;
                } finally {
                    button.disabled = false;
                    button.innerHTML = original;
                }
            });
        });
    };

    const loadFavorites = async () => {
        try {
            const response = await apiGet("/favorites");
            renderFavorites(response.favorites || []);
        } catch (error) {
            console.error(error);
            favoritesList.innerHTML = `<div class="text-danger small">Errore nel caricamento preferiti.</div>`;
        }
    };

    const openFavorites = async () => {
        favoritesSidebar.classList.add("open");
        favoritesOverlay.classList.add("show");
        await loadFavorites();
    };

    const closeDrawer = () => {
        favoritesSidebar.classList.remove("open");
        favoritesOverlay.classList.remove("show");
    };

    favoritesToggle.addEventListener("click", openFavorites);
    favoritesOverlay.addEventListener("click", closeDrawer);
    closeFavorites?.addEventListener("click", closeDrawer);
    loadFavorites();
});
