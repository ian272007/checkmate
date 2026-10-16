(function () {

    var POLL_MS = 3000;
    var busy = false;

    var myList = document.getElementById("myQuestionsList");
    var isMine = myList !== null;
    var list = isMine ? myList : document.getElementById("questionsList");

    if (list === null) {
        return;
    }

    function getCards() {
        if (isMine) {
            return list.querySelectorAll(".my-question[data-qid]");
        }
        return list.querySelectorAll(".question-card[id^='question-']");
    }

    function getCardId(card) {
        if (isMine) {
            return parseInt(card.getAttribute("data-qid"), 10);
        }
        return parseInt(card.id.replace("question-", ""), 10);
    }

    function updateCounts() {
        if (!isMine) {
            return;
        }

        var all = list.querySelectorAll(".my-question").length;
        var answered = list.querySelectorAll(".my-question[data-status='answered']").length;
        var waiting = all - answered;
        var totalAnswers = list.querySelectorAll(".answer-item").length;

        var numbers = document.querySelectorAll(".my-summary .summary-card strong");
        var hints = document.querySelectorAll(".my-summary .summary-card .summary-hint");

        if (numbers.length >= 3) {
            numbers[0].textContent = all;
            numbers[1].textContent = answered;
            numbers[2].textContent = waiting;
        }
        if (hints.length >= 2) {
            hints[1].textContent = totalAnswers + " answer" + (totalAnswers === 1 ? "" : "s") + " received";
        }

        var tabs = document.querySelectorAll(".filter-tab");
        for (var i = 0; i < tabs.length; i++) {
            var filter = tabs[i].getAttribute("data-filter");
            if (filter === "answered") {
                tabs[i].textContent = "Answered (" + answered + ")";
            } else if (filter === "waiting") {
                tabs[i].textContent = "Waiting (" + waiting + ")";
            } else {
                tabs[i].textContent = "All (" + all + ")";
            }
        }
    }

    function showEmptyIfNeeded() {
        if (getCards().length > 0 || list.querySelector("[data-live-empty]")) {
            return;
        }

        var box = document.createElement("div");
        box.className = "no-questions";
        box.setAttribute("data-live-empty", "1");
        box.innerHTML = isMine
            ? "<div>♟</div><h3>You haven't asked anything yet</h3><p>Post your first question and your peers will answer it here.</p>"
            : "<div>♟</div><h3>No questions from other students yet</h3><p>When other students post a question, it will show up here.</p>";
        list.appendChild(box);
    }

    function removeCard(card) {
        card.style.transition = "opacity 0.3s ease";
        card.style.opacity = "0";

        setTimeout(function () {
            if (card.parentNode) {
                card.parentNode.removeChild(card);
            }
            updateCounts();
            showEmptyIfNeeded();
        }, 300);
    }

    function checkForDeleted() {

        if (busy || document.hidden) {
            return;
        }
        busy = true;

        fetch("questions_live_api.php?_=" + Date.now(), { cache: "no-store" })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    return;
                }

                var alive = {};
                for (var i = 0; i < data.ids.length; i++) {
                    alive[data.ids[i]] = true;
                }

                var cards = getCards();
                for (var j = 0; j < cards.length; j++) {
                    if (!alive[getCardId(cards[j])]) {
                        removeCard(cards[j]);
                    }
                }
            })
            .catch(function () {
            })
            .finally(function () {
                busy = false;
            });
    }

    setInterval(checkForDeleted, POLL_MS);

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            checkForDeleted();
        }
    });

})();