/* =========================================
   CHECKMATE LOGIN JAVASCRIPT
========================================= */


/* =========================================
   GET ELEMENTS
========================================= */

const accountOptions =
    document.querySelectorAll(".account-option");

const passwordInput =
    document.getElementById("password");

const passwordToggle =
    document.getElementById("passwordToggle");

const eye =
    document.getElementById("eye");

const eyeSlash =
    document.getElementById("eyeSlash");

const loginForm =
    document.getElementById("loginForm");

const usernameInput =
    document.getElementById("username");

const forgotPassword =
    document.getElementById("forgotPassword");

const toast =
    document.getElementById("toast");

// red messages under the boxes
const idError =
    document.getElementById("idError");

const passwordError =
    document.getElementById("passwordError");


/* =========================================
   DEFAULT ACCOUNT TYPE
========================================= */

// the hidden input that gets sent to PHP
const accountTypeInput =
    document.getElementById("accountType");

// start with whatever PHP put in the hidden input (student or admin)
let accountType = accountTypeInput.value;


/* =========================================
   FIELD ERRORS (red message under a box)
========================================= */

function setFieldError(input, errorElement, message) {

    errorElement.textContent = message;

    errorElement.classList.add("show");

    input.classList.add("input-error");

}


function clearFieldError(input, errorElement) {

    errorElement.textContent = "";

    errorElement.classList.remove("show");

    input.classList.remove("input-error");

}


// the red message goes away as soon as the user types again
usernameInput.addEventListener("input", function() {

    clearFieldError(usernameInput, idError);

});

passwordInput.addEventListener("input", function() {

    clearFieldError(passwordInput, passwordError);

});


/* =========================================
   ACCOUNT TYPE SELECTION
========================================= */

accountOptions.forEach(function(option) {

    option.addEventListener("click", function() {


        /* Remove active from all */
        accountOptions.forEach(function(item) {

            item.classList.remove("active");

        });


        /* Add active to clicked option */
        option.classList.add("active");


        /* Save account type */
        accountType = option.dataset.type;


        /* Also save it in the hidden input so PHP receives it */
        accountTypeInput.value = accountType;


        /* The old "wrong account type" message no longer applies */
        clearFieldError(usernameInput, idError);


        /* Small feedback */
        if (accountType === "student") {

            showToast("Student account selected.");

        } else {

            showToast("Admin account selected.");

        }

    });

});


/* =========================================
   PASSWORD SHOW / HIDE
========================================= */

passwordToggle.addEventListener("click", function() {


    if (passwordInput.type === "password") {


        /* Show password */

        passwordInput.type = "text";


        eye.classList.remove("hidden");

        eyeSlash.classList.add("hidden");


        passwordToggle.setAttribute(
            "aria-label",
            "Hide password"
        );


    } else {


        /* Hide password */

        passwordInput.type = "password";


        eye.classList.add("hidden");

        eyeSlash.classList.remove("hidden");


        passwordToggle.setAttribute(
            "aria-label",
            "Show password"
        );

    }

});


/* =========================================
   TOAST FUNCTION
========================================= */

function showToast(message) {

    toast.textContent = message;

    toast.classList.add("show");


    setTimeout(function() {

        toast.classList.remove("show");

    }, 2300);

}


/* =========================================
   LOGIN FORM
========================================= */

loginForm.addEventListener("submit", function(event) {


    /* Stop the form from sending for now.
       We check the inputs first, then send it with loginForm.submit() below. */
    event.preventDefault();


    /* Get input values */
    const username =
        usernameInput.value.trim();

    const password =
        passwordInput.value.trim();


    /* Start clean */
    clearFieldError(usernameInput, idError);

    clearFieldError(passwordInput, passwordError);


    let hasError = false;


    /* =====================================
       CHECK STUDENT ID
    ====================================== */

    if (username === "") {

        setFieldError(
            usernameInput,
            idError,
            "Please enter your Student ID."
        );

        usernameInput.focus();

        hasError = true;
    }


    /* =====================================
       CHECK PASSWORD
    ====================================== */

    if (password === "") {

        setFieldError(
            passwordInput,
            passwordError,
            "Please enter your password."
        );

        if (!hasError) {

            passwordInput.focus();

        }

        hasError = true;
    }


    if (hasError) {

        return;

    }


    /* =====================================
       SEND TO PHP

       Both fields are filled in, so we
       submit the form to login.php.
       PHP checks the Student ID, password
       and account type, then redirects
       to student_home.php or admin_home.php.
    ====================================== */

    loginForm.submit();

});


/* =========================================
   FORGOT PASSWORD
========================================= */

forgotPassword.addEventListener("click", function(event) {

    event.preventDefault();


    showToast(
        "Password recovery will be available soon."
    );

});


/* =========================================
   ERROR FROM PHP

   If login.php found a problem it already
   printed the red message under the box.
   Here we only move the cursor there.
========================================= */

if (idError.classList.contains("show")) {

    usernameInput.focus();

} else if (passwordError.classList.contains("show")) {

    passwordInput.focus();

}