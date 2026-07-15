// ====== ALERT (rimane fisso) ======
function showAlert(message, type = "danger") {
    const alertBox = document.getElementById("alertBox");

    alertBox.innerHTML = `
        <div class="alert alert-${type} rounded-4 py-2 px-3 mb-3">
            ${message}
        </div>
    `;
}



// ====== BUTTON LOADING STATE ======
function setButtonLoading(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Caricamento...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || button.innerHTML;
    }
}



// ====== TRANSLATE ERROR MESSAGES ======
function translateErrorMessage(message) {
    const translations = {
        "The email has already been taken.": "Questa email è già stata utilizzata.",
        "The phone has already been taken.": "Questo numero di telefono è già stato utilizzato.",
        "The password must be at least 6 characters.": "La password deve contenere almeno 6 caratteri.",
        "Email or password invalid": "Email o password non validi",
        "The name field is required.": "Il campo nome è obbligatorio.",
        "The surname field is required.": "Il campo cognome è obbligatorio.",
        "The email field is required.": "Il campo email è obbligatorio.",
        "The phone field is required.": "Il campo telefono è obbligatorio.",
        "The password field is required.": "Il campo password è obbligatorio."
    };

    return translations[message] || message;
}



// ====== TOKEN ======
function saveToken(token) {
    localStorage.setItem("token", token);
}

function getToken() {
    return localStorage.getItem("token");
}

function saveUser(user) {
    localStorage.setItem("user", JSON.stringify(user));
}

function getUser() {
    return JSON.parse(localStorage.getItem("user") || "{}");
}

function logout() {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    window.location.href = "/index.html";
}

function requireAuth() {
    if (!getToken()) {
        window.location.href = "/index.html";
    }
}



// ====== API REGISTER ======
async function registerUser(data) {
    const res = await fetch(`${API_BASE}/auth/register`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });

    return await res.json();
}

// ====== PROTECTED DELETE ======
async function apiDelete(url) {
    const res = await fetch(`${API_BASE}${url}`, {
        method: "DELETE",
        headers: {
            "Authorization": `Bearer ${getToken()}`,
            "Accept": "application/json"
        }
    });
    return await res.json();
}


