requireAuth();

const productsContainer = document.getElementById('productsContainer');
const searchInput = document.getElementById('productSearch');
const categoryTabs = document.getElementById('categoryTabs');
const favoritesToggle = document.getElementById('favoritesToggle');
const favoritesSidebar = document.getElementById('favoritesSidebar');
const favoritesOverlay = document.getElementById('favoritesOverlay');
const favoritesList = document.getElementById('favoritesList');
const favoritesCount = document.getElementById('favoritesCount');

let allProducts = [];
let activeCategory = 'all';

async function loadProducts() {
    try {
        const resp = await apiGet('/products');
        allProducts = resp.products || [];
        renderTabs(allProducts);
        await loadFavorites(); // marca i preferiti prima del render
        renderProducts();
        searchInput.addEventListener('input', renderProducts);
    } catch (e) {
        productsContainer.innerHTML = `<div class="text-center text-danger mt-5">Errore nel caricamento prodotti.</div>`;
    }
}

function setupFavoritesUI() {
    if (favoritesToggle) {
        favoritesToggle.addEventListener('click', () => {
            console.log('favorites toggle clicked');
            openFavorites();
        });
    }
    if (favoritesOverlay) {
        favoritesOverlay.addEventListener('click', closeFavorites);
    }
    const closeBtn = document.getElementById('closeFavorites');
    if (closeBtn) closeBtn.addEventListener('click', closeFavorites);
}

function openFavorites() {
    if (favoritesSidebar) {
        favoritesSidebar.classList.add('open');
    }
    if (favoritesOverlay) {
        favoritesOverlay.classList.add('show');
    }
    loadFavorites();
}

function closeFavorites() {
    if (favoritesSidebar) {
        favoritesSidebar.classList.remove('open');
    }
    if (favoritesOverlay) {
        favoritesOverlay.classList.remove('show');
    }
}

async function loadFavorites() {
    try {
        const resp = await apiGet('/favorites');
        const favorites = resp.favorites || [];
        const favIds = favorites.map(f => f.id);
        allProducts = allProducts.map(p => ({
            ...p,
            is_favorite: favIds.includes(p.id),
        }));
        renderProducts();

        updateFavoritesCount(favorites.length);

        if (!favorites.length) {
            favoritesList.innerHTML = `<div class="text-muted small">Nessun preferito.</div>`;
            return;
        }
        favoritesList.innerHTML = favorites.map(p => {
            const img = p.image ? `<img src=\"${p.image}\" alt=\"${p.name}\" class=\"me-2 rounded\" style=\"width:40px;height:40px;object-fit:cover;\">` : '';
            return `<div class=\"d-flex align-items-center justify-content-between mb-2 fav-item\">
                      <div class=\"d-flex align-items-center\">${img}<div><div class=\"fw-semibold\">${p.name}</div><div class=\"text-muted small\">${p.category || ''}</div></div></div>
                      <button class=\"btn btn-sm btn-link text-danger remove-fav\" data-product=\"${p.id}\"><i class=\"bi bi-x\"></i></button>
                    </div>`;
        }).join('');
        favoritesList.querySelectorAll('.remove-fav').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = btn.getAttribute('data-product');
                const original = btn.innerHTML;
                const item = btn.closest('.fav-item');
                try {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    await apiDelete(`/favorites/${productId}`);
                    allProducts = allProducts.map(p => p.id == productId ? {...p, is_favorite: false} : p);
                    renderProducts();
                    if (item) item.remove();
                    if (!favoritesList.querySelector('.fav-item')) {
                        favoritesList.innerHTML = `<div class="text-muted small">Nessun preferito.</div>`;
                        updateFavoritesCount(0);
                    } else {
                        const current = parseInt(favoritesCount?.textContent || '0', 10);
                        updateFavoritesCount(Math.max(0, current - 1));
                    }
                } catch (err) {
                    console.error(err);
                    alert('Errore nel rimuovere il preferito');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            });
        });
    } catch (e) {
        favoritesList.innerHTML = `<div class="text-danger small">Errore nel caricamento preferiti.</div>`;
    }
}

