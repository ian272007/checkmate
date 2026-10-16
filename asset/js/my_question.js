const filterTabs = document.querySelectorAll(".filter-tab");
const myQuestionSearch = document.getElementById("searchMyQuestions");
const filterEmpty = document.getElementById("filterEmpty");
const myQuestionsList = document.getElementById("myQuestionsList");

let activeFilter = "all";


function getCards() {
    return document.querySelectorAll(".my-question");
}


function searchText(card) {
    const clone = card.cloneNode(true);

    clone.querySelectorAll(".mq-menu").forEach(function (el) {
        el.remove();
    });

    return clone.textContent.toLowerCase();
}


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
            term === "" || searchText(card).includes(term);

        const show = matchesTab && matchesSearch;

        card.hidden = !show;

        if (show) {
            visible++;
        }
    });

    if (filterEmpty) {
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


const POLL_MS = 3000;
let polling = false;

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
        })
        .finally(function () {
            polling = false;
        });
}

if (myQuestionsList) {
    setInterval(checkForDeleted, POLL_MS);

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            checkForDeleted();
        }
    });
}