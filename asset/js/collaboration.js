// ---------- 1. DATA ----------
// "requests" and "pendingReports" are created by collaboration.php
// (the list comes straight from the database)

// active collaborations = requests that were accepted
var activeList = [];
for (var n = 0; n < requests.length; n++) {
    if (requests[n].status === "Accepted") {
        activeList.push(requests[n]);
    }
}

var REQUESTS_PER_PAGE = 10;
var ACTIVE_PER_PAGE = 6;

var requestsPage = 1;
var activePage = 1;

// which tab is open: requests or active
var currentTab = "requests";

// extra filter when a summary card is clicked ("all" = no filter)
var statusFilter = "all";


// ---------- 2. GET ELEMENTS ----------
var toast = document.getElementById("toast");
var requestsBody = document.getElementById("requestsBody");
var showingText = document.getElementById("showingText");
var requestsPagination = document.getElementById("requestsPagination");
var requestsPanel = document.getElementById("requestsPanel");
var requestsSub = document.getElementById("requestsSub");
var activePanel = document.getElementById("activePanel");
var activeGrid = document.getElementById("activeGrid");
var activeShowing = document.getElementById("activeShowing");
var activePagination = document.getElementById("activePagination");


// ---------- 3. TOAST ----------
function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


// ---------- 4. SMALL HELPERS ----------

