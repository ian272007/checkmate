const searchInput = document.getElementById("searchQuestions");

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const search = this.value.toLowerCase();
        const questions = document.querySelectorAll(".question-card");

        questions.forEach(function (question) {
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

    form.addEventListener("submit", function () {
        if (submit) {
            submit.disabled = true;
            submit.textContent = "Posting...";
        }
    });
});


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

    if (window.history && window.history.replaceState) {
        window.history.replaceState(
            null,
            "",
            window.location.pathname + window.location.hash
        );
    }
}


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

const reportModal = document.getElementById("reportModal");
const reportForm = document.getElementById("reportForm");
const reportQuestionId = document.getElementById("reportQuestionId");
const reportDescription = document.getElementById("reportDescription");
const reportImage = document.getElementById("reportImage");
const reportImagePick = document.getElementById("reportImagePick");
const reportImageName = document.getElementById("reportImageName");
const reportPreviewBox = document.getElementById("reportPreviewBox");
const reportPreview = document.getElementById("reportPreview");
const reportImageRemove = document.getElementById("reportImageRemove");
const reportError = document.getElementById("reportError");
const reportCancel = document.getElementById("reportCancel");
const reportSubmit = document.getElementById("reportSubmit");

let reportingCard = null;

function clearReportImage() {
    if (!reportImage) {
        return;
    }

    reportImage.value = "";
    reportImageName.textContent = "No image selected";
    reportPreviewBox.hidden = true;

    if (reportPreview.src) {
        URL.revokeObjectURL(reportPreview.src);
        reportPreview.removeAttribute("src");
    }
}

function showReportError(message) {
    reportError.textContent = message;
    reportError.hidden = false;
}

function openReportModal(card) {
    reportingCard = card;
    reportQuestionId.value = card.id.replace("question-", "");
    reportDescription.value = "";
    reportError.hidden = true;
    clearReportImage();

    reportSubmit.disabled = false;
    reportSubmit.textContent = "Send Report →";

    reportModal.hidden = false;
    reportDescription.focus();
}

function closeReportModal() {
    if (!reportModal) {
        return;
    }

    reportModal.hidden = true;
    reportingCard = null;
    clearReportImage();
}

if (reportModal) {

    reportImagePick.addEventListener("click", function () {
        reportImage.click();
    });

    reportImage.addEventListener("change", function () {
        reportError.hidden = true;

        if (this.files.length === 0) {
            clearReportImage();
            return;
        }

        const file = this.files[0];
        const okTypes = ["image/jpeg", "image/png", "image/webp"];

        if (okTypes.indexOf(file.type) === -1) {
            clearReportImage();
            showReportError("Only JPG, PNG or WEBP images are allowed.");
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            clearReportImage();
            showReportError("Image must be 5 MB or smaller.");
            return;
        }

        reportImageName.textContent = file.name;
        reportPreview.src = URL.createObjectURL(file);
        reportPreviewBox.hidden = false;
    });

    reportImageRemove.addEventListener("click", clearReportImage);
    reportCancel.addEventListener("click", closeReportModal);

    reportModal.addEventListener("click", function (event) {
        if (event.target === reportModal) {
            closeReportModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !reportModal.hidden) {
            closeReportModal();
        }
    });

    reportForm.addEventListener("submit", function (event) {
        event.preventDefault();

        const description = reportDescription.value.trim();

        if (description === "") {
            showReportError("Please write a description.");
            return;
        }

        reportError.hidden = true;
        reportSubmit.disabled = true;
        reportSubmit.textContent = "Sending...";

        fetch("report_question_api.php", {
            method: "POST",
            body: new FormData(reportForm)
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success || data.already) {
                    if (reportingCard) {
                        markReported(reportingCard);
                    }
                    closeReportModal();
                    showFlash(data.success ? "success" : "error", data.message);
                    return;
                }

                showReportError(data.message);
                reportSubmit.disabled = false;
                reportSubmit.textContent = "Send Report →";
            })
            .catch(function () {
                showReportError("Something went wrong. Please try again.");
                reportSubmit.disabled = false;
                reportSubmit.textContent = "Send Report →";
            });
    });
}

if (questionsList) {

    questionsList.addEventListener("click", function (event) {

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

        const option = event.target.closest(".report-option");

        if (!option) {
            return;
        }

        const card = option.closest(".question-card");

        closeReportMenu();
        openReportModal(card);
    });
}

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


(function () {
    const match = window.location.hash.match(/^#question-(\d+)$/);

    if (!match) {
        return;
    }

    const targetId = "question-" + match[1];

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

        if (tries >= 10) {
            clearInterval(timer);
            showFlash("error", "That question is no longer available.");
        }
    }, 200);
})();


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

    if (draft !== "" && box.value.trim() === "") {
        box.value = draft;
        box.focus();
    }
})();