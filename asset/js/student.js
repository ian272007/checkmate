// ---------- 1. GET ELEMENTS ----------
// the table rows are made by student.php (from the database),
// so this file only has to search, filter, and export
var toast = document.getElementById("toast");
var showingText = document.getElementById("showingText");
var noMatchRow = document.getElementById("noMatchRow");
var studentsBody = document.getElementById("studentsBody");

var searchInput = document.getElementById("searchInput");
var courseFilter = document.getElementById("courseFilter");
var yearFilter = document.getElementById("yearFilter");
var statusFilter = document.getElementById("statusFilter");

// every student row in the table
var rows = document.querySelectorAll("#studentsBody tr.student-row");


// ---------- 2. TOAST ----------
function showToast(message) {
    toast.textContent = message;
    toast.classList.add("show");

    setTimeout(function () {
        toast.classList.remove("show");
    }, 2500);
}


// ---------- 3. SEARCH, FILTERS AND PAGES ----------
var pagination = document.getElementById("pagination");

// how many students are shown on one page
var rowsPerPage = 10;

// the page we are on right now (starts at page 1)
var currentPage = 1;

// the rows that match the search and filters (all pages together)
var matchingRows = [];

// step 1: find the rows that match the search box and the filters
function applyFilters(keepPage) {

    var searchText = searchInput.value.toLowerCase().trim();
    var course = courseFilter.value;
    var year = yearFilter.value;
    var status = statusFilter.value;

    matchingRows = [];

    for (var i = 0; i < rows.length; i++) {

        var row = rows[i];

        // check the search box (name, student id or email)
        var matchesSearch = true;
        if (searchText !== "") {
            matchesSearch = row.dataset.name.toLowerCase().indexOf(searchText) !== -1 ||
                            row.dataset.studentid.toLowerCase().indexOf(searchText) !== -1 ||
                            row.dataset.email.toLowerCase().indexOf(searchText) !== -1;
        }

        var matchesCourse = (course === "all" || row.dataset.course === course);
        var matchesYear = (year === "all" || row.dataset.year === year);
        var matchesStatus = (status === "all" || row.dataset.status === status);

        // only keep the row if all 4 are true
        if (matchesSearch && matchesCourse && matchesYear && matchesStatus) {
            matchingRows.push(row);
        }
    }

    // a new search or filter goes back to page 1
    if (keepPage !== true) {
        currentPage = 1;
    }

    showPage();
}

// step 2: show only the 10 rows that belong on the current page
function showPage() {

    var total = matchingRows.length;

    var totalPages = Math.ceil(total / rowsPerPage);
    if (totalPages === 0) {
        totalPages = 1;
    }

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    var start = (currentPage - 1) * rowsPerPage;
    var end = start + rowsPerPage;

    // hide every row first
    for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = "none";
    }

    // then show only the matching rows for this page
    for (var j = start; j < end && j < total; j++) {
        matchingRows[j].style.display = "";
    }

    // show the "no students found" row when nothing matches
    if (noMatchRow) {
        if (total === 0) {
            noMatchRow.style.display = "";
        } else {
            noMatchRow.style.display = "none";
        }
    }

    // update the text under the table
    if (total === 0) {
        showingText.textContent = "Showing 0 of 0 students";
    } else {
        var lastShown = end;
        if (lastShown > total) {
            lastShown = total;
        }
        showingText.textContent = "Showing " + (start + 1) + "–" + lastShown + " of " + total + " students";
    }

    buildPagination(totalPages);
}

// step 3: make the page buttons ( ‹  1  2  3  › )
function buildPagination(totalPages) {

    pagination.innerHTML = "";

    var prevButton = document.createElement("button");
    prevButton.className = "page-btn";
    prevButton.textContent = "‹";
    prevButton.disabled = (currentPage === 1);
    prevButton.addEventListener("click", function () {
        currentPage--;
        showPage();
    });
    pagination.appendChild(prevButton);

    for (var p = 1; p <= totalPages; p++) {
        pagination.appendChild(makePageButton(p));
    }

    var nextButton = document.createElement("button");
    nextButton.className = "page-btn";
    nextButton.textContent = "›";
    nextButton.disabled = (currentPage === totalPages);
    nextButton.addEventListener("click", function () {
        currentPage++;
        showPage();
    });
    pagination.appendChild(nextButton);
}

// makes one numbered button
function makePageButton(pageNumber) {

    var button = document.createElement("button");
    button.className = "page-btn";
    button.textContent = pageNumber;

    if (pageNumber === currentPage) {
        button.className = "page-btn current";
    }

    button.addEventListener("click", function () {
        currentPage = pageNumber;
        showPage();
    });

    return button;
}

searchInput.addEventListener("input", function () { applyFilters(); });
courseFilter.addEventListener("change", function () { applyFilters(); });
yearFilter.addEventListener("change", function () { applyFilters(); });
statusFilter.addEventListener("change", function () { applyFilters(); });

// run it once when the page opens so only the first 10 students show
applyFilters();


// ---------- 4. EXPORT ----------
// downloads the students that match the search/filters (all pages) as a csv file
function csvSafe(text) {
    text = String(text == null ? "" : text);

    // stops Excel from running a cell that starts with = + - @
    if (/^[=+\-@]/.test(text)) {
        text = "'" + text;
    }

    return '"' + text.replace(/"/g, '""') + '"';
}

document.getElementById("exportButton").addEventListener("click", function () {

    // matchingRows already holds every student that passes the search and filters
    if (matchingRows.length === 0) {
        showToast("There are no students to export.");
        return;
    }

    var csv = "ID,Full Name,Student ID,Email,Course,Year Level\n";

    for (var i = 0; i < matchingRows.length; i++) {
        var d = matchingRows[i].dataset;
        csv += [
            csvSafe(d.id),
            csvSafe(d.name),
            csvSafe(d.studentid),
            csvSafe(d.email),
            csvSafe(d.course),
            csvSafe(d.year)
        ].join(",") + "\n";
    }

    var blob = new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" });
    var link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "checkmate_students.csv";
    link.click();

    showToast("Students exported.");
});


// ---------- 5. NOTIFICATION BUTTON ----------
document.getElementById("notificationButton").addEventListener("click", function () {

    // read the number from the red badge (there is no badge when it is 0)
    var badge = document.querySelector(".notification-count");
    var count = "0";

    if (badge) {
        count = badge.textContent.trim();
    }

    showToast("You have " + count + " pending reports.");
});


// ---------- 6. OTHER SIDEBAR LINKS ----------
// the pages that don't exist yet just show a message
var sidebarLinks = document.querySelectorAll(".sidebar-menu a");

for (var j = 0; j < sidebarLinks.length; j++) {
    sidebarLinks[j].addEventListener("click", function (event) {
        if (this.getAttribute("href") === "#") {
            event.preventDefault();
            showToast("That page is not built yet.");
        }
    });
}


// ---------- 7. ACCOUNT MENU (3 dots) ----------
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