(function () {
let lifecycleController = null;

const profilePageState = {
    user: null,
    loyalty: null,
    activeField: null,
    toastTimer: null,
};

const profileFieldLabels = {
    name: "Nome",
    surname: "Cognome",
    email: "Email",
    phone: "Numero di telefono",
};

async function mountProfile() {
    lifecycleController?.abort();
    lifecycleController = new AbortController();
    const { signal } = lifecycleController;

    profilePageState.user = null;
    profilePageState.loyalty = null;
    profilePageState.activeField = null;
    window.clearTimeout(profilePageState.toastTimer);
    profilePageState.toastTimer = null;

    requireAuth();

    if (!getToken()) {
        window.location.href = "index.html";
        return;
    }

    setupProfileActions(signal);
    await Promise.all([loadProfile(), loadLoyalty()]);
}

function setupProfileActions(signal) {
    document.querySelectorAll("[data-edit-field]").forEach((button) => {
        button.addEventListener("click", () => openFieldEditor(button.dataset.editField));
    });
    document.getElementById("changePassword").addEventListener("click", openPasswordEditor);
    document.getElementById("closeSheet").addEventListener("click", () => setSheetOpen(false));
    document.getElementById("sheetOverlay").addEventListener("click", () => setSheetOpen(false));
    document.getElementById("avatarInput").addEventListener("change", uploadAvatar);
    document.getElementById("openLoyalty").addEventListener("click", openLoyaltySheet);
    document.getElementById("closeLoyalty").addEventListener("click", () => setLoyaltyOpen(false));
    document.getElementById("loyaltyOverlay").addEventListener("click", () => setLoyaltyOpen(false));
    document.getElementById("loyaltyRewardsList").addEventListener("click", redeemReward);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            setSheetOpen(false);
            setLoyaltyOpen(false);
        }
    }, { signal });
}

async function loadProfile() {
    try {
        const response = await apiGet("/auth/me");

        if (!response.status || !response.user) {
            throw new Error("Impossibile caricare il profilo.");
        }

        setProfileUser(response.user);
    } catch {
        const cachedUser = getUser();
        if (cachedUser?.email) {
            setProfileUser(cachedUser);
            showFeedback("Profilo offline: dati salvati sul dispositivo");
        }
    }
}

function setProfileUser(user) {
    profilePageState.user = user;
    saveUser(user);
    renderProfile();
}

function renderProfile() {
    const user = profilePageState.user || {};
    const fullName = [user.name, user.surname].filter(Boolean).join(" ") || "Il tuo profilo";

    document.getElementById("profileName").textContent = fullName;
    document.getElementById("profileEmail").textContent = user.email || "";
    document.getElementById("nameValue").textContent = user.name || "-";
    document.getElementById("surnameValue").textContent = user.surname || "-";
    document.getElementById("emailValue").textContent = user.email || "-";
    document.getElementById("phoneValue").textContent = formatPhone(user.phone);

    const avatarImage = document.getElementById("avatarImage");
    const avatarFallback = document.getElementById("avatarFallback");
    const avatarUrl = user.avatar_url || (user.avatar ? `/storage/${user.avatar}` : "");

    if (avatarUrl) {
        avatarImage.src = avatarUrl;
        avatarImage.hidden = false;
        avatarFallback.hidden = true;
        avatarImage.onerror = () => {
            avatarImage.hidden = true;
            avatarFallback.hidden = false;
        };
    } else {
        avatarImage.hidden = true;
        avatarFallback.hidden = false;
    }
}

async function loadLoyalty() {
    try {
        const response = await apiGet("/loyalty/summary");
        const loyalty = response.status ? response.loyalty : null;
        profilePageState.loyalty = loyalty || emptyLoyalty();
        renderLoyalty(profilePageState.loyalty);
        renderLoyaltyDetails(profilePageState.loyalty);
    } catch {
        profilePageState.loyalty = emptyLoyalty();
        renderLoyalty(profilePageState.loyalty);
        renderLoyaltyDetails(profilePageState.loyalty);
    }
}

function renderLoyalty(loyalty) {
    const points = Number(loyalty.balance || 0);
    const tierPoints = Number(loyalty.lifetime_points || points);
    const tier = getTier(tierPoints);
    const progress = tier.nextTarget ? Math.min(100, Math.round((tierPoints / tier.nextTarget) * 100)) : 100;

    document.getElementById("pointsValue").textContent = new Intl.NumberFormat("it-IT").format(points);
    document.getElementById("tierBadge").textContent = tier.name;
    document.getElementById("tierCaption").textContent = tier.nextTarget
        ? `${Math.max(0, tier.nextTarget - tierPoints)} punti al prossimo livello ${tier.nextName}`
        : "Hai raggiunto il livello massimo";
    requestAnimationFrame(() => {
        document.getElementById("tierProgress").style.width = `${progress}%`;
    });
}