function renderTabs(products) {
    const categories = new Set(['all']);
    products.forEach(p => {
        if (p.category) categories.add(p.category);
    });

    categoryTabs.innerHTML = '';
    categories.forEach(cat => {
        const btn = document.createElement('button');
        btn.className = 'btn cat-pill rounded-pill px-3';
        if (cat === activeCategory) btn.classList.add('active');
        btn.textContent = cat === 'all' ? 'Tutte' : cat;
        btn.onclick = () => {
            activeCategory = cat;
            renderTabs(allProducts);
            renderProducts();
        };
        categoryTabs.appendChild(btn);
    });
}

function renderProducts() {
    const term = searchInput.value.toLowerCase().trim();
    let filtered = allProducts;

    if (activeCategory !== 'all') {
        filtered = filtered.filter(p => (p.category || '') === activeCategory);
    }

    if (term) {
        filtered = filtered.filter(p =>
            p.name.toLowerCase().includes(term) ||
            (p.category || '').toLowerCase().includes(term) ||
            (p.description || '').toLowerCase().includes(term)
        );
    }

    if (!filtered.length) {
        productsContainer.innerHTML = `<div class="text-center text-muted mt-5">Nessun prodotto trovato.</div>`;
        return;
    }

    // Render a carousel-like horizontal scroller for each category block
    const byCategory = {};
    filtered.forEach(p => {
        const cat = p.category || 'Senza categoria';
        if (!byCategory[cat]) byCategory[cat] = [];
        byCategory[cat].push(p);
    });

    let html = '';
    Object.keys(byCategory).sort().forEach(cat => {
        html += `<div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <h6 class="fw-bold text-primary-color mb-0">${cat}</h6>
                    <span class="text-muted small">${byCategory[cat].length} prod.</span>
                 </div>`;
        html += `<div class="d-flex gap-3 overflow-auto pb-2">`;
        byCategory[cat].forEach(p => {
            const img = p.image ? `<div class="rounded-4 overflow-hidden mb-2" style="height:160px;"><img src="${p.image}" alt="${p.name}" style="width:200px;height:160px;object-fit:cover;"></div>` : '';
            const favClass = p.is_favorite ? 'text-warning' : 'text-muted';
            html += `
              <div class="card shadow-sm border-0 rounded-4" style="min-width:200px;">
                <div class="p-2">
                  ${img}
                  <div class="d-flex justify-content-between align-items-start">
                    <h6 class="mb-1">${p.name}</h6>
                    <button class="btn btn-link p-0 ${favClass}" data-product="${p.id}" title="Preferito">
                      <i class="bi bi-star-fill"></i>
                    </button>
                  </div>
                  <p class="text-muted small mb-0">${p.description || ''}</p>
                </div>
              </div>`;
        });
        html += `</div>`;
    });

    productsContainer.innerHTML = html;

    // bind favorite buttons
    const favButtons = productsContainer.querySelectorAll('button[data-product]');
    favButtons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const productId = btn.getAttribute('data-product');
            const isActive = btn.classList.contains('text-warning');
            const original = btn.innerHTML;
            try {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                if (isActive) {
                    await apiDelete(`/favorites/${productId}`);
                    btn.classList.remove('text-warning');
                    btn.classList.add('text-muted');
                } else {
                    await apiPost(`/favorites/${productId}`, {});
                    btn.classList.remove('text-muted');
                    btn.classList.add('text-warning');
                    openFavorites();
                    const current = parseInt(favoritesCount?.textContent || '0', 10);
                    updateFavoritesCount(current + 1);
                }
                // update state in allProducts and favorites list
                allProducts = allProducts.map(p => p.id == productId ? {...p, is_favorite: !isActive} : p);
                loadFavorites();
            } catch (err) {
                console.error(err);
                alert('Errore nel salvare il preferito');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', loadProducts);
document.addEventListener('DOMContentLoaded', setupFavoritesUI);

function updateFavoritesCount(n) {
    if (!favoritesCount) return;
    favoritesCount.textContent = n;
    favoritesCount.style.display = n ? 'inline-block' : 'none';
}
