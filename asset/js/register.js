/*
 * register.js - Sign up + OTP flow for CheckMate
 * Talks to otp_api.php (action = send | resend | verify)
 */

// Kung nasa ibang folder ang otp_api.php, palitan ang path na ito
// hal. '/checkmate/authentication/Register/otp_api.php'
const API_URL = 'otp_api.php';

const $ = (id) => document.getElementById(id);

const signupView = $('signupView');
const otpView    = $('otpView');
const form       = $('signupForm');
const errorMsg   = $('errorMsg');
const createBtn  = $('createBtn');

const otpEmail   = $('otpEmail');
const otpInput   = $('otpInput');
const otpTimer   = $('otpTimer');
const otpError   = $('otpError');
const otpSuccess = $('otpSuccess');
const verifyBtn  = $('verifyBtn');
const resendBtn  = $('resendBtn');
const backBtn    = $('backBtn');

let expiryInterval = null;
let cooldownInterval = null;

/* ---------- helpers ---------- */

async function callApi(data) {
    const body = new FormData();
    Object.entries(data).forEach(([k, v]) => body.append(k, v));

    try {
        const res = await fetch(API_URL, { method: 'POST', body });
        return await res.json();
    } catch (err) {
        return { ok: false, message: 'Network or server error. Please try again.' };
    }
}

function showError(el, msg) {
    el.textContent = msg;
    el.style.display = msg ? 'block' : 'none';
}

function showSuccess(msg) {
    otpSuccess.textContent = msg;
    otpSuccess.style.display = msg ? 'block' : 'none';
}

function setLoading(btn, loading, label) {
    btn.disabled = loading;
    if (label !== undefined) btn.firstChild.textContent = label + ' ';
}

function showOtpView() {
    signupView.classList.add('hidden');
    otpView.classList.remove('hidden');
    otpInput.value = '';
    showError(otpError, '');
    otpInput.focus();
}

function showSignupView() {
    clearInterval(expiryInterval);
    clearInterval(cooldownInterval);
    otpView.classList.add('hidden');
    signupView.classList.remove('hidden');
}

/* ---------- timers ---------- */

function startExpiry(seconds) {
    clearInterval(expiryInterval);
    let left = seconds;

    const tick = () => {
        if (left <= 0) {
            otpTimer.textContent = 'Code expired. Please request a new one.';
            clearInterval(expiryInterval);
            return;
        }
        const m = String(Math.floor(left / 60)).padStart(2, '0');
        const s = String(left % 60).padStart(2, '0');
        otpTimer.textContent = 'Code expires in ' + m + ':' + s;
        left--;
    };

    tick();
    expiryInterval = setInterval(tick, 1000);
}

function startCooldown(seconds) {
    clearInterval(cooldownInterval);
    let left = seconds;
    resendBtn.disabled = true;

    const tick = () => {
        if (left <= 0) {
            resendBtn.textContent = 'Resend code';
            resendBtn.disabled = false;
            clearInterval(cooldownInterval);
            return;
        }
        resendBtn.textContent = 'Resend code (' + left + 's)';
        left--;
    };

    tick();
    cooldownInterval = setInterval(tick, 1000);
}

/* ---------- show / hide password (eye icon) ---------- */

const EYE_OPEN =
    '<svg viewBox="0 0 24 24" aria-hidden="true">' +
    '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
    '<circle cx="12" cy="12" r="3"/></svg>';

const EYE_CLOSED =
    '<svg viewBox="0 0 24 24" aria-hidden="true">' +
    '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
    '<circle cx="12" cy="12" r="3"/>' +
    '<line x1="3" y1="3" x2="21" y2="21"/></svg>';

function setEye(btn, visible) {
    // visible = password is currently shown
    btn.innerHTML = visible ? EYE_OPEN : EYE_CLOSED;
    btn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    btn.setAttribute('title', visible ? 'Hide password' : 'Show password');
}

document.querySelectorAll('.eye-btn').forEach((btn) => {
    setEye(btn, false);

    btn.addEventListener('click', () => {
        const input = $(btn.dataset.target);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        setEye(btn, show);
    });
});

/* ---------- STEP 1: send OTP ---------- */

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showError(errorMsg, '');

    if (!$('agree').checked) {
        showError(errorMsg, 'Please agree to the Terms and Privacy Policy.');
        return;
    }

    if ($('password').value !== $('confirm').value) {
        showError(errorMsg, 'Passwords do not match.');
        return;
    }

    const data = Object.fromEntries(new FormData(form).entries());
    data.action = 'send';

    setLoading(createBtn, true);
    const res = await callApi(data);
    setLoading(createBtn, false);

    if (!res.ok) {
        showError(errorMsg, res.message);
        return;
    }

    otpEmail.textContent = res.email;
    showOtpView();
    startExpiry(res.ttl);
    startCooldown(res.cooldown);
});

/* ---------- STEP 2: verify OTP ---------- */

otpInput.addEventListener('input', () => {
    otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
});

otpInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') verifyBtn.click();
});

verifyBtn.addEventListener('click', async () => {
    showError(otpError, '');
    showSuccess('');

    setLoading(verifyBtn, true);
    const res = await callApi({ action: 'verify', otp: otpInput.value });
    setLoading(verifyBtn, false);

    if (!res.ok) {
        showError(otpError, res.message);
        if (res.restart) {
            setTimeout(showSignupView, 1500);
        }
        return;
    }

    showSuccess(res.message + ' Redirecting...');
    setTimeout(() => { window.location.href = res.redirect; }, 1200);
});

/* ---------- resend ---------- */

resendBtn.addEventListener('click', async () => {
    showError(otpError, '');
    showSuccess('');
    resendBtn.disabled = true;

    const res = await callApi({ action: 'resend' });

    if (!res.ok) {
        showError(otpError, res.message);
        if (res.restart) {
            setTimeout(showSignupView, 1500);
        } else if (res.wait) {
            startCooldown(res.wait);
        } else {
            resendBtn.disabled = false;
        }
        return;
    }

    showSuccess(res.message);
    otpInput.value = '';
    startExpiry(res.ttl);
    startCooldown(res.cooldown);
});

/* ---------- back ---------- */

backBtn.addEventListener('click', showSignupView);