function getTier(points) {
    if (points >= 2000) return { name: "Platinum", nextTarget: null, nextName: "" };
    if (points >= 1000) return { name: "Gold", nextTarget: 2000, nextName: "Platinum" };
    if (points >= 500) return { name: "Silver", nextTarget: 1000, nextName: "Gold" };
    return { name: "Bronze", nextTarget: 500, nextName: "Silver" };
}

function emptyLoyalty() {
    return {
        balance: 0,
        lifetime_points: 0,
        available_rewards_count: 0,
        next_reward: null,
        rewards: [],
        rules: [],
        transactions: [],
    };
}

function openLoyaltySheet() {
    renderLoyaltyDetails(profilePageState.loyalty || emptyLoyalty());
    setLoyaltyOpen(true);
}

function setLoyaltyOpen(isOpen) {
    document.getElementById("loyaltySheet").classList.toggle("open", isOpen);
    document.getElementById("loyaltyOverlay").classList.toggle("open", isOpen);
    document.getElementById("loyaltySheet").setAttribute("aria-hidden", String(!isOpen));
    document.body.style.overflow = isOpen ? "hidden" : "";
}

function renderLoyaltyDetails(loyalty) {
    const rewards = loyalty.rewards || [];
    const rules = loyalty.rules || [];
    const transactions = loyalty.transactions || [];
    const nextReward = loyalty.next_reward;

    document.getElementById("loyaltyDetailBalance").textContent =
        new Intl.NumberFormat("it-IT").format(Number(loyalty.balance || 0));
    document.getElementById("loyaltyRewardCount").textContent =
        `${Number(loyalty.available_rewards_count || rewards.length)} ${rewards.length === 1 ? "premio" : "premi"}`;

    if (nextReward) {
        document.getElementById("loyaltyNextReward").textContent = nextReward.name || "Prossimo premio";
        document.getElementById("loyaltyPointsMissing").textContent = Number(nextReward.points_missing || 0) === 0
            ? "Premio raggiunto"
            : `${nextReward.points_missing} punti mancanti`;
        document.getElementById("loyaltyMainProgress").style.width = `${Math.min(100, Number(nextReward.progress || 0))}%`;
    } else {
        document.getElementById("loyaltyNextReward").textContent = "Tutti i premi raggiunti";
        document.getElementById("loyaltyPointsMissing").textContent = "Continua cosi";
        document.getElementById("loyaltyMainProgress").style.width = "100%";
    }

    renderLoyaltyRewards(rewards);
    renderLoyaltyRules(rules);
    renderLoyaltyTransactions(transactions);
}

function renderLoyaltyRewards(rewards) {
    const list = document.getElementById("loyaltyRewardsList");

    if (!rewards.length) {
        list.innerHTML = '<p class="loyalty-empty">Nessun premio disponibile per ora.</p>';
        return;
    }

    list.innerHTML = rewards.map((reward) => `
        <article class="loyalty-reward">
            <div class="loyalty-item-top">
                <div>
                    <h4 class="loyalty-item-title">${escapeHtml(reward.title || "Premio fidelity")}</h4>
                    <p class="loyalty-item-copy">${escapeHtml(reward.description || "Premio disponibile in salone.")}</p>
                    <span class="loyalty-code"><i class="bi bi-upc-scan"></i>${escapeHtml(reward.code || "")}</span>
                </div>
                <i class="bi bi-stars" aria-hidden="true"></i>
            </div>
            <div class="loyalty-reward-bottom">
                <span class="loyalty-item-copy">${reward.expires_at ? `Scade ${formatLoyaltyDate(reward.expires_at)}` : "Senza scadenza"}</span>
                <button type="button" class="loyalty-redeem" data-redeem="${reward.id}">Usa premio</button>
            </div>
        </article>
    `).join("");
}

function renderLoyaltyRules(rules) {
    const list = document.getElementById("loyaltyRulesList");

    if (!rules.length) {
        list.innerHTML = '<p class="loyalty-empty">Le regole fidelity saranno disponibili a breve.</p>';
        return;
    }

    list.innerHTML = rules.map((rule) => `
        <article class="loyalty-rule">
            <div class="loyalty-item-top">
                <div>
                    <h4 class="loyalty-item-title">${escapeHtml(rule.reward_title || rule.name || "Obiettivo")}</h4>
                    <p class="loyalty-item-copy">${escapeHtml([rule.service, rule.name].filter(Boolean).join(" - "))}</p>
                </div>
                <span class="loyalty-rule-value">${Number(rule.current || 0)}/${Number(rule.target || 0)}</span>
            </div>
            <div class="loyalty-rule-progress"><span style="width:${Math.min(100, Number(rule.progress || 0))}%"></span></div>
        </article>
    `).join("");
}

