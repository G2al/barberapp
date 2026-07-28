const bookingsPageState = {
    bookings: [],
    quickStatus: "confirmed",
    search: "",
    filters: {
        staff: "",
        status: "",
        from: "",
        to: "",
    },
};

const bookingStatusLabels = {
    pending: "In sospeso",
    confirmed: "Confermata",
    completed: "Completata",
    cancelled: "Annullata",
    no_show: "Assente",
};

document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    if (!localStorage.getItem("token")) {
        window.location.href = "index.html";
        return;
    }

    hydrateUserHeader();
    setupHeaderPanels();
    setupSearch();
    setupStatusTabs();
    setupFilterDrawer();
    await Promise.all([loadAppConfiguration(), loadBookings()]);
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

function setupSearch() {
    document.getElementById("bookingSearch").addEventListener("input", (event) => {
        bookingsPageState.search = event.target.value.trim().toLocaleLowerCase("it");
        renderBookings();
    });
}

function setupStatusTabs() {
    const tabs = document.getElementById("statusTabs");

    tabs.addEventListener("click", (event) => {
        const button = event.target.closest(".status-tab");
        if (!button) return;

        tabs.querySelectorAll(".status-tab").forEach((tab) => tab.classList.remove("active"));
        button.classList.add("active");
        bookingsPageState.quickStatus = button.dataset.status || "all";
        renderBookings();
    });
}

function setupFilterDrawer() {
    document.getElementById("openFilters").addEventListener("click", () => setFilterDrawerOpen(true));
    document.getElementById("closeFilters").addEventListener("click", () => setFilterDrawerOpen(false));
    document.getElementById("filterOverlay").addEventListener("click", () => setFilterDrawerOpen(false));
    document.getElementById("applyFilters").addEventListener("click", applyDrawerFilters);
    document.getElementById("resetFilters").addEventListener("click", resetDrawerFilters);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") setFilterDrawerOpen(false);
    });
}

function setFilterDrawerOpen(isOpen) {
    const drawer = document.getElementById("filterDrawer");
    const overlay = document.getElementById("filterOverlay");

    drawer.classList.toggle("open", isOpen);
    overlay.classList.toggle("open", isOpen);
    drawer.setAttribute("aria-hidden", String(!isOpen));
    document.getElementById("openFilters").setAttribute("aria-expanded", String(isOpen));
    document.body.style.overflow = isOpen ? "hidden" : "";
}

function applyDrawerFilters() {
    bookingsPageState.filters = {
        staff: document.getElementById("staffFilter").value,
        status: document.getElementById("statusFilter").value,
        from: document.getElementById("dateFromFilter").value,
        to: document.getElementById("dateToFilter").value,
    };

    updateFilterIndicator();
    setFilterDrawerOpen(false);
    renderBookings();
}

function resetDrawerFilters() {
    document.getElementById("staffFilter").value = "";
    document.getElementById("statusFilter").value = "";
    document.getElementById("dateFromFilter").value = "";
    document.getElementById("dateToFilter").value = "";
    bookingsPageState.filters = { staff: "", status: "", from: "", to: "" };
    updateFilterIndicator();
    renderBookings();
}

function updateFilterIndicator() {
    const hasFilters = Object.values(bookingsPageState.filters).some(Boolean);
    document.getElementById("openFilters").classList.toggle("has-filters", hasFilters);
}

async function loadBookings() {
    const token = localStorage.getItem("token");

    try {
        const response = await fetch(`${API_BASE}/bookings`, {
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json",
            },
        });
        const data = await response.json();

        if (!response.ok || data.status !== true) {
            throw new Error(data.message || "Impossibile caricare le prenotazioni.");
        }

        bookingsPageState.bookings = Array.isArray(data.bookings) ? data.bookings : [];
        populateStaffFilter();
        renderBookings();
    } catch (error) {
        document.getElementById("bookingsList").innerHTML = `
            <div class="list-message">
                <i class="bi bi-exclamation-triangle"></i>
                Impossibile caricare le prenotazioni. Riprova tra poco.
            </div>`;
    }
}

