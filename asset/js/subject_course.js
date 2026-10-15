// ---------- 1. DATA ----------
// "courses", "subjects", "csrfToken" and "pendingReports" are created by subject_course.php
// (the lists come straight from the database)

var PER_PAGE = 10;
var currentPage = 1;

// which toggle is open: courses or subjects
var currentTab = "courses";

// when editing, this holds the id being edited (null = adding new)
var editingId = null;


// ---------- 2. GET ELEMENTS ----------
var toast = document.getElementById("toast");
var tableHead = document.getElementById("tableHead");
var tableBody = document.getElementById("tableBody");
var showingText = document.getElementById("showingText");
var pagination = document.getElementById("pagination");

var searchInput = document.getElementById("searchInput");
var statusFilter = document.getElementById("statusFilter");
var addButton = document.getElementById("addButton");

var formModal = document.getElementById("formModal");
var formError = document.getElementById("formError");
var formName = document.getElementById("formName");
var saveButton = document.getElementById("saveForm");


// ---------- 3. TOAST ----------
function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


// ---------- 4. SMALL HELPERS ----------

// so html inside a name can't break the page
function makeSafe(text) {
    text = String(text == null ? "" : text);
    text = text.replace(/&/g, "&amp;");
    text = text.replace(/</g, "&lt;");
    text = text.replace(/>/g, "&gt;");
    text = text.replace(/"/g, "&quot;");
    text = text.replace(/'/g, "&#39;");
    return text;
}

// 1 becomes #001
function formatId(number) {
    if (number >= 100) {
        return "#" + number;
    }
    return "#" + ("00" + number).slice(-3);
}

// the list for the toggle that is open
function currentList() {
    return currentTab === "courses" ? courses : subjects;
}

// "course" or "subject" (what the PHP file expects)
function itemWord() {
    return currentTab === "courses" ? "course" : "subject";
}

function capital(text) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function findItem(id) {
    var list = currentList();
    for (var i = 0; i < list.length; i++) {
        if (list[i].id === id) {
            return list[i];
        }
    }
    return null;
}

// sends one request to subject_course_api.php
function callApi(params, onDone) {

    var body = new URLSearchParams();
    for (var key in params) {
        body.append(key, params[key]);
    }
    body.append("csrf", csrfToken);

    fetch("subject_course_api.php", { method: "POST", body: body })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            onDone(data);
        })
        .catch(function () {
            onDone({ success: false, message: "Something went wrong. Please try again." });
        });
}

// builds the ‹ 1 2 3 › buttons
function buildPagination(total) {

    var pages = Math.ceil(total / PER_PAGE);
    pagination.innerHTML = "";

    if (pages <= 1) {
        return;
    }

    function addPageButton(label, targetPage, isCurrent, isDisabled) {
        var btn = document.createElement("button");
        btn.className = "page-btn" + (isCurrent ? " current" : "");
        btn.textContent = label;
        btn.disabled = isDisabled;
        btn.addEventListener("click", function () {
            currentPage = targetPage;
            showTable();
        });
        pagination.appendChild(btn);
    }

    addPageButton("‹", currentPage - 1, false, currentPage === 1);

    var start = Math.max(1, currentPage - 2);
    var end = Math.min(pages, start + 4);
    start = Math.max(1, end - 4);

    for (var p = start; p <= end; p++) {
        addPageButton(String(p), p, p === currentPage, false);
    }

    addPageButton("›", currentPage + 1, false, currentPage === pages);
}


// ---------- 5. SUMMARY CARDS ----------
function updateStats() {

    var active = 0;
    var archived = 0;
    var all = courses.concat(subjects);

    for (var i = 0; i < all.length; i++) {
        if (all[i].status === "Active") { active++; }
        else if (all[i].status === "Archived") { archived++; }
    }

    document.getElementById("statCourses").textContent = courses.length;
    document.getElementById("statSubjects").textContent = subjects.length;
    document.getElementById("statActive").textContent = active;
    document.getElementById("statArchived").textContent = archived;
}


