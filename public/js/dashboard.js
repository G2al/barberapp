(function () {
let lifecycleController = null;

const bookingPageState = {
    staff: [],
    services: [],
    selectedStaff: null,
    selectedService: null,
    selectedDate: "",
    selectedTime: "",
    calendarWeekStart: startOfWeek(new Date()),
    availabilityRequest: 0,
};

async function mountDashboard() {
    lifecycleController?.abort();
    lifecycleController = new AbortController();
    const { signal } = lifecycleController;

    Object.assign(bookingPageState, {
        staff: [],
        services: [],
        selectedStaff: null,
        selectedService: null,
        selectedDate: "",
        selectedTime: "",
        calendarWeekStart: startOfWeek(new Date()),
        availabilityRequest: bookingPageState.availabilityRequest + 1,
    });

    requireAuth();

    const currentUser = getUser();
    hydrateUserHeader(currentUser);
    setupHeaderPanels(signal);
    setupCalendar(signal);
    setupServiceSelect(signal);
    setupBookingConfirmation(signal);

    if (currentUser?.role === "admin") {
        document.getElementById("adminNoteStep")?.classList.remove("d-none");
    }

    selectDate(toIsoDate(new Date()), false);
    updateSummary();

    await Promise.all([
        loadAppConfiguration(),
        loadStaff(),
    ]);
}

function hydrateUserHeader(user) {
    const firstName = user?.name?.trim() || "cliente";
    document.getElementById("welcomeName").textContent = firstName;
}

function setupHeaderPanels(signal) {
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
    }, { signal });

    logoutButton.addEventListener("click", logout, { signal });

    notificationPanel.addEventListener("click", (event) => event.stopPropagation(), { signal });
    document.addEventListener("click", closePanels, { signal });
    window.appPushNotifications?.mount({ panel: notificationPanel });
}

async function loadAppConfiguration() {
    const locationElement = document.getElementById("shopLocation");

    try {
        const config = await apiGet("/app-config");
        locationElement.textContent = config.location || "Via Toledo 156, Napoli";
    } catch (error) {
        locationElement.textContent = "Via Toledo 156, Napoli";
    }
}

async function loadStaff() {
    const staffGrid = document.getElementById("staffGrid");
    const status = document.getElementById("staffStepStatus");

    try {
        const staff = await apiGet("/staff");
        bookingPageState.staff = Array.isArray(staff) ? staff : [];
        renderStaff();

        status.textContent = bookingPageState.staff.length
            ? `${bookingPageState.staff.length} disponibili`
            : "Nessuno disponibile";
    } catch (error) {
        staffGrid.innerHTML = '<p class="empty-line">Impossibile caricare lo staff. Riprova tra poco.</p>';
        status.textContent = "Errore";
    }
}

function renderStaff() {
    const staffGrid = document.getElementById("staffGrid");
    const barberSelect = document.getElementById("barberSelect");

    staffGrid.innerHTML = "";
    barberSelect.innerHTML = '<option value="">Seleziona barbiere</option>';

    if (!bookingPageState.staff.length) {
        staffGrid.innerHTML = '<p class="empty-line">Nessun barbiere disponibile al momento.</p>';
        return;
    }

    bookingPageState.staff.forEach((staffMember) => {
        const fullName = [staffMember.first_name, staffMember.last_name].filter(Boolean).join(" ");
        const option = document.createElement("option");
        option.value = staffMember.id;
        option.textContent = fullName;
        barberSelect.appendChild(option);

        const button = document.createElement("button");
        button.type = "button";
        button.className = "staff-option";
        button.dataset.staffId = String(staffMember.id);
        button.setAttribute("aria-pressed", "false");
        button.setAttribute("aria-label", `Seleziona ${fullName}`);

        const avatar = document.createElement("span");
        avatar.className = "staff-avatar";
        const avatarWrap = document.createElement("span");
        avatarWrap.className = "staff-avatar-wrap";
        const initials = getInitials(staffMember);

        if (staffMember.image_url) {
            const image = document.createElement("img");
            image.src = staffMember.image_url;
            image.alt = fullName;
            image.addEventListener("error", () => {
                image.remove();
                avatar.textContent = initials;
            }, { once: true });
            avatar.appendChild(image);
        } else {
            avatar.textContent = initials;
        }

        const selectedCheck = document.createElement("span");
        selectedCheck.className = "staff-selected-check";
        selectedCheck.setAttribute("aria-hidden", "true");
        selectedCheck.innerHTML = '<i class="bi bi-check-lg"></i>';
        avatarWrap.append(avatar, selectedCheck);

        const name = document.createElement("span");
        name.className = "staff-name";
        name.textContent = staffMember.first_name || fullName;

        button.append(avatarWrap, name);
        button.addEventListener("click", () => selectStaff(staffMember.id));
        staffGrid.appendChild(button);
    });
}

