// ---------- 1. DATA ----------
// "questions", "csrfToken" and "pendingReports" are created by question_ad.php
// (the list comes straight from the database)


// ---------- 2. GET ELEMENTS ----------
var toast = document.getElementById("toast");
var questionsBody = document.getElementById("questionsBody");
var showingText = document.getElementById("showingText");

var searchInput = document.getElementById("searchInput");
var categoryFilter = document.getElementById("categoryFilter");
var statusFilter = document.getElementById("statusFilter");


// ---------- 3. TOAST ----------
function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


// ---------- 4. MAKE TEXT SAFE ----------
// so html inside a question can't break the page
function makeSafe(text) {
    text = String(text == null ? "" : text);
    text = text.replace(/&/g, "&amp;");
    text = text.replace(/</g, "&lt;");
    text = text.replace(/>/g, "&gt;");
    text = text.replace(/"/g, "&quot;");
    text = text.replace(/'/g, "&#39;");
    return text;
}


// ---------- 5. MAKE THE ID LOOK LIKE #001 ----------
function formatId(number) {
    var text = "00" + number;
    if (number >= 100) {
        text = "" + number;
    } else {
        text = text.slice(-3);
    }
    return "#" + text;
}


// ---------- 6. SHOW THE TABLE ----------
function showTable(list) {

    var html = "";

    for (var i = 0; i < list.length; i++) {

        var q = list[i];

        // class name for the badge color (active, pending, ...)
        var statusClass = String(q.status || "").toLowerCase().replace(/[^a-z0-9]/g, "");

        html += "<tr>";
        html += "<td class='cell-id'>" + formatId(q.id) + "</td>";
        html += "<td class='title-cell' title=\"" + makeSafe(q.question) + "\">" + makeSafe(q.question) + "</td>";
        html += "<td>" + makeSafe(q.student) + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(q.subject || "-") + "</td>";
        html += "<td class='cell-muted'>" + makeSafe(q.type || "-") + "</td>";
        html += "<td><span class='status " + statusClass + "'>" + makeSafe(q.status || "-") + "</span></td>";
        html += "<td>";
        html += "<button class='action-btn delete' onclick='deleteQuestion(" + q.id + ")'>Delete</button>";
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


// ---------- 7. SEARCH AND FILTERS ----------
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


// ---------- 8. DELETE BUTTON ----------
function findQuestion(id) {
    for (var i = 0; i < questions.length; i++) {
        if (questions[i].id === id) {
            return questions[i];
        }
    }
    return null;
}

function deleteQuestion(id) {
    var q = findQuestion(id);
    if (q === null) {
        return;
    }

    // ask first so nothing gets deleted by accident
    var sure = confirm(
        "Delete question " + formatId(q.id) + "?\n\n" + q.question +
        "\n\nThis also deletes its answers and collaboration requests."
    );

    if (sure === false) {
        return;
    }

    // tell PHP to delete it from the database
    var body = "id=" + encodeURIComponent(id) + "&csrf=" + encodeURIComponent(csrfToken);

    fetch("delete_question.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body
    })
    .then(function (response) {
        return response.json();
    })
    .then(function (data) {
        if (!data.success) {
            showToast(data.message || "Could not delete the question.");
            return;
        }

        // remove it from the list on the page too
        var newList = [];
        for (var i = 0; i < questions.length; i++) {
            if (questions[i].id !== id) {
                newList.push(questions[i]);
            }
        }
        questions = newList;

        applyFilters();
        showToast("Question " + formatId(id) + " was deleted.");
    })
    .catch(function () {
        showToast("Something went wrong. Please try again.");
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
showTable(questions);