// so html inside a name or topic can't break the page
function makeSafe(text) {
    text = String(text == null ? "" : text);
    text = text.replace(/&/g, "&amp;");
    text = text.replace(/</g, "&lt;");
    text = text.replace(/>/g, "&gt;");
    text = text.replace(/"/g, "&quot;");
    text = text.replace(/'/g, "&#39;");
    return text;
}

// 1 becomes #CR-001
function formatId(number) {
    var text = "00" + number;
    if (number >= 100) {
        text = "" + number;
    } else {
        text = text.slice(-3);
    }
    return "#CR-" + text;
}

// first letters of the name for the round avatar (Juan Dela Cruz -> JD)
function initials(name) {
    var parts = String(name).trim().split(" ");
    var letters = parts[0].charAt(0);
    if (parts.length > 1) {
        letters += parts[parts.length - 1].charAt(0);
    }
    return letters.toUpperCase();
}

function personHtml(name, small) {
    var cls = small ? "avatar small" : "avatar";
    return "<div class='person'><span class='" + cls + "'>" + makeSafe(initials(name)) + "</span>" +
           "<span>" + makeSafe(name) + "</span></div>";
}

// cut long text with ...
function shorten(text, max) {
    text = String(text == null ? "" : text);
    if (text.length > max) {
        return text.substring(0, max) + "...";
    }
    return text;
}

// builds the ‹ 1 2 3 › buttons
function buildPagination(container, total, perPage, page, onChange) {

    var pages = Math.ceil(total / perPage);
    container.innerHTML = "";

    // nothing to page through
    if (pages <= 1) {
        return;
    }

    function addButton(label, targetPage, isCurrent, isDisabled) {
        var btn = document.createElement("button");
        btn.className = "page-btn" + (isCurrent ? " current" : "");
        btn.textContent = label;
        btn.disabled = isDisabled;
        btn.addEventListener("click", function () {
            onChange(targetPage);
        });
        container.appendChild(btn);
    }

    addButton("‹", page - 1, false, page === 1);

    // show at most 5 page numbers around the current page
    var start = Math.max(1, page - 2);
    var end = Math.min(pages, start + 4);
    start = Math.max(1, end - 4);

    for (var p = start; p <= end; p++) {
        addButton(String(p), p, p === page, false);
    }

    addButton("›", page + 1, false, page === pages);
}


// ---------- 5. SUMMARY CARDS ----------
function updateStats() {

    var pending = 0;

    for (var i = 0; i < requests.length; i++) {
        if (requests[i].status === "Pending") { pending++; }
    }

    document.getElementById("statTotal").textContent = requests.length;
    document.getElementById("statPending").textContent = pending;
    document.getElementById("statActive").textContent = activeList.length;
}


// ---------- 6. REQUESTS TABLE ----------
function showRequests() {

    var list = [];

    for (var i = 0; i < requests.length; i++) {
        if (statusFilter === "all" || requests[i].status === statusFilter) {
            list.push(requests[i]);
        }
    }

    // make sure the page number is valid
    var pages = Math.max(1, Math.ceil(list.length / REQUESTS_PER_PAGE));
    if (requestsPage > pages) { requestsPage = pages; }
    if (requestsPage < 1) { requestsPage = 1; }

    var from = (requestsPage - 1) * REQUESTS_PER_PAGE;
    var to = Math.min(from + REQUESTS_PER_PAGE, list.length);

    var html = "";

    for (var j = from; j < to; j++) {

        var q = list[j];
        var statusClass = String(q.status).toLowerCase().replace(/[^a-z0-9]/g, "");

        html += "<tr>";
        html += "<td class='cell-id'>" + formatId(q.id) + "</td>";
        html += "<td>" + personHtml(q.requester, false) + "</td>";
        html += "<td class='arrow-cell'>→</td>";
        html += "<td>" + personHtml(q.receiver, false) + "</td>";
        html += "<td class='arrow-cell'></td>";
        html += "<td class='cell-muted topic-cell' title=\"" + makeSafe(q.topic) + "\">" + makeSafe(q.topic) + "</td>";
        html += "<td>" + makeSafe(q.date) + "<span class='time-small'>" + makeSafe(q.time) + "</span></td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(q.status) + "</span></td>";
        html += "</tr>";
    }

    if (list.length === 0) {
        html = "<tr class='empty-row'><td colspan='8'>No collaboration requests found.</td></tr>";
        showingText.textContent = "Showing 0 of " + requests.length + " requests";
    } else {
        showingText.textContent = "Showing " + (from + 1) + "–" + to + " of " + list.length + " requests";
    }

    requestsBody.innerHTML = html;

    buildPagination(requestsPagination, list.length, REQUESTS_PER_PAGE, requestsPage, function (p) {
        requestsPage = p;
        showRequests();
    });
}


// ---------- 7. ACTIVE COLLABORATIONS ----------
function showActive() {

    var pages = Math.max(1, Math.ceil(activeList.length / ACTIVE_PER_PAGE));
    if (activePage > pages) { activePage = pages; }
    if (activePage < 1) { activePage = 1; }

    var from = (activePage - 1) * ACTIVE_PER_PAGE;
    var to = Math.min(from + ACTIVE_PER_PAGE, activeList.length);

    var html = "";

    for (var i = from; i < to; i++) {

        var c = activeList[i];

        var subjectText = c.subject ? c.subject : "-";
        if (c.course) {
            subjectText += " (" + c.course + ")";
        }

        html += "<div class='active-card'>";
        html += "<div>";
        html += "<div class='active-people'>" +
                "<span class='avatar small'>" + makeSafe(initials(c.requester)) + "</span>" + makeSafe(c.requester) +
                "<span class='swap'>⇄</span>" +
                "<span class='avatar small'>" + makeSafe(initials(c.receiver)) + "</span>" + makeSafe(c.receiver) +
                "</div>";
        html += "<div class='active-meta'><b>Topic:</b> " + makeSafe(shorten(c.topic, 60)) + "<br>" +
                "<b>Subject:</b> " + makeSafe(subjectText) + "</div>";
        html += "</div>";
        html += "<div class='active-right'>";
        html += "<span class='cell-muted' style='font-size:9px'>Started: " + makeSafe(c.date) + "</span>";
        html += "<span class='status active'>Active</span>";
        html += "</div>";
        html += "</div>";
    }

    if (activeList.length === 0) {
        html = "<div class='cell-muted' style='padding:20px 4px;font-size:11px;grid-column:1 / -1;text-align:center'>No active collaborations yet.</div>";
        activeShowing.textContent = "Showing 0 active collaborations";
    } else {
        activeShowing.textContent = "Showing " + (from + 1) + "–" + to + " of " + activeList.length + " active collaborations";
    }

    activeGrid.innerHTML = html;

    buildPagination(activePagination, activeList.length, ACTIVE_PER_PAGE, activePage, function (p) {
        activePage = p;
        showActive();
    });
}


// ---------- 8. TABS ----------
function setTab(name) {

    currentTab = name;

    var tabs = document.querySelectorAll(".tab");
    for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].getAttribute("data-tab") === name) {
            tabs[i].classList.add("active");
        } else {
            tabs[i].classList.remove("active");
        }
    }

    // requests tab = both sections, active tab = only active
    if (name === "active") {
        requestsPanel.classList.add("hidden");
        activePanel.classList.remove("hidden");
    } else {
        requestsPanel.classList.remove("hidden");
        activePanel.classList.remove("hidden");
    }

    if (statusFilter === "all") {
        requestsSub.textContent = "All collaboration requests sent between students.";
    } else {
        requestsSub.textContent = "Showing " + statusFilter.toLowerCase() + " requests only.";
    }

    showRequests();
}

var tabButtons = document.querySelectorAll(".tab");
for (var t = 0; t < tabButtons.length; t++) {
    tabButtons[t].addEventListener("click", function () {
        statusFilter = "all";
        requestsPage = 1;
        setTab(this.getAttribute("data-tab"));
    });
}

// clicking a summary card opens the matching view
var statCards = document.querySelectorAll(".stat-card");
for (var s = 0; s < statCards.length; s++) {
    statCards[s].addEventListener("click", function () {
        var jump = this.getAttribute("data-jump");

        if (jump === "active") {
            statusFilter = "all";
            setTab("active");
        } else {
            statusFilter = jump;       // "all" or "Pending"
            requestsPage = 1;
            setTab("requests");
        }
    });
}


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
showRequests();
showActive();