function getInitials(staffMember) {
    return [staffMember.first_name, staffMember.last_name]
        .filter(Boolean)
        .map((value) => value.charAt(0).toUpperCase())
        .join("")
        .slice(0, 2) || "GC";
}

async function selectStaff(staffId) {
    const numericId = Number(staffId);
    const selectedStaff = bookingPageState.staff.find((staffMember) => Number(staffMember.id) === numericId);

    if (!selectedStaff) return;

    bookingPageState.selectedStaff = selectedStaff;
    bookingPageState.selectedService = null;
    bookingPageState.selectedTime = "";

    document.getElementById("barberSelect").value = String(selectedStaff.id);
    document.querySelectorAll(".staff-option").forEach((button) => {
        const isActive = Number(button.dataset.staffId) === numericId;
        button.classList.toggle("active", isActive);
        button.setAttribute("aria-pressed", String(isActive));
    });

    document.getElementById("staffStepStatus").textContent = selectedStaff.first_name;
    resetServices("Caricamento servizi...");
    resetTimes("Seleziona un servizio per vedere gli orari.");
    updateSummary();

    await loadServicesForStaff(selectedStaff.id);
}

function resetServices(message) {
    const serviceSelect = document.getElementById("serviceSelect");
    bookingPageState.services = [];
    serviceSelect.disabled = true;
    serviceSelect.innerHTML = `<option value="">${message}</option>`;
    document.getElementById("serviceMeta").textContent = "Durata e prezzo appariranno qui.";
    document.getElementById("serviceStepStatus").textContent = message;
    renderCustomServiceSelect();
}

async function loadServicesForStaff(staffId) {
    const serviceSelect = document.getElementById("serviceSelect");
    const status = document.getElementById("serviceStepStatus");

    try {
        const services = await apiGet(`/services/by-staff/${staffId}`);

        if (Number(bookingPageState.selectedStaff?.id) !== Number(staffId)) return;

        bookingPageState.services = Array.isArray(services) ? services : [];
        serviceSelect.innerHTML = '<option value="">Seleziona servizio</option>';

        bookingPageState.services.forEach((service) => {
            const option = document.createElement("option");
            option.value = service.id;
            option.textContent = service.name;
            serviceSelect.appendChild(option);
        });

        serviceSelect.disabled = bookingPageState.services.length === 0;
        renderCustomServiceSelect();
        status.textContent = bookingPageState.services.length
            ? `${bookingPageState.services.length} servizi`
            : "Nessun servizio";

        if (!bookingPageState.services.length) {
            serviceSelect.innerHTML = '<option value="">Nessun servizio disponibile</option>';
            renderCustomServiceSelect();
        }
    } catch (error) {
        bookingPageState.services = [];
        serviceSelect.disabled = true;
        serviceSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
        status.textContent = "Errore";
        renderCustomServiceSelect();
    }
}