// ---------- 6. SHOW THE TABLE ----------
function showTable() {

    var list = currentList();
    var searchText = searchInput.value.toLowerCase().trim();
    var status = statusFilter.value;
    var result = [];

    // search + status filter
    for (var i = 0; i < list.length; i++) {

        var item = list[i];

        var matchesSearch = (searchText === "" || item.name.toLowerCase().indexOf(searchText) !== -1);
        var matchesStatus = (status === "all" || item.status === status);

        if (matchesSearch && matchesStatus) {
            result.push(item);
        }
    }

    // make sure the page number is valid
    var pages = Math.max(1, Math.ceil(result.length / PER_PAGE));
    if (currentPage > pages) { currentPage = pages; }
    if (currentPage < 1) { currentPage = 1; }

    var from = (currentPage - 1) * PER_PAGE;
    var to = Math.min(from + PER_PAGE, result.length);

    var word = itemWord();

    tableHead.innerHTML = "<tr><th>ID</th><th>" + capital(word) + " Name</th><th>Questions</th><th>Status</th><th>Actions</th></tr>";

    var html = "";

    for (var j = from; j < to; j++) {

        var r = result[j];
        var statusClass = String(r.status).toLowerCase().replace(/[^a-z0-9]/g, "");

        // archived rows look a little faded
        html += "<tr" + ((r.status === "Archived") ? " class='is-archived'" : "") + ">";
        html += "<td class='cell-id'>" + formatId(r.id) + "</td>";
        html += "<td>" + makeSafe(r.name) + "</td>";
        html += "<td class='cell-muted'>" + r.used + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(r.status) + "</span></td>";
        html += "<td>";

        // Active -> Archive button.  Archived -> Restore button.
        if (r.status === "Archived") {
            html += "<button class='action-btn restore' onclick='changeItem(" + r.id + ", \"restore\")'>Restore</button>";
        } else {
            html += "<button class='action-btn archive' onclick='changeItem(" + r.id + ", \"archive\")'>Archive</button>";
        }

        html += "</td>";
        html += "</tr>";
    }

    if (result.length === 0) {
        html = "<tr class='empty-row'><td colspan='5'>No " + word + "s found. Try a different search or filter.</td></tr>";
        showingText.textContent = "Showing 0 of " + list.length + " " + word + "s";
    } else {
        showingText.textContent = "Showing " + (from + 1) + "–" + to + " of " + result.length + " " + word + "s";
    }

    tableBody.innerHTML = html;

    buildPagination(result.length);
}

searchInput.addEventListener("input", function () {
    currentPage = 1;
    showTable();
});

statusFilter.addEventListener("change", function () {
    currentPage = 1;
    showTable();
});


// ---------- 7. TOGGLE (COURSES / SUBJECTS) ----------
function setTab(name) {

    currentTab = name;
    currentPage = 1;

    var buttons = document.querySelectorAll(".toggle-btn");
    for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute("data-tab") === name) {
            buttons[i].classList.add("active");
        } else {
            buttons[i].classList.remove("active");
        }
    }

    var word = itemWord();
    searchInput.placeholder = "Search " + word + "s...";
    addButton.textContent = "+ Add " + capital(word);

    searchInput.value = "";
    statusFilter.value = "all";

    showTable();
}

var toggleButtons = document.querySelectorAll(".toggle-btn");
for (var t = 0; t < toggleButtons.length; t++) {
    toggleButtons[t].addEventListener("click", function () {
        setTab(this.getAttribute("data-tab"));
    });
}

// clicking a summary card opens the matching view
var statCards = document.querySelectorAll(".stat-card");
for (var s = 0; s < statCards.length; s++) {
    statCards[s].addEventListener("click", function () {
        var jump = this.getAttribute("data-jump");

        if (jump === "courses" || jump === "subjects") {
            setTab(jump);
        } else {
            // Active / Archived: filter the table that is open
            statusFilter.value = jump;
            currentPage = 1;
            showTable();
        }
    });
}