function populateStaffFilter() {
    const select = document.getElementById("staffFilter");
    const staffNames = [...new Set(bookingsPageState.bookings.map((booking) => booking.staff).filter(Boolean))]
        .sort((first, second) => first.localeCompare(second, "it"));

    select.innerHTML = '<option value="">Tutto lo staff</option>';
    staffNames.forEach((staffName) => {
        const option = document.createElement("option");
        option.value = staffName;
        option.textContent = staffName;
        select.appendChild(option);
    });
}

function renderBookings() {
    const list = document.getElementById("bookingsList");
    const filtered = bookingsPageState.bookings
        .filter(matchesCurrentFilters)
        .sort(sortBookingsByRelevance);
    list.innerHTML = "";

    if (!filtered.length) {
        list.innerHTML = `
            <div class="list-message">
                <i class="bi bi-calendar-x"></i>
                Nessuna prenotazione corrisponde ai filtri selezionati.
            </div>`;
        return;
    }

    filtered.forEach((booking) => list.appendChild(createBookingCard(booking)));
}

function sortBookingsByRelevance(firstBooking, secondBooking) {
    const now = Date.now();
    const firstTimestamp = getBookingTimestamp(firstBooking);
    const secondTimestamp = getBookingTimestamp(secondBooking);
    const firstIsUpcoming = firstTimestamp >= now;
    const secondIsUpcoming = secondTimestamp >= now;

    if (firstIsUpcoming !== secondIsUpcoming) {
        return firstIsUpcoming ? -1 : 1;
    }

    return firstIsUpcoming
        ? firstTimestamp - secondTimestamp
        : secondTimestamp - firstTimestamp;
}

function getBookingTimestamp(booking) {
    const dateParts = normalizeDate(booking.date).split("-").map(Number);
    const timeParts = formatTime(booking.time).split(":").map(Number);

    if (dateParts.length !== 3 || dateParts.some(Number.isNaN)) {
        return Number.NEGATIVE_INFINITY;
    }

    const [year, month, day] = dateParts;
    const [hours = 0, minutes = 0] = timeParts;
    const timestamp = new Date(year, month - 1, day, hours, minutes).getTime();

    return Number.isFinite(timestamp) ? timestamp : Number.NEGATIVE_INFINITY;
}

function matchesCurrentFilters(booking) {
    const quickStatusMatches = bookingsPageState.quickStatus === "all"
        || booking.status === bookingsPageState.quickStatus;
    const searchValue = `${booking.service || ""} ${booking.staff || ""}`.toLocaleLowerCase("it");
    const searchMatches = !bookingsPageState.search || searchValue.includes(bookingsPageState.search);
    const staffMatches = !bookingsPageState.filters.staff || booking.staff === bookingsPageState.filters.staff;
    const statusMatches = !bookingsPageState.filters.status || booking.status === bookingsPageState.filters.status;
    const bookingDate = normalizeDate(booking.date);
    const fromMatches = !bookingsPageState.filters.from || bookingDate >= bookingsPageState.filters.from;
    const toMatches = !bookingsPageState.filters.to || bookingDate <= bookingsPageState.filters.to;

    return quickStatusMatches && searchMatches && staffMatches && statusMatches && fromMatches && toMatches;
}