function setupServiceSelect(signal) {
    const serviceSelect = document.getElementById("serviceSelect");
    const shell = document.getElementById("serviceSelectShell");
    const trigger = document.getElementById("serviceSelectTrigger");
    const menu = document.getElementById("serviceSelectMenu");

    trigger.addEventListener("click", (event) => {
        event.stopPropagation();
        if (trigger.disabled) return;
        setServiceMenuOpen(!shell.classList.contains("open"));
    }, { signal });

    trigger.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown" && !trigger.disabled) {
            event.preventDefault();
            setServiceMenuOpen(true);
            menu.querySelector(".service-select-option")?.focus();
        }

        if (event.key === "Escape") {
            setServiceMenuOpen(false);
        }
    }, { signal });

    menu.addEventListener("click", (event) => event.stopPropagation(), { signal });
    document.addEventListener("click", () => setServiceMenuOpen(false), { signal });

    serviceSelect.addEventListener("change", async (event) => {
        const serviceId = Number(event.target.value);
        bookingPageState.selectedService = bookingPageState.services.find((service) => Number(service.id) === serviceId) || null;
        bookingPageState.selectedTime = "";
        syncCustomServiceSelection();
        setServiceMenuOpen(false);

        if (bookingPageState.selectedService) {
            const service = bookingPageState.selectedService;
            document.getElementById("serviceMeta").textContent = `${service.duration} minuti · ${formatPrice(service.price)}`;
            document.getElementById("serviceStepStatus").textContent = `${service.duration} min`;
        } else {
            document.getElementById("serviceMeta").textContent = "Durata e prezzo appariranno qui.";
            document.getElementById("serviceStepStatus").textContent = `${bookingPageState.services.length} servizi`;
        }

        resetTimes("Caricamento orari...");
        updateSummary();
        await loadAvailableTimes();
    }, { signal });

    renderCustomServiceSelect();
}

function setupBookingConfirmation(signal) {
    document.getElementById("confirmBtn").addEventListener("click", confirmBooking, { signal });
}

function renderCustomServiceSelect() {
    const serviceSelect = document.getElementById("serviceSelect");
    const trigger = document.getElementById("serviceSelectTrigger");
    const menu = document.getElementById("serviceSelectMenu");

    trigger.disabled = serviceSelect.disabled;
    menu.innerHTML = "";

    bookingPageState.services.forEach((service) => {
        const option = document.createElement("button");
        option.type = "button";
        option.className = "service-select-option";
        option.dataset.serviceId = String(service.id);
        option.setAttribute("role", "option");

        const name = document.createElement("span");
        name.className = "service-option-name";
        name.textContent = service.name;

        const meta = document.createElement("span");
        meta.className = "service-option-meta";
        meta.textContent = `${service.duration} min - ${formatPrice(service.price)}`;

        option.append(name, meta);
        option.addEventListener("click", () => {
            serviceSelect.value = String(service.id);
            serviceSelect.dispatchEvent(new Event("change", { bubbles: true }));
        });
        menu.appendChild(option);
    });

    syncCustomServiceSelection();
}

function syncCustomServiceSelection() {
    const serviceSelect = document.getElementById("serviceSelect");
    const label = document.getElementById("serviceSelectLabel");
    const selectedService = bookingPageState.selectedService;

    label.textContent = selectedService
        ? selectedService.name
        : serviceSelect.options[0]?.textContent || "Seleziona servizio";

    document.querySelectorAll(".service-select-option").forEach((option) => {
        const isActive = Number(option.dataset.serviceId) === Number(selectedService?.id);
        option.classList.toggle("active", isActive);
        option.setAttribute("aria-selected", String(isActive));
    });
}

function setServiceMenuOpen(isOpen) {
    const shell = document.getElementById("serviceSelectShell");
    const trigger = document.getElementById("serviceSelectTrigger");

    if (trigger.disabled) isOpen = false;

    shell.classList.toggle("open", isOpen);
    trigger.setAttribute("aria-expanded", String(isOpen));
}

function setupCalendar(signal) {
    document.getElementById("previousMonth").addEventListener("click", () => changeCalendarWeek(-1), { signal });
    document.getElementById("nextMonth").addEventListener("click", () => changeCalendarWeek(1), { signal });
    window.addEventListener("resize", () => syncCalendarSelection(), { passive: true, signal });
    renderCalendar();
}

function changeCalendarWeek(offset) {
    const nextWeek = new Date(bookingPageState.calendarWeekStart);
    nextWeek.setDate(nextWeek.getDate() + (offset * 7));

    const firstWeek = startOfWeek(new Date());
    const maxDate = new Date(new Date().getFullYear(), new Date().getMonth() + 6, 0);

    if (nextWeek < firstWeek || nextWeek > maxDate) return;

    bookingPageState.calendarWeekStart = nextWeek;
    renderCalendar(offset);
}

