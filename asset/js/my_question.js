const filterTabs = document.querySelectorAll(".filter-tab");
const myQuestionSearch = document.getElementById("searchMyQuestions");
const filterEmpty = document.getElementById("filterEmpty");
const myQuestionsList = document.getElementById("myQuestionsList");

let activeFilter = "all";


/* Always read the cards fresh, because deleted ones disappear while the page is open */

function getCards() {
    return document.querySelectorAll(".my-question");
}


/* Show or hide questions based on the tab and the search box */

function applyFilters() {
    const term = myQuestionSearch
        ? myQuestionSearch.value.toLowerCase().trim()
        : "";

    const cards = getCards();
    let visible = 0;

    cards.forEach(function (card) {
        const matchesTab =
            activeFilter === "all" || card.dataset.status === activeFilter;

        const matchesSearch =
            term === "" || card.textContent.toLowerCase().includes(term);

        const show = matchesTab && matchesSearch;

        card.hidden = !show;

        if (show) {
            visible++;
        }
    });

    if (filterEmpty) {
        // when there are no cards at all, the "no questions" box is shown instead
        filterEmpty.hidden = cards.length === 0 || visible !== 0;
    }
}

filterTabs.forEach(function (tab) {
    tab.addEventListener("click", function () {
        filterTabs.forEach(function (item) {
            item.classList.remove("active");
        });

        this.classList.add("active");
        activeFilter = this.dataset.filter;

        applyFilters();
    });
});

if (myQuestionSearch) {
    myQuestionSearch.addEventListener("input", applyFilters);
}


/* Open and close the answers under a question */

function toggleQuestion(header) {
    const card = header.closest(".my-question");
    const panel = document.getElementById(
        header.getAttribute("aria-controls")
    );

    if (!card || !panel) {
        return;
    }

    const willOpen = panel.hidden;

    panel.hidden = !willOpen;
    card.classList.toggle("open", willOpen);
    header.setAttribute("aria-expanded", String(willOpen));
}

document.querySelectorAll(".my-question-header").forEach(function (header) {

    header.addEventListener("click", function () {
        toggleQuestion(this);
    });

    header.addEventListener("keydown", function (event) {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            toggleQuestion(this);
        }
    });

});


/* =========================================================
   LIVE UPDATES
   Every few seconds, ask questions_live_api.php which questions
   still exist and remove the cards of deleted ones (no refresh).
   ========================================================= */

const POLL_MS = 3000;   // 3 seconds
let polling = false;

/* Updates the numbers after a card is removed */
function updateCounts() {
    const cards = getCards();
    const all = cards.length;

    let answered = 0;
    cards.forEach(function (card) {
        if (card.dataset.status === "answered") {
            answered++;
        }
    });

    const waiting = all - answered;
    const totalAnswers = document.querySelectorAll(".answer-item").length;

    const numbers = document.querySelectorAll(".my-summary .summary-card strong");
    const hints = document.querySelectorAll(".my-summary .summary-card .summary-hint");

    if (numbers.length >= 3) {
        numbers[0].textContent = all;
        numbers[1].textContent = answered;
        numbers[2].textContent = waiting;
    }

    if (hints.length >= 2) {
        hints[1].textContent =
            totalAnswers + " answer" + (totalAnswers === 1 ? "" : "s") + " received";
    }

    filterTabs.forEach(function (tab) {
        const filter = tab.dataset.filter;

        if (filter === "answered") {
            tab.textContent = "Answered (" + answered + ")";
        } else if (filter === "waiting") {
            tab.textContent = "Waiting (" + waiting + ")";
        } else {
            tab.textContent = "All (" + all + ")";
        }
    });
}

/* Shows the "nothing here" box when the last question is gone */
function showEmptyIfNeeded() {
    if (getCards().length > 0 || document.querySelector("[data-live-empty]")) {
        return;
    }

    const box = document.createElement("div");
    box.className = "no-questions";
    box.setAttribute("data-live-empty", "1");
    box.innerHTML =
        "<div>♟</div>" +
        "<h3>You haven't asked anything yet</h3>" +
        "<p>Post your first question and your peers will answer it here.</p>";

    myQuestionsList.appendChild(box);
}

function removeCard(card) {
    card.style.transition = "opacity 0.3s ease";
    card.style.opacity = "0";

    setTimeout(function () {
        card.remove();
        updateCounts();
        applyFilters();
        showEmptyIfNeeded();
    }, 300);
}

function checkForDeleted() {

    // don't pile up requests, and don't ask while the tab is hidden
    if (polling || document.hidden) {
        return;
    }
    polling = true;

    fetch("questions_live_api.php?_=" + Date.now(), { cache: "no-store" })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                return;
            }

            const alive = new Set(data.ids);

            getCards().forEach(function (card) {
                const id = parseInt(card.dataset.qid, 10);

                if (!alive.has(id)) {
                    removeCard(card);
                }
            });
        })
        .catch(function () {
            // ignore, try again on the next round
        })
        .finally(function () {
            polling = false;
        });
}

if (myQuestionsList) {
    setInterval(checkForDeleted, POLL_MS);

    // when the student comes back to this tab, check immediately
    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            checkForDeleted();
        }
    });
}