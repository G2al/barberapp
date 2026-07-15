document.addEventListener("DOMContentLoaded", async () => {
    requireAuth();

    const serviceSelect = document.getElementById("serviceSelect");
    const barberSelect = document.getElementById("barberSelect");
    const dateSelect = document.getElementById("dateSelect");
    const monthSelect = document.getElementById("monthSelect");
    const timeSelect = document.getElementById("timeSelect");
    const dateStrip = document.getElementById("dateStrip");
    const timeGrid = document.getElementById("timeGrid");
    const serviceCardTitle = document.getElementById("serviceCardTitle");
    const serviceCardMeta = document.getElementById("serviceCardMeta");
    const barberCardTitle = document.getElementById("barberCardTitle");
    const barberCardMeta = document.getElementById("barberCardMeta");
    const barberAvatar = document.getElementById("barberAvatar");
    const barberAvatarImg = document.getElementById("barberAvatarImg");
    const heroBarberImg = document.getElementById("heroBarberImg");

    const dayLabels = ["DOM", "LUN", "MAR", "MER", "GIO", "VEN", "SAB"];

    const toIsoDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const toMonthValue = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;

    const isOpenWeekday = (date) => {
        const day = date.getDay();
        return day >= 2 && day <= 6;
    };

    const formatMonthLabel = (date) => date.toLocaleDateString("it-IT", {
        month: "long",
        year: "numeric",
    });

    const formatPrice = (price) => {
        const value = Number(price || 0);
        return `EUR ${value.toFixed(2)}`;
    };

    const updateServiceCard = () => {
        const option = serviceSelect.options[serviceSelect.selectedIndex];

        if (!serviceSelect.value || !option) {
            serviceCardTitle.textContent = "Scegli servizio";
            serviceCardMeta.textContent = "Durata e prezzo";
            return;
        }

        serviceCardTitle.textContent = option.dataset.name || option.textContent.trim();
        serviceCardMeta.textContent = `${option.dataset.duration || "-"} min  -  ${formatPrice(option.dataset.price)}`;
    };

    const updateBarberCard = () => {
        const option = barberSelect.options[barberSelect.selectedIndex];

        if (!barberSelect.value || !option) {
            barberCardTitle.textContent = "Scegli barbiere";
            barberCardMeta.textContent = "Giovanni Cerino Hair Stylist";
            barberAvatar.classList.remove("has-image");
            barberAvatarImg.removeAttribute("src");
            if (heroBarberImg) heroBarberImg.src = "/images/logo.jpg";
            return;
        }

        barberCardTitle.textContent = option.textContent.trim();
        barberCardMeta.textContent = "Senior Barber";

        if (option.dataset.image) {
            barberAvatarImg.src = option.dataset.image;
            if (heroBarberImg) heroBarberImg.src = option.dataset.image;
            barberAvatar.classList.add("has-image");
        } else {
            barberAvatar.classList.remove("has-image");
            barberAvatarImg.removeAttribute("src");
            if (heroBarberImg) heroBarberImg.src = "/images/logo.jpg";
        }
    };

    const renderMonthOptions = () => {
        if (!monthSelect) return;

        const start = new Date();
        start.setDate(1);
        monthSelect.innerHTML = "";

        for (let i = 0; i < 6; i++) {
            const monthDate = new Date(start.getFullYear(), start.getMonth() + i, 1);
            const option = document.createElement("option");
            option.value = toMonthValue(monthDate);
            option.textContent = formatMonthLabel(monthDate);
            monthSelect.appendChild(option);
        }
    };

    const renderDateStrip = () => {
        const selectedMonth = monthSelect?.value || toMonthValue(new Date());
        const [year, month] = selectedMonth.split("-").map(Number);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const start = new Date(year, month - 1, 1);
        const end = new Date(year, month, 0);
        dateStrip.innerHTML = "";
        const visibleDates = [];

        for (let day = 1; day <= end.getDate(); day++) {
            const date = new Date(year, month - 1, day);
            if (date < today || !isOpenWeekday(date)) continue;

            visibleDates.push(date);
            const value = toIsoDate(date);

            const button = document.createElement("button");
            button.type = "button";
            button.className = "date-pill";
            button.dataset.date = value;
            button.innerHTML = `<strong>${String(date.getDate()).padStart(2, "0")}</strong><span>${dayLabels[date.getDay()]}</span>`;
            button.addEventListener("click", async () => {
                dateSelect.value = value;
                highlightDate();
                await loadAvailableTimes();
            });

            dateStrip.appendChild(button);
        }

        if (!visibleDates.length) {
            dateStrip.innerHTML = `<p class="time-empty">Nessun giorno disponibile in questo mese.</p>`;
            dateSelect.value = "";
            resetTimes("Scegli un mese con giorni disponibili.");
            return;
        }

        const selectedStillVisible = visibleDates.some((date) => toIsoDate(date) === dateSelect.value);
        if (!selectedStillVisible) {
            dateSelect.value = toIsoDate(visibleDates[0]);
        }

        highlightDate();
    };

    const highlightDate = () => {
        document.querySelectorAll(".date-pill").forEach((button) => {
            button.classList.toggle("active", button.dataset.date === dateSelect.value);
        });
    };

    const renderTimeButtonsFromSelect = () => {
        timeGrid.innerHTML = "";

        const options = Array.from(timeSelect.options).filter((option) => option.value && !option.disabled);

        if (options.length === 0) {
            const message = timeSelect.options[0]?.textContent || "Nessun orario disponibile";
            timeGrid.innerHTML = `<p class="time-empty">${message}</p>`;
            return;
        }

        options.forEach((option) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "time-slot";
            button.textContent = option.value;
            button.dataset.time = option.value;
            button.addEventListener("click", () => {
                timeSelect.value = option.value;
                highlightTime();
            });

            timeGrid.appendChild(button);
        });

        highlightTime();
    };

    const highlightTime = () => {
        document.querySelectorAll(".time-slot").forEach((button) => {
            button.classList.toggle("active", button.dataset.time === timeSelect.value);
        });
    };

    const resetTimes = (message = "Scegli servizio, barbiere e data.") => {
        timeSelect.innerHTML = `<option value="">${message}</option>`;
        timeGrid.innerHTML = `<p class="time-empty">${message}</p>`;
    };

    const loadAvailableTimes = async () => {
        const date = dateSelect.value;
        const barberId = barberSelect.value;
        const serviceId = serviceSelect.value;

        resetTimes("Caricamento orari...");

        if (!date || !barberId || !serviceId) {
            resetTimes("Scegli servizio, barbiere e data.");
            return;
        }

        const response = await apiGet(`/availability/${barberId}?date=${date}&serviceId=${serviceId}`);

        if (response.closed_slots && response.closed_slots.length > 0) {
            const closedDay = response.closed_slots.find(cs => cs.time === null);
            if (closedDay) {
                resetTimes(`Giorno chiuso: ${closedDay.reason || ""}`.trim());
                return;
            }
        }

        if (!response.slots || response.slots.length === 0) {
            resetTimes("Nessun orario disponibile");
            return;
        }

        timeSelect.innerHTML = `<option value="">Seleziona ora</option>`;

        response.slots.forEach(slot => {
            const closedSlot = response.closed_slots?.find(cs => cs.time && cs.time.substring(0, 5) === slot);
            const label = closedSlot ? `${slot} - chiuso (${closedSlot.reason || ""})` : slot;
            timeSelect.innerHTML += `<option value="${slot}" ${closedSlot ? "disabled" : ""}>${label}</option>`;
        });

        renderTimeButtonsFromSelect();
    };

    const today = toIsoDate(new Date());
    dateSelect.min = today;
    renderMonthOptions();
    if (monthSelect) monthSelect.value = toMonthValue(new Date());
    renderDateStrip();
    highlightDate();
    resetTimes();

    const services = await apiGet("/services");

    serviceSelect.innerHTML = `<option value="">Seleziona servizio</option>`;

    services.forEach(s => {
        const option = document.createElement("option");
        option.value = s.id;
        option.textContent = `${s.name} - ${formatPrice(s.price)} (${s.duration} min)`;
        option.dataset.name = s.name;
        option.dataset.price = s.price;
        option.dataset.duration = s.duration;
        serviceSelect.appendChild(option);
    });

    updateServiceCard();
    updateBarberCard();

    serviceSelect.addEventListener("change", async () => {
        const serviceId = serviceSelect.value;

        updateServiceCard();
        barberSelect.innerHTML = `<option value="">Caricamento...</option>`;
        updateBarberCard();
        resetTimes();

        if (!serviceId) {
            barberSelect.innerHTML = `<option value="">Seleziona un servizio prima</option>`;
            updateBarberCard();
            return;
        }

        const staff = await apiGet(`/staff/by-service/${serviceId}`);

        if (staff.length === 0) {
            barberSelect.innerHTML = `<option value="">Nessun barbiere disponibile</option>`;
            updateBarberCard();
            return;
        }

        barberSelect.innerHTML = `<option value="">Seleziona barbiere</option>`;

        staff.forEach(b => {
            const option = document.createElement("option");
            option.value = b.id;
            option.textContent = `${b.first_name} ${b.last_name}`;
            if (b.image_url) option.dataset.image = b.image_url;
            barberSelect.appendChild(option);
        });

        updateBarberCard();
    });

    barberSelect.addEventListener("change", async () => {
        updateBarberCard();
        await loadAvailableTimes();
    });

    dateSelect.addEventListener("change", async () => {
        highlightDate();
        await loadAvailableTimes();
    });

    monthSelect?.addEventListener("change", async () => {
        renderDateStrip();
        await loadAvailableTimes();
    });

    timeSelect.addEventListener("change", highlightTime);
});

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
        haircut_id: null,
    };

    const response = await apiPost("/bookings", bookingData);

    setButtonLoading(confirmBtn, false);

    if (response.status === false) {
        showAlert(response.message || "Errore durante la prenotazione", "danger");
        return;
    }

    if (response.status === true) {
        showAlert("Prenotazione confermata. Reindirizzamento a Prenotazioni...", "success");

        setTimeout(() => {
            window.location.href = "/my-bookings.html";
        }, 1500);

        return;
    }

    showAlert("Errore sconosciuto", "danger");
}