// ====== API LOGIN ======
async function loginUser(data) {
    const res = await fetch(`${API_BASE}/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });

    return await res.json();
}

// ====== FORGOT PASSWORD ======
async function requestPasswordReset(email) {
    const res = await fetch(`${API_BASE}/auth/forgot-password`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email })
    });

    return await res.json();
}

// ====== RESET PASSWORD ======
async function resetPassword(data) {
    const res = await fetch(`${API_BASE}/auth/reset-password`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });

    return await res.json();
}



// ====== API LOGOUT ======
async function apiLogout() {
    const res = await fetch(`${API_BASE}/auth/logout`, {
        method: "POST",
        headers: {
            "Authorization": `Bearer ${getToken()}`,
            "Accept": "application/json"
        }
    });

    return await res.json();
}



// ====== PROTECTED GET ======
async function apiGet(url) {
    const res = await fetch(`${API_BASE}${url}`, {
        headers: {
            "Authorization": `Bearer ${getToken()}`,
            "Accept": "application/json"
        }
    });

    return await res.json();
}



// ====== PROTECTED POST ======
async function apiPost(url, data) {
    const token = getToken();
    console.log("apiPost - Token being sent:", token);
    
    const res = await fetch(`${API_BASE}${url}`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Authorization": `Bearer ${token}`,
            "Accept": "application/json"
        },
        body: JSON.stringify(data)
    });

    console.log("apiPost - Response status:", res.status);
    return await res.json();
}



// ====== SUBMIT REGISTER ======
async function submitRegister() {
    const submitBtn = document.getElementById("submitBtn");

    const data = {
        name: document.getElementById("name").value.trim(),
        surname: document.getElementById("surname").value.trim(),
        email: document.getElementById("email").value.trim(),
        phone: document.getElementById("phone").value.trim(),
        password: document.getElementById("password").value.trim()
    };

    setButtonLoading(submitBtn, true);
    const res = await registerUser(data);
    setButtonLoading(submitBtn, false);

    // ERRORI DI VALIDAZIONE (email / telefono / altro)
    if (res.errors) {

        if (res.errors.email) {
            showAlert(translateErrorMessage(res.errors.email[0]), "danger");
            return;
        }

        if (res.errors.phone) {
            showAlert(translateErrorMessage(res.errors.phone[0]), "danger");
            return;
        }

        if (res.errors.password) {
            showAlert(translateErrorMessage(res.errors.password[0]), "danger");
            return;
        }

        if (res.errors.name) {
            showAlert(translateErrorMessage(res.errors.name[0]), "danger");
            return;
        }

        if (res.errors.surname) {
            showAlert(translateErrorMessage(res.errors.surname[0]), "danger");
            return;
        }

        showAlert("Errore di validazione", "danger");
        return;
    }

    // STATUS FALSE (errore generico)
    if (res.status === false) {
        showAlert(res.message || "Errore durante la registrazione", "danger");
        return;
    }

    // REGISTRAZIONE OK
    if (res.status === true && res.token) {
        saveToken(res.token);

        // Salva anche i dati utente
        if (res.user) {
            saveUser(res.user);
        }

        showAlert("Registrazione completata!", "success");

        setTimeout(() => {
            window.location.href = "/dashboard.html";
        }, 1000);

        return;
    }

    // FALLBACK
    showAlert("Errore sconosciuto, riprova.", "danger");
}




// ====== SUBMIT LOGIN ======
async function submitLogin() {
    const submitBtn = document.getElementById("submitBtn");
    const rememberLogin = document.getElementById("rememberLogin");

    const data = {
        email: document.getElementById("email").value.trim(),
        password: document.getElementById("password").value.trim()
    };

    setButtonLoading(submitBtn, true);
    const res = await loginUser(data);
    setButtonLoading(submitBtn, false);

    // LOGIN FALLITO
    if (res.status === false) {
        showAlert(res.message || "Email o password non validi", "danger");
        return;
    }

    // LOGIN OK
    if (res.token) {
        saveToken(res.token);

        if (rememberLogin?.checked) {
            localStorage.setItem("remembered_email", data.email);
        } else {
            localStorage.removeItem("remembered_email");
        }

        // Salva anche i dati utente
        if (res.user) {
            saveUser(res.user);
        }

        showAlert("Accesso effettuato!", "success");

        setTimeout(() => {
            window.location.href = "/dashboard.html";
        }, 1000);

        return;
    }

    // ERRORE GENERICO
    showAlert("Errore durante il login.", "danger");
}

document.addEventListener("DOMContentLoaded", () => {
    const rememberedEmail = localStorage.getItem("remembered_email");
    const emailInput = document.getElementById("email");
    const rememberLogin = document.getElementById("rememberLogin");

    if (rememberedEmail && emailInput) {
        emailInput.value = rememberedEmail;
        if (rememberLogin) rememberLogin.checked = true;
    }
});



// ====== SUBMIT FORGOT PASSWORD ======
async function submitForgot() {
    const submitBtn = document.getElementById("submitBtn");
    const email = document.getElementById("email").value.trim();

    if (!email) {
        showAlert("Inserisci la tua email.", "danger");
        return;
    }

    setButtonLoading(submitBtn, true);
    const res = await requestPasswordReset(email);
    setButtonLoading(submitBtn, false);

    if (res.errors) {
        if (res.errors.email) {
            showAlert(res.errors.email[0], "danger");
            return;
        }
    }

    if (res.status === false) {
        showAlert(res.message || "Errore durante la richiesta di reset.", "danger");
        return;
    }

    showAlert(res.message || "Se l'email è registrata, ti abbiamo inviato il link di reset.", "success");
}



// ====== SUBMIT RESET PASSWORD ======
async function submitReset() {
    const submitBtn = document.getElementById("submitBtn");

    const data = {
        email: document.getElementById("email").value.trim(),
        token: document.getElementById("token").value.trim(),
        password: document.getElementById("password").value.trim(),
        password_confirmation: document.getElementById("password_confirmation").value.trim(),
    };

    if (!data.email || !data.token) {
        showAlert("Link non valido. Richiedi un nuovo reset.", "danger");
        return;
    }

    if (!data.password || !data.password_confirmation) {
        showAlert("Inserisci e conferma la nuova password.", "danger");
        return;
    }

    if (data.password.length < 6) {
        showAlert("La password deve contenere almeno 6 caratteri.", "danger");
        return;
    }

    if (data.password !== data.password_confirmation) {
        showAlert("Le password non coincidono.", "danger");
        return;
    }

    setButtonLoading(submitBtn, true);
    const res = await resetPassword(data);
    setButtonLoading(submitBtn, false);

    if (res.errors) {
        if (res.errors.password) {
            showAlert(translateErrorMessage(res.errors.password[0]), "danger");
            return;
        }

        if (res.errors.email) {
            showAlert(translateErrorMessage(res.errors.email[0]), "danger");
            return;
        }

        if (res.errors.token) {
            showAlert(translateErrorMessage(res.errors.token[0]), "danger");
            return;
        }

        showAlert("Errore di validazione.", "danger");
        return;
    }

    if (res.status === false) {
        showAlert(res.message || "Reset fallito. Richiedi un nuovo link.", "danger");
        return;
    }

    showAlert("Password aggiornata! Ora puoi accedere.", "success");

    setTimeout(() => {
        window.location.href = "/index.html";
    }, 1200);
}
