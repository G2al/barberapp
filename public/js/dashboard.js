document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    const serviceSelect = document.getElementById("serviceSelect");
    const barberSelect = document.getElementById("barberSelect");
    const dateSelect = document.getElementById("dateSelect");
    const timeSelect = document.getElementById("timeSelect");
    const today = new Date().toISOString().split("T")[0];

    dateSelect.min = today;
    dateSelect.value = today;

    try {
        const services = await apiGet("/services");
        serviceSelect.innerHTML = `<option value="">Seleziona servizio</option>`;
        services.forEach((service) => {
            serviceSelect.innerHTML += `<option value="${service.id}">${service.name} - EUR ${service.price} (${service.duration} min)</option>`;
        });
        if (!services.length) serviceSelect.innerHTML = `<option value="">Nessun servizio disponibile</option>`;
    } catch (error) {
        serviceSelect.innerHTML = `<option value="">Impossibile caricare i servizi</option>`;
        showAlert("Impossibile caricare i servizi disponibili", "danger");
    }

    serviceSelect.addEventListener("change", async () => {
        const serviceId = serviceSelect.value;
        barberSelect.innerHTML = `<option value="">Caricamento barbieri...</option>`;
        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;
        hideBarberAvatar();

        if (!serviceId) {
            barberSelect.innerHTML = `<option value="">Seleziona prima il servizio</option>`;
            return;
        }

        try {
            const staff = await apiGet(`/staff/by-service/${encodeURIComponent(serviceId)}`);
            if (!staff.length) {
                barberSelect.innerHTML = `<option value="">Nessun barbiere disponibile</option>`;
                return;
            }

            barberSelect.innerHTML = `<option value="">Seleziona barbiere</option>`;
            staff.forEach((barber) => {
                const option = document.createElement("option");
                option.value = barber.id;
                option.textContent = `${barber.first_name} ${barber.last_name}`;
                if (barber.image_url) option.dataset.image = barber.image_url;
                barberSelect.appendChild(option);
            });
        } catch (error) {
            barberSelect.innerHTML = `<option value="">Impossibile caricare i barbieri</option>`;
            showAlert("Impossibile caricare i professionisti", "danger");
        }
    });

    barberSelect.addEventListener("change", () => {
        const selectedOption = barberSelect.options[barberSelect.selectedIndex];
        const avatarWrap = document.getElementById("barberAvatar");
        const avatarImg = document.getElementById("barberAvatarImg");

        if (selectedOption?.dataset.image && avatarWrap && avatarImg) {
            avatarImg.src = selectedOption.dataset.image;
            avatarWrap.style.display = "block";
        } else {
            hideBarberAvatar();
        }

        void loadAvailability();
    });

    dateSelect.addEventListener("change", () => void loadAvailability());

    function hideBarberAvatar() {
        const avatarWrap = document.getElementById("barberAvatar");
        if (avatarWrap) avatarWrap.style.display = "none";
    }

    async function loadAvailability() {
        const date = dateSelect.value;
        const barberId = barberSelect.value;
        const serviceId = serviceSelect.value;
        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        if (!date || !barberId || !serviceId) return;

        try {
            const response = await apiGet(`/availability/${barberId}?date=${encodeURIComponent(date)}&serviceId=${encodeURIComponent(serviceId)}`);
            const closedDay = response.closed_slots?.find((slot) => slot.time === null);

            if (closedDay) {
                timeSelect.innerHTML = `<option value="">Giorno chiuso: ${closedDay.reason}</option>`;
                return;
            }
            if (!response.slots?.length) {
                timeSelect.innerHTML = `<option value="">Nessun orario disponibile</option>`;
                return;
            }
            response.slots.forEach((slot) => {
                timeSelect.innerHTML += `<option value="${slot}">${slot}</option>`;
            });
        } catch (error) {
            timeSelect.innerHTML = `<option value="">Impossibile caricare gli orari</option>`;
            showAlert("Impossibile caricare gli orari disponibili", "danger");
        }
    }
});

async function confirmBooking() {
    const confirmBtn = document.getElementById("confirmBtn");
    const serviceId = document.getElementById("serviceSelect").value;
    const barberId = document.getElementById("barberSelect").value;
    const date = document.getElementById("dateSelect").value;
    const time = document.getElementById("timeSelect").value;

    if (!serviceId || !barberId || !date || !time) {
        showAlert("Compila tutti i campi", "danger");
        return;
    }

    setButtonLoading(confirmBtn, true);
    try {
        const response = await apiPost("/bookings", {
            staff_id: Number.parseInt(barberId, 10),
            service_id: Number.parseInt(serviceId, 10),
            date,
            time,
            haircut_id: null,
        });

        if (response.status === false) {
            showAlert(response.message || "Errore durante la prenotazione", "danger");
            return;
        }

        showAlert("Prenotazione confermata. Reindirizzamento alle prenotazioni...", "success");
        setTimeout(() => { window.location.href = "/my-bookings.html"; }, 1500);
    } catch (error) {
        showAlert("Non è stato possibile completare la prenotazione", "danger");
    } finally {
        setButtonLoading(confirmBtn, false);
    }
}