function renderCalendar(direction = 0, previousDate = "") {
    const grid = document.getElementById("calendarGrid");
    const monthLabel = document.getElementById("calendarMonthLabel");
    const weekStart = bookingPageState.calendarWeekStart;
    const today = startOfDay(new Date());
    const maxDate = new Date(today.getFullYear(), today.getMonth() + 6, 0);
    const labelDate = new Date(weekStart);
    labelDate.setDate(labelDate.getDate() + 3);
    const weekdayLabels = ["L", "M", "M", "G", "V", "S", "D"];

    monthLabel.textContent = labelDate.toLocaleDateString("it-IT", {
        month: "long",
        year: "numeric",
    });

    grid.innerHTML = "";
    grid.classList.remove("has-selection-indicator", "week-forward", "week-back");

    const selection = document.createElement("span");
    selection.className = "calendar-selection";
    selection.setAttribute("aria-hidden", "true");
    grid.appendChild(selection);

    for (let index = 0; index < 7; index++) {
        const date = new Date(weekStart);
        date.setDate(weekStart.getDate() + index);
        const isoDate = toIsoDate(date);
        const button = document.createElement("button");
        button.type = "button";
        button.className = "calendar-day";
        const isPast = startOfDay(date) < today;
        const isBeyondRange = date > maxDate;

        const weekday = document.createElement("span");
        weekday.className = "weekday";
        weekday.textContent = weekdayLabels[index];

        const dayNumber = document.createElement("strong");
        dayNumber.className = "day-number";
        dayNumber.textContent = String(date.getDate());

        button.append(weekday, dayNumber);
        button.dataset.date = isoDate;
        button.disabled = isPast || isBeyondRange;
        button.classList.toggle("today", isoDate === toIsoDate(today));
        button.classList.toggle("active", isoDate === bookingPageState.selectedDate);
        button.setAttribute("aria-label", formatLongDate(date));
        button.addEventListener("click", () => selectDate(isoDate));
        grid.appendChild(button);
    }

    if (direction !== 0) {
        grid.classList.add(direction > 0 ? "week-forward" : "week-back");
    }
    syncCalendarSelection(previousDate);

    const firstAllowedWeek = startOfWeek(today);
    const nextWeekStart = new Date(weekStart);
    nextWeekStart.setDate(nextWeekStart.getDate() + 7);
    document.getElementById("previousMonth").disabled = weekStart <= firstAllowedWeek;
    document.getElementById("nextMonth").disabled = nextWeekStart > maxDate;
}

async function selectDate(isoDate, loadTimes = true) {
    const previousDate = bookingPageState.selectedDate;
    bookingPageState.selectedDate = isoDate;
    bookingPageState.selectedTime = "";
    document.getElementById("dateSelect").value = isoDate;

    const date = parseIsoDate(isoDate);
    const formattedDate = formatLongDate(date);
    document.getElementById("selectedDateLabel").textContent = formattedDate;

    const visibleWeekEnd = new Date(bookingPageState.calendarWeekStart);
    visibleWeekEnd.setDate(visibleWeekEnd.getDate() + 6);

    if (date < bookingPageState.calendarWeekStart || date > visibleWeekEnd) {
        bookingPageState.calendarWeekStart = startOfWeek(date);
    }

    renderCalendar(0, previousDate);
    updateSummary();

    if (loadTimes) {
        resetTimes("Caricamento orari...");
        await loadAvailableTimes();
    } else {
        resetTimes("Scegli barbiere, servizio e data per vedere gli orari.");
    }
}

async function loadAvailableTimes() {
    const staffId = bookingPageState.selectedStaff?.id;
    const serviceId = bookingPageState.selectedService?.id;
    const date = bookingPageState.selectedDate;

    if (!staffId || !serviceId || !date) {
        resetTimes("Seleziona barbiere, servizio e data per vedere gli orari disponibili.");
        return;
    }

    const requestId = ++bookingPageState.availabilityRequest;
    resetTimes("Caricamento orari disponibili...");
    document.getElementById("timeStepStatus").textContent = "Caricamento";

    try {
        const response = await apiGet(`/availability/${staffId}?date=${encodeURIComponent(date)}&serviceId=${serviceId}`);

        if (requestId !== bookingPageState.availabilityRequest) return;

        const fullDayClosure = response.closed_slots?.find((closedSlot) => closedSlot.time === null);
        if (fullDayClosure) {
            const reason = fullDayClosure.reason ? `: ${fullDayClosure.reason}` : "";
            resetTimes(`Giorno non disponibile${reason}`);
            return;
        }

        const slots = Array.isArray(response.slots) ? response.slots : [];
        renderTimeSlots(slots);
    } catch (error) {
        if (requestId !== bookingPageState.availabilityRequest) return;
        resetTimes("Impossibile caricare gli orari. Riprova.");
    }
}