function createBookingCard(booking) {
    const card = document.createElement("article");
    const canCancel = isBookingCancellable(booking);
    const phoneHref = formatPhoneHref(booking.staff_phone);
    const duration = Number(booking.service_duration || 0);
    const bookingDate = normalizeDate(booking.date);

    card.className = "booking-card";
    card.innerHTML = `
        <div class="booking-card-top">
            ${renderStaffAvatar(booking)}
            <div class="booking-main">
                <div class="booking-staff-line">
                    <p class="booking-staff">${escapeHtml(booking.staff || "Barbiere")}</p>
                </div>
                <div class="booking-service-line">
                    <h3 class="booking-service">${escapeHtml(booking.service || "Servizio")}</h3>
                </div>
                <time class="booking-datetime" datetime="${escapeHtml(`${bookingDate}T${formatTime(booking.time)}`)}">
                    <span><i class="bi bi-calendar3"></i>${escapeHtml(formatCardDate(bookingDate))}</span>
                    <span><i class="bi bi-clock"></i>${escapeHtml(formatTime(booking.time))}</span>
                </time>
                <div class="booking-meta">
                    ${duration > 0 ? `
                        <div class="booking-duration">
                            <i class="bi bi-hourglass-split"></i>
                            <span>${escapeHtml(formatDuration(duration))}</span>
                        </div>` : ""}
                    <span class="status-badge status-${escapeHtml(booking.status)}">
                        ${escapeHtml(bookingStatusLabels[booking.status] || booking.status)}
                    </span>
                </div>
            </div>
            <div class="card-actions">
                <a
                    class="card-action call-action ${phoneHref ? "" : "disabled"}"
                    href="${phoneHref || "#"}"
                    aria-label="Chiama ${escapeHtml(booking.staff || "il barbiere")}"
                    title="Chiama il barbiere">
                    <i class="bi bi-telephone-fill"></i>
                </a>
                ${canCancel ? `
                    <button
                        type="button"
                        class="card-action cancel-action"
                        aria-label="Annulla prenotazione"
                        title="Annulla prenotazione">
                        <i class="bi bi-x-lg"></i>
                    </button>` : ""}
            </div>
        </div>
        ${booking.note ? `<p class="booking-note"><strong>Nota:</strong> ${escapeHtml(booking.note)}</p>` : ""}`;

    const cancelButton = card.querySelector(".cancel-action");
    cancelButton?.addEventListener("click", () => cancelBooking(booking, cancelButton));

    return card;
}

function renderStaffAvatar(booking) {
    const name = booking.staff || "Barbiere";
    const initials = name.split(/\s+/).filter(Boolean).map((part) => part.charAt(0)).join("").slice(0, 2).toUpperCase();

    if (!booking.staff_image) {
        return `<div class="staff-avatar" aria-label="${escapeHtml(name)}">${escapeHtml(initials || "GC")}</div>`;
    }

    return `
        <div class="staff-avatar">
            <img src="${escapeHtml(booking.staff_image)}" alt="${escapeHtml(name)}">
        </div>`;
}

async function cancelBooking(booking, button) {
    const confirmed = await window.appConfirm(
        "La prenotazione verrà annullata e lo slot tornerà disponibile.",
        {
            title: "Annullare la prenotazione?",
            confirmText: "Sì, annulla",
            type: "danger",
        }
    );

    if (!confirmed) return;

    const token = localStorage.getItem("token");
    const originalContent = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    try {
        const response = await fetch(`${API_BASE}/bookings/${booking.id}/cancel`, {
            method: "POST",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json",
            },
        });
        const data = await response.json();

        if (!response.ok || data.status !== true) {
            throw new Error(data.message || "Impossibile annullare la prenotazione.");
        }

        booking.status = "cancelled";
        const card = button.closest(".booking-card");
        card?.classList.add("app-card-removing");
        window.setTimeout(renderBookings, card ? 190 : 0);
        window.appToast?.("La prenotazione è stata annullata.", "success");
    } catch (error) {
        button.disabled = false;
        button.innerHTML = originalContent;
        window.appToast(error.message || "Errore durante l'annullamento. Riprova.", "error");
    }
}

function isBookingCancellable(booking) {
    return ["pending", "confirmed"].includes(booking.status);
}

function formatDate(value) {
    const [year, month, day] = normalizeDate(value).split("-").map(Number);
    const date = new Date(year, month - 1, day);

    return date.toLocaleDateString("it-IT", {
        day: "numeric",
        month: "short",
    });
}

function formatCardDate(value) {
    const [year, month, day] = normalizeDate(value).split("-").map(Number);
    const date = new Date(year, month - 1, day);

    return date.toLocaleDateString("it-IT", {
        day: "numeric",
        month: "long",
    });
}

function normalizeDate(value) {
    return String(value || "").slice(0, 10);
}

function formatTime(value) {
    return String(value || "").slice(0, 5);
}

function formatDuration(minutes) {
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    return remainingMinutes ? `${hours} h ${remainingMinutes} min` : `${hours} h`;
}

function formatPhoneHref(phone) {
    if (!phone) return "";

    const cleanPhone = String(phone).replace(/[^\d+]/g, "");
    if (!cleanPhone) return "";

    return `tel:${cleanPhone.startsWith("+") ? cleanPhone : `+39${cleanPhone}`}`;
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
