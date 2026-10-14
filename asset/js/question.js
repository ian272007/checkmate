const searchInput = document.getElementById("searchQuestions");

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const search = this.value.toLowerCase();
        const questions = document.querySelectorAll(".question-card");

        questions.forEach(function (question) {
            // Search only the question content, not the report menu text
            const details = question.querySelector(".question-details");
            const text = (details ? details : question).textContent.toLowerCase();

            if (text.includes(search)) {
                question.style.display = "flex";
            } else {
                question.style.display = "none";
            }
        });
    });
}


/* Attachment menu */

const attachmentPlus = document.getElementById("attachmentPlus");
const attachmentMenu = document.getElementById("attachmentMenu");
const attachmentInput = document.getElementById("attachment");
const attachmentSelected = document.getElementById("attachmentSelected");

if (attachmentPlus && attachmentMenu && attachmentInput) {

    attachmentPlus.addEventListener("click", function () {
        const isOpen = !attachmentMenu.hidden;

        attachmentMenu.hidden = isOpen;
        attachmentPlus.setAttribute("aria-expanded", String(!isOpen));
    });

    document.querySelectorAll(".attachment-option").forEach(function (option) {

        option.addEventListener("click", function () {
            const choice = this.dataset.attachment;

            attachmentInput.value = "";

            if (choice === "photos") {
                attachmentInput.accept = ".jpg,.jpeg,.png";
            } else {
                attachmentInput.accept =
                    ".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.xls,.xlsx,.csv";
            }

            attachmentMenu.hidden = true;
            attachmentPlus.setAttribute("aria-expanded", "false");

            attachmentInput.click();
        });

    });

    attachmentInput.addEventListener("change", function () {
        if (this.files.length > 0) {
            attachmentSelected.textContent = this.files[0].name;
        } else {
            attachmentSelected.textContent = "Add attachment (optional)";
        }
    });

    document.addEventListener("click", function (event) {
        if (
            !attachmentMenu.contains(event.target) &&
            !attachmentPlus.contains(event.target)
        ) {
            attachmentMenu.hidden = true;
            attachmentPlus.setAttribute("aria-expanded", "false");
        }
    });

}


/* Answer form: open and close under each question */

document.querySelectorAll(".question-card").forEach(function (card) {
    const form = card.querySelector(".answer-form");

    if (!form) {
        return;
    }

    const toggles = card.querySelectorAll(".answer-toggle");
    const button = card.querySelector(".answer-toggle-btn");
    const textarea = form.querySelector("textarea");
    const cancel = form.querySelector(".answer-cancel");
    const submit = form.querySelector(".answer-submit");

    function setOpen(open) {
        form.hidden = !open;
        card.classList.toggle("answering", open);

        if (button) {
            button.setAttribute("aria-expanded", String(open));
        }

        if (open && textarea) {
            textarea.focus();
        }
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener("click", function () {
            setOpen(form.hidden);
        });

        toggle.addEventListener("keydown", function (event) {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                setOpen(form.hidden);
            }
        });
    });

    if (cancel) {
        cancel.addEventListener("click", function () {
            if (textarea) {
                textarea.value = "";
            }

            setOpen(false);
        });
    }

    // Prevent double posting
    form.addEventListener("submit", function () {
        if (submit) {
            submit.disabled = true;
            submit.textContent = "Posting...";
        }
    });
});


/* Pop-up message (for example "Your question has been posted.") */

function showFlash(type, message) {
    let box = document.getElementById("cmFlash");

    if (!box) {
        box = document.createElement("div");
        box.id = "cmFlash";
        box.setAttribute("role", "status");
        box.setAttribute("aria-live", "polite");
        document.body.appendChild(box);
    }

    const toast = document.createElement("div");
    toast.className = "cm-flash" + (type === "error" ? " error" : "");

    const text = document.createElement("span");
    text.textContent = message;

    const close = document.createElement("button");
    close.type = "button";
    close.className = "cm-flash-close";
    close.setAttribute("aria-label", "Close");
    close.textContent = "×";

    function remove() {
        toast.classList.add("hide");
        setTimeout(function () {
            toast.remove();
        }, 250);
    }

    close.addEventListener("click", remove);

    toast.append(text, close);
    box.appendChild(toast);

    setTimeout(remove, 5000);
}

const flashData = document.getElementById("flashData");

if (flashData) {
    showFlash(flashData.dataset.type, flashData.dataset.message);

    // Remove ?notice=... so the message does not show again on refresh
    if (window.history && window.history.replaceState) {
        window.history.replaceState(
            null,
            "",
            window.location.pathname + window.location.hash
        );
    }
}


/* =========================================================
   Report menu (three dots)
   ========================================================= */

const questionsList = document.getElementById("questionsList");
let openReportMenu = null;

function closeReportMenu() {
    if (openReportMenu) {
        openReportMenu.dropdown.hidden = true;
        openReportMenu.button.setAttribute("aria-expanded", "false");
        openReportMenu = null;
    }
}

function placeReportMenu(button, dropdown) {
    dropdown.hidden = false;

    const rect = button.getBoundingClientRect();
    const width = dropdown.offsetWidth;
    const height = dropdown.offsetHeight;

    // open below the dots, or above if there is no room
    let top = rect.bottom + 6;
    if (top + height > window.innerHeight - 8) {
        top = Math.max(8, rect.top - height - 6);
    }

    dropdown.style.top = top + "px";
    dropdown.style.left = Math.max(8, rect.right - width) + "px";
}

