document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    const token = localStorage.getItem("token");
    const bookingsList = document.getElementById("bookingsList");
    const loadingMsg = document.getElementById("loadingMsg");

    if (!token) {
        window.location.href = "index.html";
        return;
    }

    // Traduzioni stato
    const statusTranslations = {
        pending: "In sospeso",
        confirmed: "Confermata",
        cancelled: "Annullata",
    };

    // Colori per stato
    const badgeColors = {
        pending: "bg-warning text-dark",
        confirmed: "bg-success text-white",
        cancelled: "bg-danger text-white",
    };

    // Formatta la data in italiano
    const formatDate = (dateString) => {
        try {
            const d = new Date(dateString);
            return d.toLocaleDateString("it-IT", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            });
        } catch {
            return dateString;
        }
    };

    try {
        const response = await fetch(`${API_BASE}/bookings`, {
            method: "GET",
            headers: {
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json",
            },
        });

        const data = await response.json();
        loadingMsg.style.display = "none";

        if (!data.status || !data.bookings.length) {
            bookingsList.innerHTML = `
                <div class="text-center text-muted mt-5">
                    <i class="bi bi-calendar-x display-4 d-block mb-2"></i>
                    <p>Nessuna prenotazione trovata.</p>
                </div>`;
            return;
        }

        data.bookings.forEach((booking) => {
            const translatedStatus = statusTranslations[booking.status] || booking.status;
            const badgeClass = badgeColors[booking.status] || "bg-secondary";
            const formattedDate = formatDate(booking.date);

            const item = document.createElement("div");
            item.className = "list-item mb-3 p-3 bg-white rounded-3 shadow-sm";

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold text-primary-color">${booking.service}</div>
                        <div class="small text-muted">${booking.staff}</div>
                    </div>
                    <span class="badge ${badgeClass}">${translatedStatus}</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-calendar3 me-2 text-muted"></i>
                        <span>${formattedDate}</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock me-2 text-muted"></i>
                        <span>${booking.time}</span>
                    </div>
                </div>
            `;

            bookingsList.appendChild(item);
        });
    } catch (error) {
        console.error("Errore nel caricamento:", error);
        bookingsList.innerHTML = `
            <div class="text-center text-danger mt-5">
                <i class="bi bi-exclamation-triangle display-4 d-block mb-2"></i>
                <p>Errore durante il caricamento delle prenotazioni.</p>
            </div>`;
    }
});