function syncCalendarSelection(previousDate = "") {
    const grid = document.getElementById("calendarGrid");
    const selection = grid?.querySelector(".calendar-selection");
    const activeDay = grid?.querySelector(".calendar-day.active");

    if (!grid || !selection || !activeDay) {
        grid?.classList.remove("has-selection-indicator");
        return;
    }

    const previousDay = previousDate
        ? Array.from(grid.querySelectorAll(".calendar-day")).find((day) => day.dataset.date === previousDate)
        : null;
    const setPosition = (day) => {
        selection.style.width = `${day.offsetWidth}px`;
        selection.style.height = `${day.offsetHeight}px`;
        selection.style.setProperty("--calendar-x", `${day.offsetLeft}px`);
        selection.style.setProperty("--calendar-y", `${day.offsetTop}px`);
    };

    grid.classList.add("has-selection-indicator");
    setPosition(previousDay || activeDay);
    selection.classList.add("visible");

    window.requestAnimationFrame(() => {
        selection.classList.add("animate");
        setPosition(activeDay);
    });
}

function renderTimeSlots(slots) {
    const timeGrid = document.getElementById("timeGrid");
    const timeSelect = document.getElementById("timeSelect");
    const status = document.getElementById("timeStepStatus");

    timeGrid.innerHTML = "";
    timeSelect.innerHTML = '<option value="">Seleziona orario</option>';
    bookingPageState.selectedTime = "";

    if (!slots.length) {
        timeGrid.innerHTML = '<p class="time-empty">Nessun orario disponibile per questa data. Prova un altro giorno.</p>';
        status.textContent = "Nessun orario";
        updateSummary();
        return;
    }

    const periods = [
        {
            label: "Mattina",
            slots: slots.filter((slot) => Number(slot.split(":")[0]) < 13),
        },
        {
            label: "Pomeriggio",
            slots: slots.filter((slot) => Number(slot.split(":")[0]) >= 13),
        },
    ];

    periods.filter((period) => period.slots.length).forEach((period) => {
        const section = document.createElement("section");
        section.className = "time-period";

        const title = document.createElement("h4");
        title.className = "time-period-title";
        title.textContent = period.label;

        const grid = document.createElement("div");
        grid.className = "time-period-grid";
        grid.classList.toggle("dense", slots.length > 12);

        period.slots.forEach((slot) => {
            const option = document.createElement("option");
            option.value = slot;
            option.textContent = slot;
            timeSelect.appendChild(option);

            const button = document.createElement("button");
            button.type = "button";
            button.className = "time-slot";
            button.dataset.time = slot;
            button.textContent = slot;
            button.addEventListener("click", () => selectTime(slot));
            grid.appendChild(button);
        });

        section.append(title, grid);
        timeGrid.appendChild(section);
    });

    status.textContent = `${slots.length} disponibili`;
    updateSummary();
}

function selectTime(time) {
    bookingPageState.selectedTime = time;
    document.getElementById("timeSelect").value = time;
    document.querySelectorAll(".time-slot").forEach((button) => {
        button.classList.toggle("active", button.dataset.time === time);
    });
    document.getElementById("timeStepStatus").textContent = time;
    updateSummary();
}

function resetTimes(message) {
    bookingPageState.selectedTime = "";
    document.getElementById("timeSelect").innerHTML = `<option value="">${message}</option>`;
    const timeGrid = document.getElementById("timeGrid");
    timeGrid.innerHTML = `<p class="time-empty">${message}</p>`;
    document.getElementById("timeStepStatus").textContent = "In attesa";
    updateSummary();
}

