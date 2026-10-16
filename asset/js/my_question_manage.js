(function () {
    const list = document.getElementById("myQuestionsList");

    if (!list) {
        return;
    }

    const API = "question_manage_api.php";
    const csrf = list.dataset.csrf || "";

    const editModal = document.getElementById("editModal");
    const editForm = document.getElementById("editForm");
    const editQuestion = document.getElementById("editQuestion");
    const editSubject = document.getElementById("editSubject");
    const editCourse = document.getElementById("editCourse");
    const editType = document.getElementById("editType");
    const editFile = document.getElementById("editFile");
    const editFileName = document.getElementById("editFileName");
    const editCurrent = document.getElementById("editCurrent");
    const editCurrentLink = document.getElementById("editCurrentLink");
    const editRemove = document.getElementById("editRemove");
    const editError = document.getElementById("editError");
    const editSave = document.getElementById("editSave");

    const deleteModal = document.getElementById("deleteModal");
    const deleteText = document.getElementById("deleteText");
    const deleteConfirm = document.getElementById("deleteConfirm");
    const deleteError = document.getElementById("deleteError");

    let activeCard = null;


    function toast(type, message) {
        let box = document.getElementById("mqToasts");

        if (!box) {
            box = document.createElement("div");
            box.id = "mqToasts";
            box.setAttribute("role", "status");
            box.setAttribute("aria-live", "polite");
            document.body.appendChild(box);
        }

        const item = document.createElement("div");
        item.className = "mq-toast" + (type === "error" ? " error" : "");
        item.textContent = message;
        box.appendChild(item);

        setTimeout(function () {
            item.classList.add("hide");
            setTimeout(function () {
                item.remove();
            }, 250);
        }, 4000);
    }


    function openModal(modal) {
        modal.hidden = false;
        document.body.classList.add("mq-modal-open");
    }

    function closeModal(modal) {
        modal.hidden = true;

        if (
            (!editModal || editModal.hidden) &&
            (!deleteModal || deleteModal.hidden)
        ) {
            document.body.classList.remove("mq-modal-open");
        }
    }

    [editModal, deleteModal].forEach(function (modal) {
        if (!modal) {
            return;
        }

        modal.addEventListener("click", function (event) {
            if (event.target.closest("[data-close]")) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            if (editModal && !editModal.hidden) {
                closeModal(editModal);
            }
            if (deleteModal && !deleteModal.hidden) {
                closeModal(deleteModal);
            }
        }
    });


    function uploadUrl(name) {
        return "../../uploads/questions/" + encodeURIComponent(name);
    }

    function setSelect(select, value) {
        const exists = Array.prototype.some.call(select.options, function (opt) {
            return opt.value === value;
        });

        if (value && !exists) {
            const opt = document.createElement("option");
            opt.value = value;
            opt.textContent = value;
            select.appendChild(opt);
        }

        select.value = value || "";
    }

    function showError(box, message) {
        box.textContent = message;
        box.hidden = false;
    }

    function renderAttachment(card, name) {
        const slot = card.querySelector(".attachment-slot");

        if (!slot) {
            return;
        }

        slot.innerHTML = "";

        if (name) {
            const link = document.createElement("a");
            link.className = "attachment-link";
            link.href = uploadUrl(name);
            link.target = "_blank";
            link.rel = "noopener";
            link.textContent = "View your attachment ↗";
            slot.appendChild(link);
        }
    }


    function openEdit(card) {
        activeCard = card;

        editQuestion.value = card.dataset.question || "";
        setSelect(editSubject, card.dataset.subject || "");
        setSelect(editCourse, card.dataset.course || "");
        setSelect(editType, card.dataset.type || "");

        editFile.value = "";
        editFileName.textContent = "Replace file (optional)";
        editRemove.checked = false;
        editError.hidden = true;

        const attachment = card.dataset.attachment || "";

        if (attachment) {
            editCurrent.hidden = false;
            editCurrentLink.href = uploadUrl(attachment);
        } else {
            editCurrent.hidden = true;
        }

        editSave.disabled = false;
        editSave.textContent = "Save changes";

        openModal(editModal);
        editQuestion.focus();
    }

    editFile.addEventListener("change", function () {
        editFileName.textContent =
            this.files.length > 0 ? this.files[0].name : "Replace file (optional)";

        if (this.files.length > 0) {
            editRemove.checked = false;
        }
    });

    editRemove.addEventListener("change", function () {
        if (this.checked) {
            editFile.value = "";
            editFileName.textContent = "Replace file (optional)";
        }
    });

    editForm.addEventListener("submit", function (event) {
        event.preventDefault();

        if (!activeCard) {
            return;
        }

        editError.hidden = true;

        if (editQuestion.value.trim() === "") {
            showError(editError, "Please enter your question.");
            return;
        }

        const data = new FormData(editForm);
        data.append("action", "edit");
        data.append("csrf", csrf);
        data.append("question_id", activeCard.dataset.qid);
        data.set("remove_attachment", editRemove.checked ? "1" : "0");

        editSave.disabled = true;
        editSave.textContent = "Saving...";

        fetch(API, { method: "POST", body: data })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    showError(editError, result.message);
                    editSave.disabled = false;
                    editSave.textContent = "Save changes";
                    return;
                }

                applyEdit(activeCard, result);
                closeModal(editModal);
                toast("success", result.message);
            })
            .catch(function () {
                showError(editError, "Something went wrong. Please try again.");
                editSave.disabled = false;
                editSave.textContent = "Save changes";
            });
    });

    function applyEdit(card, result) {
        card.dataset.question = result.question;
        card.dataset.subject = result.subject;
        card.dataset.course = result.course;
        card.dataset.type = result.type;
        card.dataset.attachment = result.attachment || "";

        const title = card.querySelector(".question-details h3");
        if (title) {
            title.textContent = result.question;
        }

        const tags = card.querySelectorAll(".question-tags span");
        if (tags.length >= 3) {
            tags[0].textContent = result.subject;
            tags[1].textContent = result.course;
            tags[2].textContent = result.type;
        }

        renderAttachment(card, result.attachment);

        if (typeof applyFilters === "function") {
            applyFilters();
        }
    }


    function openDelete(card) {
        activeCard = card;

        const answers = card.querySelectorAll(".answer-item").length;

        deleteText.textContent =
            answers > 0
                ? "This question has " + answers + " answer" +
                  (answers === 1 ? "" : "s") +
                  ". Deleting it will permanently remove the question and all of its answers."
                : "This will permanently remove your question.";

        deleteError.hidden = true;
        deleteConfirm.disabled = false;
        deleteConfirm.textContent = "Delete question";

        openModal(deleteModal);
    }

    deleteConfirm.addEventListener("click", function () {
        if (!activeCard) {
            return;
        }

        const card = activeCard;
        const data = new URLSearchParams();
        data.append("action", "delete");
        data.append("csrf", csrf);
        data.append("question_id", card.dataset.qid);

        deleteConfirm.disabled = true;
        deleteConfirm.textContent = "Deleting...";

        fetch(API, { method: "POST", body: data })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (!result.success) {
                    showError(deleteError, result.message);
                    deleteConfirm.disabled = false;
                    deleteConfirm.textContent = "Delete question";
                    return;
                }

                closeModal(deleteModal);
                toast("success", result.message);

                removeCard(card);
            })
            .catch(function () {
                showError(deleteError, "Something went wrong. Please try again.");
                deleteConfirm.disabled = false;
                deleteConfirm.textContent = "Delete question";
            });
    });


    let openMenu = null;

    function closeMenu() {
        if (openMenu) {
            openMenu.dropdown.hidden = true;
            openMenu.button.setAttribute("aria-expanded", "false");
            openMenu = null;
        }
    }

    function placeMenu(button, dropdown) {
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

    list.addEventListener("click", function (event) {
        const dots = event.target.closest(".mq-dots");

        if (!dots) {
            return;
        }

        event.stopPropagation();

        const dropdown = dots.closest(".mq-menu").querySelector(".mq-dropdown");

        if (openMenu && openMenu.button === dots) {
            closeMenu();
            return;
        }

        closeMenu();
        placeMenu(dots, dropdown);
        dots.setAttribute("aria-expanded", "true");
        openMenu = { button: dots, dropdown: dropdown };
    });

    document.addEventListener("click", function (event) {
        if (openMenu && !openMenu.dropdown.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });

    window.addEventListener("scroll", closeMenu, true);
    window.addEventListener("resize", closeMenu);


    list.addEventListener("click", function (event) {
        const edit = event.target.closest(".mq-edit");
        const del = event.target.closest(".mq-delete");

        if (!edit && !del) {
            return;
        }

        const card = event.target.closest(".my-question");

        if (!card) {
            return;
        }

        closeMenu();

        if (edit) {
            if (edit.disabled || card.dataset.status === "answered") {
                toast("error", "Answered questions can no longer be edited.");
                return;
            }
            openEdit(card);
        } else {
            openDelete(card);
        }
    });
})();