// ---------- 8. ARCHIVE / RESTORE ----------
// action is "archive" or "restore"
// asks first with the styled dialog from cm_confirm.js
function changeItem(id, action) {

    var item = findItem(id);
    if (item === null) {
        return;
    }

    var word = itemWord();
    var dialog;

    if (action === "archive") {

        var archiveMessage = item.name;

        if (item.used > 0) {
            archiveMessage += "\n\n" + item.used + " question(s) already use this " + word + ".";
        }

        archiveMessage += "\n\nIt will no longer be available to students. You can restore it later.";

        dialog = {
            label: "ARCHIVE " + word.toUpperCase(),
            title: "Archive " + word + " " + formatId(item.id) + "?",
            message: archiveMessage,
            confirmText: "Archive " + word,
            cancelText: "Cancel",
            tone: "warn"
        };

    } else {

        dialog = {
            label: "RESTORE " + word.toUpperCase(),
            title: "Restore " + word + " " + formatId(item.id) + "?",
            message: item.name + "\n\nIt will be available to students again.",
            confirmText: "Restore " + word,
            cancelText: "Cancel",
            tone: "warn"
        };
    }

    cmConfirm(dialog).then(function (confirmed) {

        if (!confirmed) {
            return;
        }

        callApi({ action: action, type: word, id: id }, function (data) {

            if (!data.success) {
                showToast(data.message);
                return;
            }

            // update the list on the page too
            item.status = data.status;

            updateStats();
            showTable();

            if (action === "archive") {
                showToast(capital(word) + " " + formatId(id) + " was archived.");
            } else {
                showToast(capital(word) + " " + formatId(id) + " was restored.");
            }
        });
    });
}


// ---------- 9. ADD POPUP ----------
// openForm() with no id = add new, openForm(id) = edit that one
function openForm(id) {

    var word = itemWord();

    editingId = (id === undefined) ? null : id;
    formError.textContent = "";

    document.getElementById("modalLabel").textContent = word.toUpperCase() + "S";
    document.getElementById("nameLabel").textContent = capital(word) + " name";
    formName.placeholder = (currentTab === "courses") ? "BSIT" : "Web Development";

    if (editingId === null) {
        document.getElementById("modalTitle").textContent = "Add " + word;
        saveButton.textContent = "Add " + capital(word);
        formName.value = "";
    } else {
        var item = findItem(editingId);
        document.getElementById("modalTitle").textContent = "Edit " + word;
        saveButton.textContent = "Save Changes";
        formName.value = item.name;
    }

    formModal.classList.add("show");
    formName.focus();
}

addButton.addEventListener("click", function () {
    openForm();
});

document.getElementById("cancelForm").addEventListener("click", function () {
    formModal.classList.remove("show");
});

// clicking the dark area outside the box also closes it
formModal.addEventListener("click", function (event) {
    if (event.target === formModal) {
        formModal.classList.remove("show");
    }
});

saveButton.addEventListener("click", function () {

    var word = itemWord();
    var name = formName.value.trim();
    if (name === "") {
        formError.textContent = "Please enter the " + word + " name.";
        return;
    }

    // new items are always Active
    var params = { type: word, name: name, status: "Active" };

    if (editingId === null) {
        params.action = "add";
    } else {
        params.action = "update";
        params.id = editingId;
    }

    // stop double clicks while waiting for PHP
    saveButton.disabled = true;

    callApi(params, function (data) {

        saveButton.disabled = false;

        if (!data.success) {
            formError.textContent = data.message;
            return;
        }

        if (editingId === null) {
            currentList().push(data.item);
            showToast(capital(word) + " added.");
        } else {
            var item = findItem(editingId);
            item.name = data.item.name;
            item.status = data.item.status;
            item.used = data.item.used;
            showToast(capital(word) + " " + formatId(editingId) + " was updated.");
        }

        formModal.classList.remove("show");

        updateStats();
        showTable();
    });
});


// ---------- 10. NOTIFICATION BUTTON ----------
document.getElementById("notificationButton").addEventListener("click", function () {
    if (pendingReports > 0) {
        showToast("You have " + pendingReports + " pending report(s).");
    } else {
        showToast("No new notifications.");
    }
});


// ---------- 11. OTHER SIDEBAR LINKS ----------
var sidebarLinks = document.querySelectorAll(".sidebar-menu a");

for (var i = 0; i < sidebarLinks.length; i++) {
    sidebarLinks[i].addEventListener("click", function (event) {
        if (this.getAttribute("href") === "#") {
            event.preventDefault();
            showToast("That page is not built yet.");
        }
    });
}


// ---------- 12. ACCOUNT MENU (3 dots) ----------
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


// ---------- 13. START ----------
updateStats();
showTable();