function markReported(card) {
    const dropdown = card.querySelector(".report-dropdown");

    if (!dropdown) {
        return;
    }

    dropdown.innerHTML = "";

    const done = document.createElement("div");
    done.className = "report-done";
    done.textContent = "✓ You already reported this question";
    dropdown.appendChild(done);
}

if (questionsList) {
    const reportCsrf = questionsList.dataset.csrf || "";

    questionsList.addEventListener("click", function (event) {

        // open / close the three-dot menu
        const dots = event.target.closest(".report-dots");

        if (dots) {
            event.stopPropagation();

            const dropdown = dots
                .closest(".report-menu")
                .querySelector(".report-dropdown");

            if (openReportMenu && openReportMenu.button === dots) {
                closeReportMenu();
                return;
            }

            closeReportMenu();
            placeReportMenu(dots, dropdown);
            dots.setAttribute("aria-expanded", "true");
            openReportMenu = { button: dots, dropdown: dropdown };
            return;
        }

        // choose a reason
        const option = event.target.closest(".report-option");

        if (!option) {
            return;
        }

        const card = option.closest(".question-card");
        const questionId = card.id.replace("question-", "");
        const reason = option.dataset.reason;

        if (!confirm('Report this question as "' + reason + '"?')) {
            return;
        }

        option.disabled = true;

        const body = new URLSearchParams();
        body.append("question_id", questionId);
        body.append("reason", reason);
        body.append("csrf", reportCsrf);

        fetch("report_question_api.php", { method: "POST", body: body })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                closeReportMenu();

                if (data.success || data.already) {
                    markReported(card);
                }

                showFlash(data.success ? "success" : "error", data.message);
            })
            .catch(function () {
                closeReportMenu();
                showFlash("error", "Something went wrong. Please try again.");
            })
            .finally(function () {
                option.disabled = false;
            });
    });
}

// click anywhere else, press Esc, scroll or resize = close the menu
document.addEventListener("click", function (event) {
    if (openReportMenu && !openReportMenu.dropdown.contains(event.target)) {
        closeReportMenu();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeReportMenu();
    }
});

window.addEventListener("scroll", closeReportMenu, true);
window.addEventListener("resize", closeReportMenu);

/* =========================================================
   Open a question from Home (questions.php#question-ID):
   scroll to it and highlight it for a moment
   ========================================================= */

(function () {
    const match = window.location.hash.match(/^#question-(\d+)$/);

    if (!match) {
        return;
    }

    const targetId = "question-" + match[1];

    // highlight animation (no CSS file edit needed)
    const style = document.createElement("style");
    style.textContent =
        "@keyframes cmPulse {" +
        "0%{box-shadow:0 0 0 0 rgba(212,160,60,.65);}" +
        "70%{box-shadow:0 0 0 14px rgba(212,160,60,0);}" +
        "100%{box-shadow:0 0 0 0 rgba(212,160,60,0);}}" +
        ".question-card.cm-highlight{" +
        "border-color:#d4a03c !important;" +
        "animation:cmPulse 1.2s ease-out 3;}";
    document.head.appendChild(style);

    let tries = 0;

    const timer = setInterval(function () {
        const card = document.getElementById(targetId);
        tries++;

        if (card) {
            clearInterval(timer);

            card.scrollIntoView({ behavior: "smooth", block: "center" });
            card.classList.add("cm-highlight");

            setTimeout(function () {
                card.classList.remove("cm-highlight");
            }, 4000);

            return;
        }

        // stop after ~2 seconds: the question may have been removed
        if (tries >= 10) {
            clearInterval(timer);
            showFlash("error", "That question is no longer available.");
        }
    }, 200);
})();

/* =========================================================
   Open a question from Home (questions.php#question-ID):
   scroll to it and highlight it for a moment
   ========================================================= */

(function () {
    const match = window.location.hash.match(/^#question-(\d+)$/);

    if (!match) {
        return;
    }

    const targetId = "question-" + match[1];

    // highlight animation (no CSS file edit needed)
    const style = document.createElement("style");
    style.textContent =
        "@keyframes cmPulse {" +
        "0%{box-shadow:0 0 0 0 rgba(212,160,60,.65);}" +
        "70%{box-shadow:0 0 0 14px rgba(212,160,60,0);}" +
        "100%{box-shadow:0 0 0 0 rgba(212,160,60,0);}}" +
        ".question-card.cm-highlight{" +
        "border-color:#d4a03c !important;" +
        "animation:cmPulse 1.2s ease-out 3;}";
    document.head.appendChild(style);

    let tries = 0;

    const timer = setInterval(function () {
        const card = document.getElementById(targetId);
        tries++;

        if (card) {
            clearInterval(timer);

            card.scrollIntoView({ behavior: "smooth", block: "center" });
            card.classList.add("cm-highlight");

            setTimeout(function () {
                card.classList.remove("cm-highlight");
            }, 4000);

            return;
        }

        // stop after ~2 seconds: the question may have been removed
        if (tries >= 10) {
            clearInterval(timer);
            showFlash("error", "That question is no longer available.");
        }
    }, 200);
})();

/* =========================================================
   Question typed on Home ("Make a Move"):
   fill the question box here, then clear the saved draft
   ========================================================= */

(function () {
    const box = document.getElementById("question");

    if (!box) {
        return;
    }

    let draft = "";

    try {
        draft = sessionStorage.getItem("cm_question_draft") || "";
        sessionStorage.removeItem("cm_question_draft");
    } catch (error) {
        draft = "";
    }

    // only fill it when the box is empty (so nothing already typed is overwritten)
    if (draft !== "" && box.value.trim() === "") {
        box.value = draft;
        box.focus();
        box.setSelectionRange(box.value.length, box.value.length);
    }
})();