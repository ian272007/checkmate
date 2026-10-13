// ---------- 1. DATA ----------
// "reports", "csrfToken" and "uploadUrl" are created by report.php
// (the list comes straight from the database)

var PER_PAGE = 10;
var currentPage = 1;

// the report that is open in the popup
var viewingId = null;

// ids of reports that just arrived (they get a highlight)
var highlighted = {};


// ---------- 2. GET ELEMENTS ----------
var toast = document.getElementById("toast");
var tableBody = document.getElementById("tableBody");
var showingText = document.getElementById("showingText");
var pagination = document.getElementById("pagination");

var searchInput = document.getElementById("searchInput");
var reasonFilter = document.getElementById("reasonFilter");
var statusFilter = document.getElementById("statusFilter");

var viewModal = document.getElementById("viewModal");
var viewStatus = document.getElementById("viewStatus");
var saveViewButton = document.getElementById("saveView");


// ---------- 3. TOAST ----------
function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


// ---------- 4. SMALL HELPERS ----------

// so html inside a question or name can't break the page
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

// cut long text with ...
function shorten(text, max) {
    text = String(text == null ? "" : text);
    if (text.length > max) {
        return text.substring(0, max) + "...";
    }
    return text;
}

function findReport(id) {
    for (var i = 0; i < reports.length; i++) {
        if (reports[i].id === id) {
            return reports[i];
        }
    }
    return null;
}

// sends one request to report_api.php
function callApi(params, onDone) {

    var body = new URLSearchParams();
    for (var key in params) {
        body.append(key, params[key]);
    }
    body.append("csrf", csrfToken);

    fetch("report_api.php", { method: "POST", body: body })
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

// keeps the red number on the bell and the dot in the sidebar correct
function updatePendingBadge() {

    var pending = 0;
    for (var i = 0; i < reports.length; i++) {
        if (reports[i].status === "Pending") { pending++; }
    }

    var count = document.getElementById("notificationCount");
    var dot = document.getElementById("menuDot");

    count.textContent = pending;
    count.style.display = pending > 0 ? "" : "none";
    dot.style.display = pending > 0 ? "" : "none";

    return pending;
}


// ---------- 5. SHOW THE TABLE ----------
// returns the reports that match the search box and both filters
function getFiltered() {

    var searchText = searchInput.value.toLowerCase().trim();
    var reason = reasonFilter.value;
    var status = statusFilter.value;
    var result = [];

    for (var i = 0; i < reports.length; i++) {

        var r = reports[i];

        // check the search box (reason, question, reported student or reporter)
        var matchesSearch = true;
        if (searchText !== "") {
            matchesSearch = String(r.reason).toLowerCase().indexOf(searchText) !== -1 ||
                            String(r.question).toLowerCase().indexOf(searchText) !== -1 ||
                            String(r.student).toLowerCase().indexOf(searchText) !== -1 ||
                            String(r.reporter).toLowerCase().indexOf(searchText) !== -1;
        }

        var matchesReason = (reason === "all" || r.reason === reason);
        var matchesStatus = (status === "all" || r.status === status);

        if (matchesSearch && matchesReason && matchesStatus) {
            result.push(r);
        }
    }

    return result;
}

function showTable() {

    var result = getFiltered();

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

        html += "<tr" + (highlighted[r.id] ? " class='new-row'" : "") + ">";
        html += "<td class='cell-id'>" + formatId(r.id) + "</td>";
        html += "<td>" + makeSafe(r.reason) + "</td>";
        html += "<td class='title-cell' title=\"" + makeSafe(r.question) + "\">" + makeSafe(r.question) + "</td>";
        html += "<td>" + makeSafe(r.student) + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(r.reporter) + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(r.date) + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(r.status) + "</span></td>";
        html += "<td>";
        html += "<button class='action-btn' onclick='openView(" + r.id + ")'>View</button>";
        html += "<button class='action-btn delete' onclick='deleteReport(" + r.id + ")'>Delete</button>";
        html += "</td>";
        html += "</tr>";
    }

    if (result.length === 0) {
        html = "<tr class='empty-row'><td colspan='8'>No reports found. Try a different search or filter.</td></tr>";
        showingText.textContent = "Showing 0 of " + reports.length + " reports";
    } else {
        showingText.textContent = "Showing " + (from + 1) + "–" + to + " of " + result.length + " reports";
    }

    tableBody.innerHTML = html;

    buildPagination(result.length);
}

