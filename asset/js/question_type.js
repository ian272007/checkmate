var PER_PAGE = 10;
var currentPage = 1;


var toast = document.getElementById("toast");
var tableBody = document.getElementById("tableBody");
var showingText = document.getElementById("showingText");
var pagination = document.getElementById("pagination");

var searchInput = document.getElementById("searchInput");
var statusFilter = document.getElementById("statusFilter");

var formModal = document.getElementById("formModal");
var formError = document.getElementById("formError");
var formName = document.getElementById("formName");
var saveButton = document.getElementById("saveForm");


function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


function makeSafe(text) {
    text = String(text == null ? "" : text);
    text = text.replace(/&/g, "&amp;");
    text = text.replace(/</g, "&lt;");
    text = text.replace(/>/g, "&gt;");
    text = text.replace(/"/g, "&quot;");
    text = text.replace(/'/g, "&#39;");
    return text;
}

function formatId(number) {
    if (number >= 100) {
        return "#" + number;
    }
    return "#" + ("00" + number).slice(-3);
}

function findType(id) {
    for (var i = 0; i < types.length; i++) {
        if (types[i].id === id) {
            return types[i];
        }
    }
    return null;
}

function callApi(params, onDone) {

    var body = new URLSearchParams();
    for (var key in params) {
        body.append(key, params[key]);
    }
    body.append("csrf", csrfToken);

    fetch("question_type_api.php", { method: "POST", body: body })
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


function updateStats() {

    var active = 0;
    var archived = 0;

    for (var i = 0; i < types.length; i++) {
        if (types[i].status === "Active") { active++; }
        else if (types[i].status === "Archived") { archived++; }
    }

    document.getElementById("statTotal").textContent = types.length;
    document.getElementById("statActive").textContent = active;
    document.getElementById("statArchived").textContent = archived;
}

var statCards = document.querySelectorAll(".stat-card");
for (var s = 0; s < statCards.length; s++) {
    statCards[s].addEventListener("click", function () {
        statusFilter.value = this.getAttribute("data-jump");
        currentPage = 1;
        showTable();
    });
}


function showTable() {

    var searchText = searchInput.value.toLowerCase().trim();
    var status = statusFilter.value;
    var result = [];

    for (var i = 0; i < types.length; i++) {

        var t = types[i];

        var matchesSearch = (searchText === "" || t.name.toLowerCase().indexOf(searchText) !== -1);
        var matchesStatus = (status === "all" || t.status === status);

        if (matchesSearch && matchesStatus) {
            result.push(t);
        }
    }

    var pages = Math.max(1, Math.ceil(result.length / PER_PAGE));
    if (currentPage > pages) { currentPage = pages; }
    if (currentPage < 1) { currentPage = 1; }

    var from = (currentPage - 1) * PER_PAGE;
    var to = Math.min(from + PER_PAGE, result.length);

    var html = "";

    for (var j = from; j < to; j++) {

        var r = result[j];
        var statusClass = String(r.status).toLowerCase().replace(/[^a-z0-9]/g, "");

        html += "<tr" + ((r.status === "Archived") ? " class='is-archived'" : "") + ">";
        html += "<td class='cell-id'>" + formatId(r.id) + "</td>";
        html += "<td>" + makeSafe(r.name) + "</td>";
        html += "<td class='cell-muted'>" + r.used + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(r.status) + "</span></td>";
        html += "<td>";

        if (r.status === "Archived") {
            html += "<button class='action-btn restore' onclick='changeType(" + r.id + ", \"restore\")'>Restore</button>";
        } else {
            html += "<button class='action-btn archive' onclick='changeType(" + r.id + ", \"archive\")'>Archive</button>";
        }

        html += "</td>";
        html += "</tr>";
    }

    if (result.length === 0) {
        html = "<tr class='empty-row'><td colspan='5'>No question types found. Try a different search or filter.</td></tr>";
        showingText.textContent = "Showing 0 of " + types.length + " types";
    } else {
        showingText.textContent = "Showing " + (from + 1) + "–" + to + " of " + result.length + " types";
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


function changeType(id, action) {

    var t = findType(id);
    if (t === null) {
        return;
    }

    var dialog;

    if (action === "archive") {

        var archiveMessage = t.name;

        if (t.used > 0) {
            archiveMessage += "\n\n" + t.used + " question(s) already use this type.";
        }

        archiveMessage += "\n\nIt will no longer be available to students. You can restore it later.";

        dialog = {
            label: "ARCHIVE QUESTION TYPE",
            title: "Archive question type " + formatId(t.id) + "?",
            message: archiveMessage,
            confirmText: "Archive type",
            cancelText: "Cancel",
            tone: "warn"
        };

    } else {

        dialog = {
            label: "RESTORE QUESTION TYPE",
            title: "Restore question type " + formatId(t.id) + "?",
            message: t.name + "\n\nIt will be available to students again.",
            confirmText: "Restore type",
            cancelText: "Cancel",
            tone: "warn"
        };
    }

    cmConfirm(dialog).then(function (confirmed) {

        if (!confirmed) {
            return;
        }

        callApi({ action: action, id: id }, function (data) {

            if (!data.success) {
                showToast(data.message);
                return;
            }

            t.status = data.status;

            updateStats();
            showTable();

            if (action === "archive") {
                showToast("Question type " + formatId(id) + " was archived.");
            } else {
                showToast("Question type " + formatId(id) + " was restored.");
            }
        });
    });
}


function openForm() {

    formError.textContent = "";
    document.getElementById("modalTitle").textContent = "Add type";
    saveButton.textContent = "Add Type";
    formName.value = "";

    formModal.classList.add("show");
    formName.focus();
}

document.getElementById("addButton").addEventListener("click", function () {
    openForm();
});

document.getElementById("cancelForm").addEventListener("click", function () {
    formModal.classList.remove("show");
});

formModal.addEventListener("click", function (event) {
    if (event.target === formModal) {
        formModal.classList.remove("show");
    }
});

saveButton.addEventListener("click", function () {

    var name = formName.value.trim();

    if (name === "") {
        formError.textContent = "Please enter the type name.";
        return;
    }

    saveButton.disabled = true;

    callApi({ action: "add", name: name, status: "Active" }, function (data) {

        saveButton.disabled = false;

        if (!data.success) {
            formError.textContent = data.message;
            return;
        }

        types.push(data.item);
        showToast("Question type added.");

        formModal.classList.remove("show");

        updateStats();
        showTable();
    });
});


document.getElementById("notificationButton").addEventListener("click", function () {
    if (pendingReports > 0) {
        showToast("You have " + pendingReports + " pending report(s).");
    } else {
        showToast("No new notifications.");
    }
});


var sidebarLinks = document.querySelectorAll(".sidebar-menu a");

for (var i = 0; i < sidebarLinks.length; i++) {
    sidebarLinks[i].addEventListener("click", function (event) {
        if (this.getAttribute("href") === "#") {
            event.preventDefault();
            showToast("That page is not built yet.");
        }
    });
}


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


updateStats();
showTable();