document.addEventListener("DOMContentLoaded", function () {
    const questionForm = document.getElementById("questionForm");
    const questionInput = document.getElementById("questionInput");
    const askBox = document.getElementById("askBox");
    const askButton = document.getElementById("askButton");
    const askButtonLabel = document.getElementById("askButtonLabel");

    const helpButton = document.getElementById("helpButton");
    const userMore = document.getElementById("userMore");
    const viewQuestions = document.getElementById("viewQuestions");

    const toast = document.getElementById("toast");

    /* Ask a Question fields */
    const askSubject = document.getElementById("askSubject");
    const askCourse = document.getElementById("askCourse");
    const askType = document.getElementById("askType");
    const askMessage = document.getElementById("askMessage");

    /* Attachment */
    const attachPlus = document.getElementById("attachPlus");
    const attachMenu = document.getElementById("attachMenu");
    const attachInput = document.getElementById("attachInput");
    const attachName = document.getElementById("attachName");
    const attachClear = document.getElementById("attachClear");

    const ATTACH_DEFAULT_TEXT = "Add attachment (optional)";
    const PHOTO_ACCEPT = ".jpg,.jpeg,.png";
    const FILE_ACCEPT = ".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.xls,.xlsx,.csv";
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB, same as the server

    let isPosting = false;

    function showToast(message) {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.classList.add("show");

        clearTimeout(toast.timeoutId);

        toast.timeoutId = setTimeout(function () {
            toast.classList.remove("show");
        }, 2300);
    }

    function openQuestionsPage() {
        window.location.href = "questions.php";
    }

    /* ---------- Message above the Make a Move button ---------- */

    function showAskMessage(text, isSuccess) {
        if (!askMessage) {
            return;
        }

        askMessage.textContent = text;
        askMessage.classList.toggle("is-success", Boolean(isSuccess));
        askMessage.hidden = false;
    }

    function showPostedMessage() {
        if (!askMessage) {
            return;
        }

        askMessage.textContent = "Your question has been posted. ";

        const link = document.createElement("a");
        link.href = "my_question.php";
        link.textContent = "View in My Questions →";

        askMessage.appendChild(link);
        askMessage.classList.add("is-success");
        askMessage.hidden = false;
    }

    function hideAskMessage() {
        if (askMessage) {
            askMessage.hidden = true;
            askMessage.classList.remove("is-success");
        }
    }

    /* ---------- Attachment menu ---------- */

    function closeAttachMenu() {
        if (!attachMenu || !attachPlus) {
            return;
        }

        attachMenu.hidden = true;
        attachPlus.setAttribute("aria-expanded", "false");
    }

    function toggleAttachMenu() {
        if (!attachMenu || !attachPlus) {
            return;
        }

        const willOpen = attachMenu.hidden;

        attachMenu.hidden = !willOpen;
        attachPlus.setAttribute("aria-expanded", willOpen ? "true" : "false");
    }

    function resetAttachment() {
        if (attachInput) {
            attachInput.value = "";
        }

        if (attachName) {
            attachName.textContent = ATTACH_DEFAULT_TEXT;
            attachName.classList.remove("has-file");
        }

        if (attachClear) {
            attachClear.hidden = true;
        }
    }

    if (attachPlus) {
        attachPlus.addEventListener("click", function (event) {
            event.stopPropagation();
            toggleAttachMenu();
        });
    }

    if (attachMenu && attachInput) {
        attachMenu.querySelectorAll(".attach-option").forEach(function (option) {
            option.addEventListener("click", function (event) {
                event.stopPropagation();

                attachInput.accept =
                    option.dataset.attachment === "photos"
                        ? PHOTO_ACCEPT
                        : FILE_ACCEPT;

                closeAttachMenu();
                attachInput.click();
            });
        });

        attachInput.addEventListener("change", function () {
            const file = attachInput.files && attachInput.files[0];

            if (!file) {
                resetAttachment();
                return;
            }

            if (file.size > MAX_FILE_SIZE) {
                resetAttachment();
                showAskMessage("That file is too big. Please choose one that is 5 MB or smaller.");
                return;
            }

            hideAskMessage();

            if (attachName) {
                attachName.textContent = file.name;
                attachName.classList.add("has-file");
            }

            if (attachClear) {
                attachClear.hidden = false;
            }
        });
    }

    if (attachClear) {
        attachClear.addEventListener("click", resetAttachment);
    }

    // click outside / Escape closes the menu
    document.addEventListener("click", function (event) {
        if (
            attachMenu &&
            !attachMenu.hidden &&
            !attachMenu.contains(event.target) &&
            event.target !== attachPlus
        ) {
            closeAttachMenu();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeAttachMenu();
        }
    });

    /* ---------- Clear the red outline when a field is fixed ---------- */

    [askSubject, askCourse, askType].forEach(function (select) {
        if (!select) {
            return;
        }

        select.addEventListener("change", function () {
            select.classList.remove("is-invalid");
            hideAskMessage();
        });
    });

    if (questionInput) {
        questionInput.addEventListener("input", function () {
            questionInput.classList.remove("is-invalid");
            hideAskMessage();
        });
    }

    /* ---------- Validation ---------- */

    function validateAsk() {
        const text = questionInput ? questionInput.value.trim() : "";

        if (text === "") {
            questionInput.classList.add("is-invalid");
            showAskMessage("Please write your question first.");
            questionInput.focus();
            return false;
        }

        let valid = true;

        [askSubject, askCourse, askType].forEach(function (select) {
            if (select && select.value === "") {
                select.classList.add("is-invalid");
                valid = false;
            }
        });

        if (!valid) {
            showAskMessage("Please choose a subject, a course, and a question type.");
        }

        return valid;
    }

    /* ---------- Post the question straight from Home ---------- */

    function setPosting(posting) {
        isPosting = posting;

        if (askButton) {
            askButton.disabled = posting;
        }

        if (askButtonLabel) {
            askButtonLabel.textContent = posting ? "Posting..." : "Make a Move";
        }
    }

    function clearAskForm() {
        if (questionInput) {
            questionInput.value = "";
            questionInput.classList.remove("is-invalid");
        }

        [askSubject, askCourse, askType].forEach(function (select) {
            if (select) {
                select.selectedIndex = 0;
                select.classList.remove("is-invalid");
            }
        });

        resetAttachment();
    }

    function postQuestion() {
        if (isPosting || !validateAsk()) {
            return;
        }

        hideAskMessage();

        const body = new FormData();
        body.append("csrf", questionForm ? questionForm.dataset.csrf || "" : "");
        body.append("question", questionInput.value.trim());
        body.append("subject", askSubject.value);
        body.append("question_course", askCourse.value);
        body.append("question_type", askType.value);

        if (attachInput && attachInput.files && attachInput.files[0]) {
            body.append("attachment", attachInput.files[0]);
        }

        setPosting(true);

        fetch("question_post_api.php", {
            method: "POST",
            body: body
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    clearAskForm();
                    showPostedMessage();
                    showToast("Your question has been posted.");
                } else {
                    showAskMessage(data.message || "Could not post your question.");
                }
            })
            .catch(function () {
                showAskMessage("Something went wrong. Please try again.");
            })
            .finally(function () {
                setPosting(false);
            });
    }

    function focusQuestionBox() {
        if (!askBox || !questionInput) {
            return;
        }

        askBox.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });

        setTimeout(function () {
            questionInput.focus();
        }, 450);
    }

    const newQuestionButton = document.getElementById("newQuestionButton");

    if (newQuestionButton) {
        newQuestionButton.addEventListener("click", function (event) {
            event.preventDefault();
            focusQuestionBox();
            showToast("Ready for your next move.");
        });
    }

    if (questionInput && askBox) {
        questionInput.addEventListener("focus", function () {
            askBox.classList.add("focused");
        });

        questionInput.addEventListener("blur", function () {
            askBox.classList.remove("focused");
        });

        // Ctrl + Enter posts the question
        questionInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                postQuestion();
            }
        });
    }

    if (askButton) {
        askButton.addEventListener("click", postQuestion);
    }

    if (helpButton) {
        helpButton.addEventListener("click", function () {
            showToast("Need help? Check the system guide.");
        });
    }

    if (userMore) {
        userMore.addEventListener("click", function () {
            showToast("Account options will appear here.");
        });
    }

    if (viewQuestions) {
        viewQuestions.addEventListener("click", function (event) {
            event.preventDefault();
            openQuestionsPage();
        });
    }

    const quickCards = document.querySelectorAll(".quick-card");

    quickCards.forEach(function (card) {
        function handleCardAction() {
            const action = card.dataset.action;

            if (action === "browse") {
                openQuestionsPage();
            } else if (action === "collaborate") {
                showToast("Opening your collaborations.");
            } else if (action === "ask") {
                focusQuestionBox();
                showToast("Start your question.");
            }
        }

        card.addEventListener("click", handleCardAction);

        card.addEventListener("keydown", function (event) {
            if (
                event.key === "Enter" ||
                event.key === " "
            ) {
                event.preventDefault();
                handleCardAction();
            }
        });
    });

    const questionItems = document.querySelectorAll(".question-item");

    questionItems.forEach(function (item) {
        item.addEventListener("click", function () {
            const question = item.dataset.question;

            if (question) {
                showToast("Opening: " + question);
            } else {
                showToast("Opening question details.");
            }
        });
    });

    const openButtons = document.querySelectorAll(".open-button");

    openButtons.forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.stopPropagation();

            const person = button.dataset.person;

            if (person) {
                showToast("Opening collaboration with " + person);
            } else {
                showToast("Opening collaboration details.");
            }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (
            event.key === "/" &&
            questionInput &&
            document.activeElement !== questionInput &&
            !event.ctrlKey &&
            !event.altKey &&
            !event.metaKey
        ) {
            event.preventDefault();
            focusQuestionBox();
        }
    });
});