function renderLoyaltyTransactions(transactions) {
    const list = document.getElementById("loyaltyTransactionsList");

    if (!transactions.length) {
        list.innerHTML = '<p class="loyalty-empty">Qui vedrai i punti guadagnati con le prenotazioni completate.</p>';
        return;
    }

    list.innerHTML = transactions.map((transaction) => {
        const points = Number(transaction.points || 0);
        const isNegative = points < 0;
        return `
            <article class="loyalty-transaction">
                <span class="loyalty-transaction-icon">
                    <i class="bi ${isNegative ? "bi-dash-lg" : "bi-plus-lg"}"></i>
                </span>
                <div>
                    <h4 class="loyalty-item-title">${escapeHtml(transaction.description || "Movimento punti")}</h4>
                    <p class="loyalty-item-copy">${formatLoyaltyDate(transaction.created_at)}</p>
                </div>
                <strong class="loyalty-points ${isNegative ? "negative" : ""}">${points > 0 ? "+" : ""}${points}</strong>
            </article>
        `;
    }).join("");
}

async function redeemReward(event) {
    const button = event.target.closest("[data-redeem]");
    if (!button) return;

    const confirmed = await window.appConfirm(
        "Segna il premio come usato soltanto quando sei in salone e stai per riscattarlo.",
        {
            title: "Usare questo premio?",
            confirmText: "Usa premio",
        }
    );

    if (!confirmed) {
        return;
    }

    button.disabled = true;
    button.textContent = "Attendi...";

    try {
        const response = await authenticatedFetch(`/loyalty/rewards/${button.dataset.redeem}/redeem`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: "{}",
        });

        if (!response.ok || response.data.status !== true) {
            throw new Error(response.data.message || "Impossibile usare il premio.");
        }

        showFeedback("Premio segnato come usato");
        await loadLoyalty();
    } catch (error) {
        showFeedback(error.message);
        button.disabled = false;
        button.textContent = "Usa premio";
    }
}