searchInput.addEventListener("input", function () {
    currentPage = 1;
    showTable();
});

reasonFilter.addEventListener("change", function () {
    currentPage = 1;
    showTable();
});

statusFilter.addEventListener("change", function () {
    currentPage = 1;
    showTable();
});


// ---------- 6. VIEW POPUP ----------
function openView(id) {

    var r = findReport(id);
    if (r === null) {
        return;
    }

    viewingId = id;

    document.getElementById("viewLabel").textContent = "REPORT " + formatId(r.id);
    document.getElementById("viewTitle").textContent = r.reason;
    document.getElementById("viewStudent").textContent = r.student;
    document.getElementById("viewReporter").textContent = r.reporter;
    document.getElementById("viewDate").textContent = r.date + ", " + r.time;
    document.getElementById("viewQuestionId").textContent = r.question_deleted ? "Deleted" : formatId(r.question_id);

    // the question that was reported
    document.getElementById("viewQuestion").textContent = r.question;

    var meta = [];
    if (r.subject) { meta.push(r.subject); }
    if (r.course)  { meta.push(r.course); }
    if (r.type)    { meta.push(r.type); }
    document.getElementById("viewQuestionMeta").textContent = meta.join("  ·  ");

    // picture or file attached to the question
    var attachBox = document.getElementById("viewAttachment");
    attachBox.innerHTML = "";

    if (r.attachment) {
        var fileUrl = uploadUrl + encodeURIComponent(r.attachment);

        if (/\.(jpe?g|png|gif|webp)$/i.test(r.attachment)) {
            attachBox.innerHTML = "<a href=\"" + makeSafe(fileUrl) + "\" target=\"_blank\" rel=\"noopener\">" +
                                  "<img class='attach-img' src=\"" + makeSafe(fileUrl) + "\" alt='Question attachment'></a>";
        } else {
            attachBox.innerHTML = "<a class='attach-link' href=\"" + makeSafe(fileUrl) + "\" target=\"_blank\" rel=\"noopener\">" +
                                  "Open attachment: " + makeSafe(r.attachment) + "</a>";
        }
    }

    // what the reporter wrote
    document.getElementById("viewDetails").textContent = r.details ? r.details : "No details were provided.";

    viewStatus.value = r.status;

    viewModal.classList.add("show");
}

document.getElementById("closeView").addEventListener("click", function () {
    viewModal.classList.remove("show");
});

// clicking the dark area outside the box also closes it
viewModal.addEventListener("click", function (event) {
    if (event.target === viewModal) {
        viewModal.classList.remove("show");
    }
});

saveViewButton.addEventListener("click", function () {

    var r = findReport(viewingId);
    if (r === null) {
        return;
    }

    // stop double clicks while waiting for PHP
    saveViewButton.disabled = true;

    callApi({ action: "status", id: viewingId, status: viewStatus.value }, function (data) {

        saveViewButton.disabled = false;

        if (!data.success) {
            showToast(data.message);
            return;
        }

        r.status = data.status;

        viewModal.classList.remove("show");

        updatePendingBadge();
        showTable();
        showToast(formatId(r.id) + " was updated.");
    });
});


// ---------- 7. DELETE ----------
function deleteReport(id) {

    var r = findReport(id);
    if (r === null) {
        return;
    }

    var sure = confirm("Delete report " + formatId(r.id) + "?\n\n" + r.reason +
                       "\n\nThis will also permanently delete the reported question, " +
                       "its answers, and all other reports about it.");

    if (sure === false) {
        return;
    }

    callApi({ action: "delete", id: id }, function (data) {

        if (!data.success) {
            showToast(data.message);
            return;
        }

        var deletedQuestionId = r.question_id;
        var newList = [];

        for (var i = 0; i < reports.length; i++) {
            var sameReport = reports[i].id === id;
            var sameQuestion = deletedQuestionId && reports[i].question_id === deletedQuestionId;

            if (!sameReport && !sameQuestion) {
                newList.push(reports[i]);
            }
        }
        reports = newList;

        updatePendingBadge();
        showTable();
        showToast("Report and question were deleted.");
    });
}


