// ---------- 1. GET ELEMENTS ----------
// the table rows are made by student.php (from the database),
// so this file only has to search and filter them
var toast = document.getElementById("toast");
var showingText = document.getElementById("showingText");
var noMatchRow = document.getElementById("noMatchRow");

var searchInput = document.getElementById("searchInput");
var courseFilter = document.getElementById("courseFilter");
var yearFilter = document.getElementById("yearFilter");

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

// step 1: find the rows that match the search box and the two filters
function applyFilters() {

    var searchText = searchInput.value.toLowerCase().trim();
    var course = courseFilter.value;
    var year = yearFilter.value;

    // start with an empty list
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

        // check the course filter
        var matchesCourse = (course === "all" || row.dataset.course === course);

        // check the year level filter
        var matchesYear = (year === "all" || row.dataset.year === year);

        // only keep the row if all 3 are true
        if (matchesSearch && matchesCourse && matchesYear) {
            matchingRows.push(row);
        }
    }

    // a new search or filter always goes back to page 1
    currentPage = 1;
    showPage();
}

// step 2: show only the 10 rows that belong on the current page
function showPage() {

    var total = matchingRows.length;

    // how many pages we need (at least 1, even when there are no students)
    var totalPages = Math.ceil(total / rowsPerPage);
    if (totalPages === 0) {
        totalPages = 1;
    }

    // safety: never go past the last page
    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    // example: page 2 starts at index 10 and stops before index 20
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

    // update the text under the table (example: Showing 11–20 of 25 students)
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

    // clear the old buttons first
    pagination.innerHTML = "";

    // previous button (turned off on page 1)
    var prevButton = document.createElement("button");
    prevButton.className = "page-btn";
    prevButton.textContent = "‹";
    prevButton.disabled = (currentPage === 1);
    prevButton.addEventListener("click", function () {
        currentPage--;
        showPage();
    });
    pagination.appendChild(prevButton);

    // one button for each page number
    // TODO: show "..." when there are a lot of pages
    for (var p = 1; p <= totalPages; p++) {
        pagination.appendChild(makePageButton(p));
    }

    // next button (turned off on the last page)
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

// makes one numbered button (it is in its own function so every button remembers its own number)
function makePageButton(pageNumber) {

    var button = document.createElement("button");
    button.className = "page-btn";
    button.textContent = pageNumber;

    // the page we are on gets the gold look
    if (pageNumber === currentPage) {
        button.className = "page-btn current";
    }

    button.addEventListener("click", function () {
        currentPage = pageNumber;
        showPage();
    });

    return button;
}

searchInput.addEventListener("input", applyFilters);
courseFilter.addEventListener("change", applyFilters);
yearFilter.addEventListener("change", applyFilters);

// run it once when the page opens so only the first 10 students show
applyFilters();


// ---------- 4. VIEW AND EDIT BUTTONS ----------
// the name and student id come from the data-* attributes of the row
for (var i = 0; i < rows.length; i++) {

    var viewButton = rows[i].querySelector(".view-btn");
    var editButton = rows[i].querySelector(".edit-btn");

    viewButton.addEventListener("click", function () {
        var row = this.closest("tr");
        // TODO: open a real student profile page
        showToast("Viewing " + row.dataset.name + " (" + row.dataset.studentid + ")");
    });

    editButton.addEventListener("click", function () {
        var row = this.closest("tr");
        // TODO: open an edit form
        showToast("Editing " + row.dataset.name + "...");
    });
}


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