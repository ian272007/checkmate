var toast = document.getElementById("toast");
var questionsBody = document.getElementById("questionsBody");
var showingText = document.getElementById("showingText");

var searchInput = document.getElementById("searchInput");
var categoryFilter = document.getElementById("categoryFilter");
var statusFilter = document.getElementById("statusFilter");


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
    var text = "00" + number;
    if (number >= 100) {
        text = "" + number;
    } else {
        text = text.slice(-3);
    }
    return "#" + text;
}

function shorten(text, max) {
    text = String(text == null ? "" : text);
    if (text.length > max) {
        return text.substring(0, max) + "...";
    }
    return text;
}


function showTable(list) {

    var html = "";

    for (var i = 0; i < list.length; i++) {

        var q = list[i];

        var statusClass = String(q.status || "").toLowerCase().replace(/[^a-z0-9]/g, "");

        var rowClass = (q.status === "Archived") ? " class='is-archived'" : "";

        html += "<tr" + rowClass + ">";
        html += "<td class='cell-id'>" + formatId(q.id) + "</td>";
        html += "<td class='title-cell' title=\"" + makeSafe(q.question) + "\">" + makeSafe(q.question) + "</td>";
        html += "<td>" + makeSafe(q.student) + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(q.subject || "-") + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(q.type || "-") + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(q.status || "-") + "</span></td>";
        html += "<td>";

        if (q.status === "Archived") {
            html += "<button class='action-btn restore' onclick='changeQuestion(" + q.id + ", \"restore\")'>Restore</button>";
        } else {
            html += "<button class='action-btn archive' onclick='changeQuestion(" + q.id + ", \"archive\")'>Archive</button>";
        }

        html += "</td>";
        html += "</tr>";
    }

    if (list.length === 0) {
        html = "<tr class='empty-row'><td colspan='7'>No questions found. Try a different search or filter.</td></tr>";
        showingText.textContent = "Showing 0 of " + questions.length + " questions";
    } else {
        showingText.textContent = "Showing 1–" + list.length + " of " + questions.length + " questions";
    }

    questionsBody.innerHTML = html;
}


function applyFilters() {

    var searchText = searchInput.value.toLowerCase().trim();
    var category = categoryFilter.value;
    var status = statusFilter.value;

    var result = [];

    for (var i = 0; i < questions.length; i++) {

        var q = questions[i];

        var matchesSearch = true;
        if (searchText !== "") {
            matchesSearch = String(q.question).toLowerCase().indexOf(searchText) !== -1 ||
                            String(q.student).toLowerCase().indexOf(searchText) !== -1;
        }

        var matchesCategory = (category === "all" || q.subject === category);
        var matchesStatus = (status === "all" || q.status === status);

        if (matchesSearch && matchesCategory && matchesStatus) {
            result.push(q);
        }
    }

    showTable(result);
}

searchInput.addEventListener("input", applyFilters);
categoryFilter.addEventListener("change", applyFilters);
statusFilter.addEventListener("change", applyFilters);


function findQuestion(id) {
    for (var i = 0; i < questions.length; i++) {
        if (questions[i].id === id) {
            return questions[i];
        }
    }
    return null;
}

function changeQuestion(id, action) {
    var q = findQuestion(id);
    if (q === null) {
        return;
    }

    var dialog;

    if (action === "archive") {
        dialog = {
            label: "ARCHIVE QUESTION",
            title: "Archive question " + formatId(q.id) + "?",
            message: shorten(q.question, 160) +
                     "\n\nStudents will no longer see it. You can restore it later.",
            confirmText: "Archive question",
            cancelText: "Cancel",
            tone: "warn"
        };
    } else {
        dialog = {
            label: "RESTORE QUESTION",
            title: "Restore question " + formatId(q.id) + "?",
            message: shorten(q.question, 160) +
                     "\n\nStudents will be able to see and answer it again.",
            confirmText: "Restore question",
            cancelText: "Cancel",
            tone: "warn"
        };
    }

    cmConfirm(dialog).then(function (confirmed) {

        if (!confirmed) {
            return;
        }

        var body = "id=" + encodeURIComponent(id) +
                   "&action=" + encodeURIComponent(action) +
                   "&csrf=" + encodeURIComponent(csrfToken);

        fetch("archive_question.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                showToast(data.message || "Could not update the question.");
                return;
            }

            q.status = data.status;

            applyFilters();

            if (action === "archive") {
                showToast("Question " + formatId(id) + " was archived.");
            } else {
                showToast("Question " + formatId(id) + " was restored.");
            }
        })
        .catch(function () {
            showToast("Something went wrong. Please try again.");
        });
    });
}


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


showTable(questions);