function formatLoyaltyDate(value) {
    if (!value) return "";
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return new Intl.DateTimeFormat("it-IT", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(date);
}

function openFieldEditor(field) {
    if (!profilePageState.user || !profileFieldLabels[field]) return;

    profilePageState.activeField = field;
    document.getElementById("sheetTitle").textContent = `Modifica ${profileFieldLabels[field]}`;
    document.getElementById("sheetForm").innerHTML = `
        <label class="sheet-field">
            <span>${profileFieldLabels[field]}</span>
            <input
                id="profileFieldInput"
                type="${field === "email" ? "email" : field === "phone" ? "tel" : "text"}"
                value="${escapeHtml(profilePageState.user[field] || "")}"
                autocomplete="${field === "email" ? "email" : field === "phone" ? "tel" : "off"}"
                required>
        </label>
        <p id="sheetError" class="sheet-error"></p>
        <button type="submit" class="sheet-submit">Salva modifica</button>`;
    document.getElementById("sheetForm").onsubmit = saveProfileField;
    setSheetOpen(true);
    window.setTimeout(() => document.getElementById("profileFieldInput")?.focus(), 240);
}

async function saveProfileField(event) {
    event.preventDefault();
    const field = profilePageState.activeField;
    const input = document.getElementById("profileFieldInput");
    const submit = event.currentTarget.querySelector(".sheet-submit");
    const payload = {
        name: profilePageState.user.name || "",
        surname: profilePageState.user.surname || "",
        email: profilePageState.user.email || "",
        phone: profilePageState.user.phone || "",
        [field]: input.value.trim(),
    };

    setSheetLoading(submit, true);
    clearSheetError();

    try {
        const response = await authenticatedFetch("/auth/profile", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!response.ok || response.data.status !== true) {
            throw new Error(firstValidationError(response.data) || response.data.message || "Impossibile aggiornare il profilo.");
        }

        setProfileUser(response.data.user);
        setSheetOpen(false);
        showFeedback("Profilo aggiornato");
    } catch (error) {
        showSheetError(error.message);
    } finally {
        setSheetLoading(submit, false);
    }
}

function openPasswordEditor() {
    profilePageState.activeField = "password";
    document.getElementById("sheetTitle").textContent = "Cambia password";
    document.getElementById("sheetForm").innerHTML = `
        <label class="sheet-field">
            <span>Password attuale</span>
            <input name="current_password" type="password" autocomplete="current-password" required>
        </label>
        <label class="sheet-field">
            <span>Nuova password</span>
            <input name="password" type="password" minlength="6" autocomplete="new-password" required>
        </label>
        <label class="sheet-field">
            <span>Conferma nuova password</span>
            <input name="password_confirmation" type="password" minlength="6" autocomplete="new-password" required>
        </label>
        <p id="sheetError" class="sheet-error"></p>
        <button type="submit" class="sheet-submit">Aggiorna password</button>`;
    document.getElementById("sheetForm").onsubmit = savePassword;
    setSheetOpen(true);
}

async function savePassword(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const submit = form.querySelector(".sheet-submit");
    const payload = Object.fromEntries(new FormData(form).entries());

    setSheetLoading(submit, true);
    clearSheetError();

    try {
        const response = await authenticatedFetch("/auth/password", {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        if (!response.ok || response.data.status !== true) {
            throw new Error(firstValidationError(response.data) || response.data.message || "Impossibile aggiornare la password.");
        }

        setSheetOpen(false);
        showFeedback("Password aggiornata");
    } catch (error) {
        showSheetError(error.message);
    } finally {
        setSheetLoading(submit, false);
    }
}

async function uploadAvatar(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (file.size > 4 * 1024 * 1024) {
        showFeedback("La foto non può superare 4 MB");
        event.target.value = "";
        return;
    }

    const formData = new FormData();
    formData.append("avatar", file);
    showFeedback("Caricamento foto...");

    try {
        const response = await authenticatedFetch("/auth/avatar", {
            method: "POST",
            body: formData,
        });

        if (!response.ok || response.data.status !== true) {
            throw new Error(firstValidationError(response.data) || "Impossibile aggiornare la foto.");
        }

        setProfileUser(response.data.user);
        showFeedback("Foto profilo aggiornata");
    } catch (error) {
        showFeedback(error.message);
    } finally {
        event.target.value = "";
    }
}

async function authenticatedFetch(path, options = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
        ...options,
        headers: {
            "Authorization": `Bearer ${getToken()}`,
            "Accept": "application/json",
            ...(options.headers || {}),
        },
    });
    const data = await response.json();
    return { ok: response.ok, data };
}

function setSheetOpen(isOpen) {
    document.getElementById("editSheet").classList.toggle("open", isOpen);
    document.getElementById("sheetOverlay").classList.toggle("open", isOpen);
    document.getElementById("editSheet").setAttribute("aria-hidden", String(!isOpen));
    document.body.style.overflow = isOpen ? "hidden" : "";
}

function setSheetLoading(button, isLoading) {
    if (!button) return;
    button.disabled = isLoading;
    if (isLoading) {
        button.dataset.label = button.textContent;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
    } else {
        button.textContent = button.dataset.label || "Salva";
    }
}

function showSheetError(message) {
    const error = document.getElementById("sheetError");
    if (error) error.textContent = message;
}

function clearSheetError() {
    showSheetError("");
}

function firstValidationError(response) {
    if (!response?.errors) return "";
    const first = Object.values(response.errors)[0];
    return Array.isArray(first) ? first[0] : String(first || "");
}

function formatPhone(phone) {
    if (!phone) return "-";
    const value = String(phone);
    return value.startsWith("+") ? value : `+39 ${value}`;
}

function showFeedback(message) {
    if (window.appToast) {
        const isError = /impossibile|errore|non può|non è|offline/i.test(message);
        const isLoading = /caricamento/i.test(message);
        window.appToast(message, isError ? "error" : isLoading ? "info" : "success");
        return;
    }

    const toast = document.getElementById("feedbackToast");
    window.clearTimeout(profilePageState.toastTimer);
    toast.textContent = message;
    toast.classList.add("show");
    profilePageState.toastTimer = window.setTimeout(() => toast.classList.remove("show"), 1900);
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

function unmountProfile() {
    lifecycleController?.abort();
    lifecycleController = null;
    window.clearTimeout(profilePageState.toastTimer);
    document.body.style.overflow = "";
}

window.AppPages = window.AppPages || {};
window.AppPages.profile = {
    mount: mountProfile,
    unmount: unmountProfile,
};

function autoMountProfile() {
    if (!window.AppSpa?.active && document.body.classList.contains("profile-page")) {
        void mountProfile();
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoMountProfile, { once: true });
} else {
    autoMountProfile();
}
})();