// ---------- 8. EXPORT ----------
// downloads the reports that are currently shown as a csv file
function csvSafe(text) {
    text = String(text == null ? "" : text);

    // stops Excel from running a cell that starts with = + - @
    if (/^[=+\-@]/.test(text)) {
        text = "'" + text;
    }

    return '"' + text.replace(/"/g, '""') + '"';
}

document.getElementById("exportButton").addEventListener("click", function () {

    var result = getFiltered();

    if (result.length === 0) {
        showToast("There are no reports to export.");
        return;
    }

    var csv = "ID,Reason,Question,Reported Student,Reported By,Date,Status,Details\n";

    for (var i = 0; i < result.length; i++) {
        var r = result[i];
        csv += [
            formatId(r.id),
            csvSafe(r.reason),
            csvSafe(r.question),
            csvSafe(r.student),
            csvSafe(r.reporter),
            csvSafe(r.date + " " + r.time),
            csvSafe(r.status),
            csvSafe(r.details)
        ].join(",") + "\n";
    }

    var blob = new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "checkmate_reports.csv";
    link.click();

    showToast("Reports exported.");
});


// ---------- 9. NOTIFICATION BUTTON ----------
document.getElementById("notificationButton").addEventListener("click", function () {
    var pending = updatePendingBadge();

    if (pending > 0) {
        showToast("You have " + pending + " pending report(s).");
    } else {
        showToast("No pending reports.");
    }
});


// ---------- 10. ACCOUNT MENU (3 dots) ----------
var accountButton = document.getElementById("accountMenuButton");
var accountMenu = document.getElementById("accountMenu");

accountButton.addEventListener("click", function (event) {
    event.stopPropagation();
    accountMenu.classList.toggle("show");
    accountButton.classList.toggle("open");
});

// clicking anywhere else (or pressing Esc) closes it
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


// ---------- 11. LIVE UPDATES (polling) ----------
var POLL_MS = 3000;          // 3 seconds
var polling = false;
var lastSignature = JSON.stringify(reports);
var lastReasons = "";

// rebuilds the "All Reasons" dropdown, but keeps the admin's choice
function refreshReasonFilter(list) {

    var joined = list.join("|");
    if (joined === lastReasons) {
        return;
    }
    lastReasons = joined;

    var current = reasonFilter.value;
    var html = "<option value='all'>All Reasons</option>";

    for (var i = 0; i < list.length; i++) {
        html += "<option value=\"" + makeSafe(list[i]) + "\">" + makeSafe(list[i]) + "</option>";
    }

    reasonFilter.innerHTML = html;
    reasonFilter.value = current;

    // the chosen reason no longer exists
    if (reasonFilter.value !== current) {
        reasonFilter.value = "all";
    }
}

function checkForUpdates() {

    // don't pile up requests, and don't ask while the tab is hidden
    if (polling || document.hidden) {
        return;
    }
    polling = true;

    fetch("report_api.php?action=list&_=" + Date.now(), { cache: "no-store" })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (!data.success) {
                console.log("[polling] failed:", data.message);
                return;
            }

            var signature = JSON.stringify(data.reports);
            if (signature === lastSignature) {
                return;   // nothing changed
            }
            lastSignature = signature;

            // find reports we did not have before
            var known = {};
            for (var i = 0; i < reports.length; i++) {
                known[reports[i].id] = true;
            }

            var newCount = 0;
            for (var j = 0; j < data.reports.length; j++) {
                var id = data.reports[j].id;
                if (!known[id]) {
                    highlighted[id] = true;
                    newCount++;
                    (function (reportId) {
                        setTimeout(function () {
                            delete highlighted[reportId];
                            showTable();
                        }, 6000);
                    })(id);
                }
            }

            reports = data.reports;

            refreshReasonFilter(data.reasons);
            updatePendingBadge();
            showTable();

            if (newCount > 0) {
                showToast(newCount === 1 ? "New report received!" : newCount + " new reports received!");
            }
        })
        .catch(function (err) {
            console.log("[polling] error:", err);
        })
        .finally(function () {
            polling = false;
        });
}

setInterval(checkForUpdates, POLL_MS);

// when the admin comes back to this tab, update immediately
document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
        checkForUpdates();
    }
});


// ---------- 12. START ----------
lastReasons = Array.prototype.map.call(reasonFilter.options, function (o) {
    return o.value;
}).filter(function (v) {
    return v !== "all";
}).join("|");

showTable();