function updateSummary() {
    const staffName = bookingPageState.selectedStaff
        ? [bookingPageState.selectedStaff.first_name, bookingPageState.selectedStaff.last_name].filter(Boolean).join(" ")
        : "Da scegliere";
    const serviceName = bookingPageState.selectedService?.name || "Da scegliere";
    const dateLabel = bookingPageState.selectedDate
        ? parseIsoDate(bookingPageState.selectedDate).toLocaleDateString("it-IT", { day: "2-digit", month: "short" })
        : "Da scegliere";
    const timeLabel = bookingPageState.selectedTime || "Da scegliere";

    document.getElementById("summaryStaff").textContent = staffName;
    document.getElementById("summaryService").textContent = serviceName;
    document.getElementById("summaryDate").textContent = dateLabel;
    document.getElementById("summaryTime").textContent = timeLabel;

    const isComplete = Boolean(
        bookingPageState.selectedStaff
        && bookingPageState.selectedService
        && bookingPageState.selectedDate
        && bookingPageState.selectedTime
    );
    document.getElementById("confirmBtn").disabled = !isComplete;
}

async function confirmBooking() {
    const confirmButton = document.getElementById("confirmBtn");
    setBookingActionMessage("");

    if (
        !bookingPageState.selectedStaff
        || !bookingPageState.selectedService
        || !bookingPageState.selectedDate
        || !bookingPageState.selectedTime
    ) {
        window.appButtonState(confirmButton, "error", {
            label: "Completa tutte le scelte",
            resetAfter: 1700,
            disabledAfterReset: true,
            onReset: updateSummary,
        });
        setBookingActionMessage("Scegli barbiere, servizio, giorno e orario prima di continuare.");
        return;
    }

    const bookingData = {
        staff_id: Number(bookingPageState.selectedStaff.id),
        service_id: Number(bookingPageState.selectedService.id),
        date: bookingPageState.selectedDate,
        time: bookingPageState.selectedTime,
        haircut_id: null,
    };

    const currentUser = getUser();
    const note = document.getElementById("bookingNote")?.value.trim();
    if (currentUser?.role === "admin" && note) {
        bookingData.note = note;
    }

    window.appButtonState(confirmButton, "loading", {
        label: "Prenotazione in corso...",
    });

    try {
        const response = await apiPost("/bookings", bookingData);

        if (response.status !== true) {
            const message = getBookingErrorMessage(response);
            window.appButtonState(confirmButton, "error", {
                label: "Prenotazione non riuscita",
                resetAfter: 1900,
                onReset: updateSummary,
            });
            setBookingActionMessage(message);
            return;
        }

        window.appButtonState(confirmButton, "success", {
            label: "Prenotazione confermata",
        });
        setTimeout(() => {
            if (window.AppSpa?.navigate) {
                window.AppSpa.navigate("/my-bookings.html");
            } else {
                window.location.href = "/my-bookings.html";
            }
        }, 1350);
    } catch (error) {
        window.appButtonState(confirmButton, "error", {
            label: "Connessione non disponibile",
            resetAfter: 1900,
            onReset: updateSummary,
        });
        setBookingActionMessage("Non riusciamo a contattare il server. Controlla la connessione e riprova.");
    }
}

function getBookingErrorMessage(response) {
    if (response?.message) return response.message;

    const validationError = response?.errors
        ? Object.values(response.errors).flat().find(Boolean)
        : null;

    return validationError || "Non è stato possibile completare la prenotazione. Riprova.";
}

function setBookingActionMessage(message) {
    const element = document.getElementById("bookingActionMessage");
    element.textContent = message || "";
    element.classList.toggle("show", Boolean(message));
}

function formatPrice(price) {
    return new Intl.NumberFormat("it-IT", {
        style: "currency",
        currency: "EUR",
    }).format(Number(price || 0));
}

function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function parseIsoDate(value) {
    const [year, month, day] = value.split("-").map(Number);
    return new Date(year, month - 1, day);
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function startOfWeek(date) {
    const value = startOfDay(date);
    const mondayOffset = (value.getDay() + 6) % 7;
    value.setDate(value.getDate() - mondayOffset);
    return value;
}

function formatLongDate(date) {
    return date.toLocaleDateString("it-IT", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    });
}

function unmountDashboard() {
    lifecycleController?.abort();
    lifecycleController = null;
    bookingPageState.availabilityRequest += 1;
    document.body.style.overflow = "";
}

window.AppPages = window.AppPages || {};
window.AppPages.dashboard = {
    mount: mountDashboard,
    unmount: unmountDashboard,
};

function autoMountDashboard() {
    if (!window.AppSpa?.active && document.body.classList.contains("booking-page")) {
        void mountDashboard();
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoMountDashboard, { once: true });
} else {
    window.setTimeout(autoMountDashboard, 0);
}
})();
