// ---------- 1. DATA ----------
// "types", "csrfToken" and "pendingReports" are created by question_type.php
// (the list comes straight from the database)

var PER_PAGE = 10;
var currentPage = 1;

// when editing, this holds the id being edited (null = adding new)
var editingId = null;


// ---------- 2. GET ELEMENTS ----------
var toast = document.getElementById("toast");
var tableBody = document.getElementById("tableBody");
var showingText = document.getElementById("showingText");
var pagination = document.getElementById("pagination");

var searchInput = document.getElementById("searchInput");
var statusFilter = document.getElementById("statusFilter");

var formModal = document.getElementById("formModal");
var formError = document.getElementById("formError");
var formName = document.getElementById("formName");
var formStatus = document.getElementById("formStatus");
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

function findType(id) {
    for (var i = 0; i < types.length; i++) {
        if (types[i].id === id) {
            return types[i];
        }
    }
    return null;
}

// sends one request to question_type_api.php
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
    var inactive = 0;

    for (var i = 0; i < types.length; i++) {
        if (types[i].status === "Active") { active++; }
        else { inactive++; }
    }

    document.getElementById("statTotal").textContent = types.length;
    document.getElementById("statActive").textContent = active;
    document.getElementById("statInactive").textContent = inactive;
}

// clicking a summary card sets the status filter
var statCards = document.querySelectorAll(".stat-card");
for (var s = 0; s < statCards.length; s++) {
    statCards[s].addEventListener("click", function () {
        statusFilter.value = this.getAttribute("data-jump");
        currentPage = 1;
        showTable();
    });
}


// ---------- 6. SHOW THE TABLE ----------
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

    // make sure the page number is valid
    var pages = Math.max(1, Math.ceil(result.length / PER_PAGE));
    if (currentPage > pages) { currentPage = pages; }
    if (currentPage < 1) { currentPage = 1; }

    var from = (currentPage - 1) * PER_PAGE;
    var to = Math.min(from + PER_PAGE, result.length);

    var html = "";

    for (var j = from; j < to; j++) {

        var r = result[j];
        var statusClass = String(r.status).toLowerCase().replace(/[^a-z0-9]/g, "");

        html += "<tr>";
        html += "<td class='cell-id'>" + formatId(r.id) + "</td>";
        html += "<td>" + makeSafe(r.name) + "</td>";
        html += "<td class='cell-muted'>" + r.used + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(r.status) + "</span></td>";
        html += "<td>";
        html += "<button class='action-btn' onclick='openForm(" + r.id + ")'>Edit</button>";
        html += "<button class='action-btn delete' onclick='deleteType(" + r.id + ")'>Delete</button>";
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


// ---------- 7. DELETE ----------
function deleteType(id) {

    var t = findType(id);
    if (t === null) {
        return;
    }

    var message = "Delete question type " + formatId(t.id) + "?\n\n" + t.name;

    if (t.used > 0) {
        message += "\n\n" + t.used + " question(s) already use this type. " +
                   "They will keep the name, but it will no longer be in the list.\n" +
                   "Tip: set it to Inactive instead if you only want to hide it.";
    }

    if (confirm(message) === false) {
        return;
    }

    callApi({ action: "delete", id: id }, function (data) {

        if (!data.success) {
            showToast(data.message);
            return;
        }

        var newList = [];
        for (var i = 0; i < types.length; i++) {
            if (types[i].id !== id) {
                newList.push(types[i]);
            }
        }
        types = newList;

        updateStats();
        showTable();
        showToast(formatId(id) + " was deleted.");
    });
}


// ---------- 8. ADD / EDIT POPUP ----------
// openForm() with no id = add new, openForm(id) = edit that one
function openForm(id) {

    editingId = (id === undefined) ? null : id;
    formError.textContent = "";

    if (editingId === null) {
        document.getElementById("modalTitle").textContent = "Add type";
        saveButton.textContent = "Add Type";
        formName.value = "";
        formStatus.value = "Active";
    } else {
        var t = findType(editingId);
        document.getElementById("modalTitle").textContent = "Edit type";
        saveButton.textContent = "Save Changes";
        formName.value = t.name;
        formStatus.value = t.status;
    }

    formModal.classList.add("show");
    formName.focus();
}

document.getElementById("addButton").addEventListener("click", function () {
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

    var name = formName.value.trim();
    var status = formStatus.value;

    if (name === "") {
        formError.textContent = "Please enter the type name.";
        return;
    }

    var params = { name: name, status: status };

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
            types.push(data.item);
            showToast("Question type added.");
        } else {
            var t = findType(editingId);
            t.name = data.item.name;
            t.status = data.item.status;
            t.used = data.item.used;
            showToast(formatId(editingId) + " was updated.");
        }

        formModal.classList.remove("show");

        updateStats();
        showTable();
    });
});


// ---------- 9. NOTIFICATION BUTTON ----------
document.getElementById("notificationButton").addEventListener("click", function () {
    if (pendingReports > 0) {
        showToast("You have " + pendingReports + " pending report(s).");
    } else {
        showToast("No new notifications.");
    }
});


// ---------- 10. OTHER SIDEBAR LINKS ----------
var sidebarLinks = document.querySelectorAll(".sidebar-menu a");

for (var i = 0; i < sidebarLinks.length; i++) {
    sidebarLinks[i].addEventListener("click", function (event) {
        if (this.getAttribute("href") === "#") {
            event.preventDefault();
            showToast("That page is not built yet.");
        }
    });
}


// ---------- 11. ACCOUNT MENU (3 dots) ----------
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


// ---------- 12. START ----------
updateStats();
showTable();