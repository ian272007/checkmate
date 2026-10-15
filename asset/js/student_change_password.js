/* CHECKMATE - student change password page
   (the 3-dots menu is handled by user_menu.js, so it is not repeated here) */

(function () {
    "use strict";

    var API_URL = "change_password_api.php";

    var toast = document.getElementById("cpToast");

    var currentPassword = document.getElementById("currentPassword");
    var newPassword = document.getElementById("newPassword");
    var confirmPassword = document.getElementById("confirmPassword");
    var formError = document.getElementById("formError");
    var saveButton = document.getElementById("saveButton");

    var toastTimer = null;


    // ---------- TOAST ----------
    function showToast(message) {
        toast.textContent = message;
        toast.classList.add("show");

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove("show");
        }, 2500);
    }


    // ---------- SHOW / HIDE (EYE ICON) ----------
    var SVG_OPEN =
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
        '<circle cx="12" cy="12" r="3"/></svg>';

    var SVG_CLOSED =
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>' +
        '<circle cx="12" cy="12" r="3"/>' +
        '<line x1="3" y1="3" x2="21" y2="21"/></svg>';

    function setEye(button, visible) {
        // visible = password is currently shown
        button.innerHTML = visible ? SVG_OPEN : SVG_CLOSED;
        button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
        button.setAttribute("title", visible ? "Hide password" : "Show password");
    }

    var toggleButtons = document.querySelectorAll(".cp-toggle");

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


    // ---------- PASSWORD RULES ----------
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


    // ---------- SAVE ----------
    function save() {

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

        var body = new URLSearchParams();
        body.append("current_password", currentPassword.value);
        body.append("new_password", newPassword.value);
        body.append("confirm_password", confirmPassword.value);

        saveButton.disabled = true;

        fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
            credentials: "same-origin"
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return { success: false, message: "Unexpected response from the server." };
                });
            })
            .then(function (data) {
                if (data.success) {
                    currentPassword.value = "";
                    newPassword.value = "";
                    confirmPassword.value = "";
                    showRules();
                    showToast(data.message || "Password updated.");
                } else {
                    formError.textContent = data.message || "Could not update the password.";
                }
            })
            .catch(function () {
                formError.textContent = "Could not reach the server. Please try again.";
            })
            .then(function () {
                saveButton.disabled = false;
            });
    }

    saveButton.addEventListener("click", save);

    // Enter key in any box also saves
    [currentPassword, newPassword, confirmPassword].forEach(function (input) {
        input.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                save();
            }
        });
    });

})();