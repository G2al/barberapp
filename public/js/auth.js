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
    window.location.href = "/login.html";
}

function requireAuth() {
    if (!getToken()) {
        window.location.href = "/login.html";
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



// ====== API LOGIN ======
async function loginUser(data) {
    const res = await fetch(`${API_BASE}/auth/login`, {
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
            showAlert(res.errors.email[0], "danger");
            return;
        }

        if (res.errors.phone) {
            showAlert(res.errors.phone[0], "danger");
            return;
        }

        if (res.errors.password) {
            showAlert(res.errors.password[0], "danger");
            return;
        }

        if (res.errors.name) {
            showAlert(res.errors.name[0], "danger");
            return;
        }

        if (res.errors.surname) {
            showAlert(res.errors.surname[0], "danger");
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