var toast = document.getElementById("toast");

var currentPassword = document.getElementById("currentPassword");
var newPassword = document.getElementById("newPassword");
var confirmPassword = document.getElementById("confirmPassword");
var formError = document.getElementById("formError");
var saveButton = document.getElementById("saveButton");


function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


var SVG_OPEN =
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" ' +
    'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
    '<circle cx="12" cy="12" r="3"/></svg>';

var SVG_CLOSED =
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" ' +
    'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
    '<circle cx="12" cy="12" r="3"/>' +
    '<line x1="3" y1="3" x2="21" y2="21"/></svg>';

function setEye(button, visible) {
    button.innerHTML = visible ? SVG_OPEN : SVG_CLOSED;
    button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
    button.setAttribute("title", visible ? "Hide password" : "Show password");
}

var toggleButtons = document.querySelectorAll(".toggle-btn");

for (var t = 0; t < toggleButtons.length; t++) {
    setEye(toggleButtons[t], false);

    toggleButtons[t].addEventListener("click", function () {

        var input = document.getElementById(this.getAttribute("data-target"));

        if (!input) return;

        if (input.type === "password") {
            input.type = "text";
            setEye(this, true);
        } else {
            input.type = "password";
            setEye(this, false);
        }
    });
}


function checkRules() {

    var pass = newPassword.value;

    return {
        length: pass.length >= 8,
        upper: /[A-Z]/.test(pass),
        lower: /[a-z]/.test(pass),
        number: /[0-9]/.test(pass),
        match: pass !== "" && pass === confirmPassword.value
    };
}

function setRule(id, passed) {
    var item = document.getElementById(id);

    if (passed) {
        item.classList.add("ok");
    } else {
        item.classList.remove("ok");
    }
}

function showRules() {
    var rules = checkRules();

    setRule("ruleLength", rules.length);
    setRule("ruleUpper", rules.upper);
    setRule("ruleLower", rules.lower);
    setRule("ruleNumber", rules.number);
    setRule("ruleMatch", rules.match);
}

newPassword.addEventListener("input", showRules);
confirmPassword.addEventListener("input", showRules);


saveButton.addEventListener("click", function () {

    formError.textContent = "";

    if (currentPassword.value === "" || newPassword.value === "" || confirmPassword.value === "") {
        formError.textContent = "Please fill in all three password boxes.";
        return;
    }

    var rules = checkRules();

    if (!rules.length || !rules.upper || !rules.lower || !rules.number) {
        formError.textContent = "The new password does not meet all of the rules.";
        return;
    }

    if (!rules.match) {
        formError.textContent = "The new passwords do not match.";
        return;
    }

    if (newPassword.value === currentPassword.value) {
        formError.textContent = "The new password must be different from the current one.";
        return;
    }

    var data = new FormData();
    data.append("current_password", currentPassword.value);
    data.append("new_password", newPassword.value);
    data.append("confirm_password", confirmPassword.value);

    saveButton.disabled = true;

    fetch("change_password_api.php", { method: "POST", body: data })
        .then(function (response) { return response.json(); })
        .then(function (result) {

            saveButton.disabled = false;

            if (!result.ok) {
                formError.textContent = result.message;
                return;
            }

            currentPassword.value = "";
            newPassword.value = "";
            confirmPassword.value = "";
            showRules();

            showToast("Password updated.");
        })
        .catch(function () {
            saveButton.disabled = false;
            formError.textContent = "Server error. Please try again.";
        });
});


var accountButton = document.getElementById("accountMenuButton");
var accountMenu = document.getElementById("accountMenu");

accountButton.addEventListener("click", function (event) {
    event.stopPropagation();
    accountMenu.classList.toggle("show");
    accountButton.classList.toggle("open");
});

document.addEventListener("click", function () {
    accountMenu.classList.remove("show");
    accountButton.classList.remove("open");
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        accountMenu.classList.remove("show");
        accountButton.classList.remove("open");
    }
});


document.getElementById("notificationButton").addEventListener("click", function () {
    showToast("You have 3 new notifications.");
});