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

const idError =
    document.getElementById("idError");

const passwordError =
    document.getElementById("passwordError");


const accountTypeInput =
    document.getElementById("accountType");

let accountType = accountTypeInput.value;


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


usernameInput.addEventListener("input", function() {

    clearFieldError(usernameInput, idError);

});

passwordInput.addEventListener("input", function() {

    clearFieldError(passwordInput, passwordError);

});


accountOptions.forEach(function(option) {

    option.addEventListener("click", function() {


        accountOptions.forEach(function(item) {

            item.classList.remove("active");

        });


        option.classList.add("active");


        accountType = option.dataset.type;


        accountTypeInput.value = accountType;


        clearFieldError(usernameInput, idError);


        if (accountType === "student") {

            showToast("Student account selected.");

        } else {

            showToast("Admin account selected.");

        }

    });

});


passwordToggle.addEventListener("click", function() {


    if (passwordInput.type === "password") {


        passwordInput.type = "text";


        eye.classList.remove("hidden");

        eyeSlash.classList.add("hidden");


        passwordToggle.setAttribute(
            "aria-label",
            "Hide password"
        );


    } else {


        passwordInput.type = "password";


        eye.classList.add("hidden");

        eyeSlash.classList.remove("hidden");


        passwordToggle.setAttribute(
            "aria-label",
            "Show password"
        );

    }

});


function showToast(message) {

    toast.textContent = message;

    toast.classList.add("show");


    setTimeout(function() {

        toast.classList.remove("show");

    }, 2300);

}


loginForm.addEventListener("submit", function(event) {


    event.preventDefault();


    const username =
        usernameInput.value.trim();

    const password =
        passwordInput.value.trim();


    clearFieldError(usernameInput, idError);

    clearFieldError(passwordInput, passwordError);


    let hasError = false;


    if (username === "") {

        setFieldError(
            usernameInput,
            idError,
            "Please enter your Student ID."
        );

        usernameInput.focus();

        hasError = true;
    }


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


    loginForm.submit();

});


forgotPassword.addEventListener("click", function(event) {

    event.preventDefault();


    showToast(
        "Password recovery will be available soon."
    );

});


if (idError.classList.contains("show")) {

    usernameInput.focus();

} else if (passwordError.classList.contains("show")) {

    passwordInput.focus();

}