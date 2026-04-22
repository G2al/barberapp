document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    const serviceSelect = document.getElementById("serviceSelect");
    const barberSelect = document.getElementById("barberSelect");
    const dateSelect = document.getElementById("dateSelect");
    const timeSelect = document.getElementById("timeSelect");

    // ===========================
    // 0️⃣ IMPOSTA DATA MINIMA (OGGI)
    // ===========================
    const today = new Date().toISOString().split('T')[0];
    dateSelect.min = today;
    dateSelect.value = today; // Imposta oggi come default

    // ===========================
    // 1️⃣ CARICA SERVIZI
    // ===========================
    const services = await apiGet("/services");

    serviceSelect.innerHTML = `<option value="">Seleziona servizio</option>`;

    services.forEach(s => {
        serviceSelect.innerHTML += `
            <option value="${s.id}">
                ${s.name} — €${s.price} (${s.duration} min)
            </option>
        `;
    });

    // ===========================
    // 2️⃣ QUANDO CAMBIO SERVIZIO → CARICA STAFF
    // ===========================
    serviceSelect.addEventListener("change", async () => {
        const serviceId = serviceSelect.value;

        barberSelect.innerHTML = `<option value="">Caricamento...</option>`;
        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;
        // reset avatar
        const avatarWrap = document.getElementById("barberAvatar");
        if (avatarWrap) avatarWrap.style.display = "none";

        if (!serviceId) {
            barberSelect.innerHTML = `<option value="">Seleziona un servizio prima</option>`;
            return;
        }

        const staff = await apiGet(`/staff/by-service/${serviceId}`);

        if (staff.length === 0) {
            barberSelect.innerHTML = `<option value="">Nessun barbiere disponibile</option>`;
            return;
        }

        barberSelect.innerHTML = `<option value="">Seleziona barbiere</option>`;

        staff.forEach(b => {
            const image = b.image_url ? b.image_url : '';
            const option = document.createElement('option');
            option.value = b.id;
            option.textContent = `${b.first_name} ${b.last_name}`;
            if (image) option.dataset.image = image;
            barberSelect.appendChild(option);
        });
    });

    // ===========================
    // 3️⃣ QUANDO CAMBIO DATA → CARICA ORARI
    // ===========================
    dateSelect.addEventListener("change", async () => {
        const date = dateSelect.value;
        const barberId = barberSelect.value;
        const serviceId = serviceSelect.value;

        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        if (!date || !barberId || !serviceId) {
            return;
        }

        const response = await apiGet(`/availability/${barberId}?date=${date}&serviceId=${serviceId}`);

        // 🆕 Controlla se il giorno intero è chiuso
        if (response.closed_slots && response.closed_slots.length > 0) {
            const closedDay = response.closed_slots.find(cs => cs.time === null);
            if (closedDay) {
                timeSelect.innerHTML = `<option value="">📅 Giorno chiuso: ${closedDay.reason}</option>`;
                return;
            }
        }

        if (!response.slots || response.slots.length === 0) {
            timeSelect.innerHTML = `<option value="">Nessun orario disponibile</option>`;
            return;
        }

        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        response.slots.forEach(slot => {
            // 🆕 Controlla se questo orario specifico è chiuso
            const closedSlot = response.closed_slots?.find(cs => cs.time && cs.time.substring(0, 5) === slot);
            const label = closedSlot ? `${slot} ⛔ (${closedSlot.reason})` : slot;
            timeSelect.innerHTML += `<option value="${slot}" ${closedSlot ? 'disabled' : ''}>${label}</option>`;
        });
    });

    // ===========================
    // 4️⃣ QUANDO CAMBIO BARBIERE → RICARICARE ORARI
    // ===========================
    barberSelect.addEventListener("change", async () => {
        const date = dateSelect.value;
        const barberId = barberSelect.value;
        const serviceId = serviceSelect.value;

        const selectedOption = barberSelect.options[barberSelect.selectedIndex];
        const avatarWrap = document.getElementById("barberAvatar");
        const avatarImg = document.getElementById("barberAvatarImg");
        if (avatarWrap && avatarImg && selectedOption && selectedOption.dataset.image) {
            avatarImg.src = selectedOption.dataset.image;
            avatarWrap.style.display = 'block';
        } else if (avatarWrap) {
            avatarWrap.style.display = 'none';
        }

        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        if (!date || !barberId || !serviceId) {
            return;
        }

        const response = await apiGet(`/availability/${barberId}?date=${date}&serviceId=${serviceId}`);

        // 🆕 Controlla se il giorno intero è chiuso
        if (response.closed_slots && response.closed_slots.length > 0) {
            const closedDay = response.closed_slots.find(cs => cs.time === null);
            if (closedDay) {
                timeSelect.innerHTML = `<option value="">📅 Giorno chiuso: ${closedDay.reason}</option>`;
                return;
            }
        }

        if (!response.slots || response.slots.length === 0) {
            timeSelect.innerHTML = `<option value="">Nessun orario disponibile</option>`;
            return;
        }

        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        response.slots.forEach(slot => {
            // 🆕 Controlla se questo orario specifico è chiuso
            const closedSlot = response.closed_slots?.find(cs => cs.time && cs.time.substring(0, 5) === slot);
            const label = closedSlot ? `${slot} ⛔ (${closedSlot.reason})` : slot;
            timeSelect.innerHTML += `<option value="${slot}" ${closedSlot ? 'disabled' : ''}>${label}</option>`;
        });
    });
});

// ===========================
// 5️⃣ CONFERMA PRENOTAZIONE
// ===========================
async function confirmBooking() {
    const confirmBtn = document.getElementById("confirmBtn");
    const serviceSelect = document.getElementById("serviceSelect");
    const barberSelect = document.getElementById("barberSelect");
    const dateSelect = document.getElementById("dateSelect");
    const timeSelect = document.getElementById("timeSelect");

    const serviceId = serviceSelect.value;
    const barberId = barberSelect.value;
    const date = dateSelect.value;
    const time = timeSelect.value;

    if (!serviceId || !barberId || !date || !time) {
        showAlert("Compila tutti i campi", "danger");
        return;
    }

    setButtonLoading(confirmBtn, true);

    const bookingData = {
        staff_id: parseInt(barberId),
        service_id: parseInt(serviceId),
        date: date,
        time: time,
        haircut_id: null
    };

    const response = await apiPost("/bookings", bookingData);

    setButtonLoading(confirmBtn, false);

    if (response.status === false) {
        showAlert(response.message || "Errore durante la prenotazione", "danger");
        return;
    }

    if (response.status === true) {
        showAlert("Prenotazione confermata! ✅ Reindirizzamento a Le mie prenotazioni...", "success");

        setTimeout(() => {
            // Redirect a My Bookings dopo 1.5 secondi
            window.location.href = "/my-bookings.html";
        }, 1500);

        return;
    }

    showAlert("Errore sconosciuto", "danger");
}
