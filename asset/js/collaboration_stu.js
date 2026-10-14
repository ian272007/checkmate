/* =========================================================
   Collaboration page
   - filter tabs + search over your requests
   - expand / collapse details
   - Find a peer: search students, send and cancel requests
   - Accept / Reject / Cancel without reloading the page
   - live refresh for BOTH students (checks every 2 seconds)
   - clears the sidebar notification badge while this page is open
   ========================================================= */

(() => {
    "use strict";

    const cfg = window.CHECKMATE_COLLAB || {
        url: "collaboration_stu.php",
        csrf: ""
    };

    const POLL_MS = 2000;

    const $ = id => document.getElementById(id);

    const collabList = $("collabList");
    const collabSearch = $("searchCollaborations");
    const tabsBox = document.querySelector(".filter-tabs");
    const summaryBox = document.querySelector(".my-summary");
    const flashBox = $("collabFlash");

    let collabFilter = "all";
    let refreshing = false;
    let refreshQueued = false;
    let checking = false;
    let flashTimer;

    function esc(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[char]);
    }

    function initials(name) {
        const parts = String(name || "?").trim().split(/\s+/).filter(Boolean);

        if (!parts.length) return "?";

        return (
            parts[0].charAt(0) +
            (parts.length > 1 ? parts[parts.length - 1].charAt(0) : "")
        ).toUpperCase();
    }

    function flash(message, ok = true) {
        if (!flashBox) return;

        flashBox.textContent = message;
        flashBox.className =
            "form-message " +
            (ok ? "form-message-success" : "form-message-error");
        flashBox.hidden = false;

        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => {
            flashBox.hidden = true;
        }, 4000);
    }

    /* POST helper: always asks for JSON back */
    async function postAction(fields) {
        const response = await fetch(cfg.url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
            },
            body: new URLSearchParams({
                ...fields,
                csrf: cfg.csrf,
                ajax: "1"
            }).toString()
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.ok) {
            throw new Error(data.error || "Something went wrong. Please try again.");
        }

        return data;
    }

    /* Clear the sidebar badge once the student is looking at this page */
    async function markSeen() {
        if (document.hidden) return;

        try {
            await postAction({ action: "mark_seen" });
        } catch {
            /* not important */
        }
    }


    /* ---------- Filter tabs and search ---------- */

    function syncTabs() {
        document.querySelectorAll(".filter-tab").forEach(tab => {
            tab.classList.toggle("active", tab.dataset.filter === collabFilter);
        });
    }

    function applyCollabFilters() {
        const term = collabSearch
            ? collabSearch.value.toLowerCase().trim()
            : "";

        let visible = 0;

        document.querySelectorAll("#collabList .my-question").forEach(card => {
            const groups = (card.dataset.groups || "").split(" ");

            const matchesTab =
                collabFilter === "all" || groups.includes(collabFilter);

            const matchesSearch =
                term === "" || card.textContent.toLowerCase().includes(term);

            const show = matchesTab && matchesSearch;

            card.hidden = !show;

            if (show) visible++;
        });

        const empty = $("filterEmpty");

        if (empty) empty.hidden = visible !== 0;
    }

    if (tabsBox) {
        tabsBox.addEventListener("click", event => {
            const tab = event.target.closest(".filter-tab");

            if (!tab) return;

            collabFilter = tab.dataset.filter;
            syncTabs();
            applyCollabFilters();
        });
    }

    if (collabSearch) {
        collabSearch.addEventListener("input", applyCollabFilters);
    }


    /* ---------- Open and close the details of a request ---------- */

    function toggleCollab(header, forceOpen) {
        const card = header.closest(".my-question");
        const panel = document.getElementById(
            header.getAttribute("aria-controls")
        );

        if (!card || !panel) return;

        const willOpen = typeof forceOpen === "boolean"
            ? forceOpen
            : panel.hidden;

        panel.hidden = !willOpen;
        card.classList.toggle("open", willOpen);
        header.setAttribute("aria-expanded", String(willOpen));
    }

    if (collabList) {
        collabList.addEventListener("click", event => {
            const header = event.target.closest(".my-question-header");

            if (!header || !collabList.contains(header)) return;

            // Accept / Reject / Cancel buttons must not open or close the row
            if (event.target.closest("button, a, form")) return;

            toggleCollab(header);
        });

        collabList.addEventListener("keydown", event => {
            const header = event.target.closest(".my-question-header");

            if (!header || event.target !== header) return;

            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                toggleCollab(header);
            }
        });

        /* Accept / Reject / Cancel: no page reload */
        collabList.addEventListener("submit", async event => {
            const form = event.target.closest(".collab-form");

            if (!form) return;

            event.preventDefault();

            const question = form.dataset.confirm;

            if (question && !window.confirm(question)) return;

            const buttons = form
                .closest(".collab-actions")
                .querySelectorAll("button");

            buttons.forEach(button => { button.disabled = true; });

            try {
                const data = await postAction({
                    action: form.elements.action.value,
                    collaboration_id: form.elements.collaboration_id.value
                });

                flash(data.message || "Done.");
            } catch (error) {
                flash(error.message, false);
            }

            // Success or not, show what the server now says
            await refreshList();
        });
    }


    /* ---------- Live refresh ---------- */

    async function refreshList() {
        if (!collabList) return;

        // A refresh is already running: run once more right after it
        if (refreshing) {
            refreshQueued = true;
            return;
        }

        refreshing = true;

        try {
            const response = await fetch(cfg.url, {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "text/html" }
            });

            if (!response.ok) return;

            const doc = new DOMParser().parseFromString(
                await response.text(),
                "text/html"
            );

            const newList = doc.getElementById("collabList");

            if (!newList) return;

            const openIds = Array.from(
                document.querySelectorAll(
                    "#collabList .my-question.open .my-question-header"
                )
            ).map(header => header.getAttribute("aria-controls"));

            collabList.innerHTML = newList.innerHTML;
            collabList.dataset.signature = newList.dataset.signature || "";

            const newSummary = doc.querySelector(".my-summary");

            if (newSummary && summaryBox) {
                summaryBox.innerHTML = newSummary.innerHTML;
            }

            const newTabs = doc.querySelector(".filter-tabs");

            if (newTabs && tabsBox) {
                tabsBox.innerHTML = newTabs.innerHTML;
            }

            openIds.forEach(id => {
                const header = collabList.querySelector(
                    `.my-question-header[aria-controls="${id}"]`
                );

                if (header) toggleCollab(header, true);
            });

            syncTabs();
            applyCollabFilters();

            // Keep the "Find a peer" list in sync (Pending / Collaborating tags)
            loadStudents();

            // A new request or acceptance arrived while this page is open
            markSeen();
        } catch {
            /* try again on the next check */
        } finally {
            refreshing = false;

            if (refreshQueued) {
                refreshQueued = false;
                refreshList();
            }
        }
    }

    async function checkForChanges() {
        if (checking || !collabList) return;

        checking = true;

        try {
            const response = await fetch(`${cfg.url}?action=summary`, {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "application/json" }
            });

            const data = await response.json();

            if (!data.ok) return;

            if (data.signature !== (collabList.dataset.signature || "")) {
                await refreshList();
            }
        } catch {
            /* ignore network hiccups */
        } finally {
            checking = false;
        }
    }

    // Keeps running even when the tab is in the background
    window.setInterval(checkForChanges, POLL_MS);

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) {
            checkForChanges();
            markSeen();
        }
    });

    window.addEventListener("focus", () => {
        checkForChanges();
        markSeen();
    });


    /* ---------- Find a peer ---------- */

    const findQuestion = $("findQuestion");
    const findSearch = $("findSearch");
    const findResults = $("findResults");
    const findAlert = $("findAlert");

    let searchTimer;
    let searchToken = 0;

    function showFindAlert(message = "", success = false) {
        if (!findAlert) return;

        findAlert.textContent = message;
        findAlert.hidden = !message;
        findAlert.classList.toggle("success", Boolean(message && success));
    }

    function statusTag(status) {
        if (status === "pending") {
            return '<span class="find-tag pending">Request pending</span>';
        }

        if (status === "accepted") {
            return '<span class="find-tag accepted">Collaborating</span>';
        }

        if (status === "rejected") {
            return '<span class="find-tag rejected">Declined</span>';
        }

        return "";
    }

    function renderStudents(users) {
        if (!users.length) {
            findResults.innerHTML =
                '<div class="find-note">No students found.</div>';
            return;
        }

        findResults.innerHTML = users.map(student => {
            const meta = [
                student.course,
                student.year_level,
                student.student_id
            ].filter(Boolean).join(" · ");

            let action;

            if (!student.status) {
                action = `<button type="button" class="find-send"
                                  data-receiver="${Number(student.id)}">
                              Send request
                          </button>`;
            } else if (student.status === "pending" && student.cancel_id) {
                action = `${statusTag("pending")}
                          <button type="button" class="find-cancel"
                                  data-collab="${Number(student.cancel_id)}">
                              Cancel
                          </button>`;
            } else {
                action = statusTag(student.status);
            }

            return `
                <div class="find-row">
                    <div class="find-avatar">${esc(initials(student.name))}</div>

                    <div class="find-info">
                        <strong>${esc(student.name)}</strong>
                        <span>${esc(meta)}</span>
                    </div>

                    <div class="find-actions">${action}</div>
                </div>
            `;
        }).join("");
    }

    async function loadStudents() {
        if (!findQuestion || !findResults) return;

        const token = ++searchToken;

        const params = new URLSearchParams({
            action: "search",
            q: findSearch.value.trim(),
            question_id: findQuestion.value
        });

        try {
            const response = await fetch(`${cfg.url}?${params}`, {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "application/json" }
            });

            const data = await response.json();

            if (token !== searchToken) return;

            if (!response.ok || !data.ok) {
                throw new Error(data.error || "Unable to search students.");
            }

            // Keep the scroll position when the list is refreshed live
            const scrollTop = findResults.scrollTop;

            renderStudents(data.users || []);

            findResults.scrollTop = scrollTop;
        } catch (error) {
            if (token !== searchToken) return;

            findResults.innerHTML =
                `<div class="find-note">${esc(error.message)}</div>`;
        }
    }

    if (findQuestion && findSearch && findResults) {
        findSearch.addEventListener("input", () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadStudents, 250);
        });

        findQuestion.addEventListener("change", () => {
            showFindAlert("");
            loadStudents();
        });

        findResults.addEventListener("click", async event => {
            const sendButton = event.target.closest(".find-send");
            const cancelButton = event.target.closest(".find-cancel");

            /* ----- Send request ----- */
            if (sendButton) {
                sendButton.disabled = true;
                sendButton.textContent = "Sending...";

                try {
                    const data = await postAction({
                        action: "send",
                        question_id: findQuestion.value,
                        receiver_id: sendButton.dataset.receiver
                    });

                    showFindAlert(data.message || "Request sent.", true);
                } catch (error) {
                    showFindAlert(error.message);
                }

                await refreshList();
                return;
            }

            /* ----- Cancel request ----- */
            if (cancelButton) {
                cancelButton.disabled = true;
                cancelButton.textContent = "Cancelling...";

                try {
                    const data = await postAction({
                        action: "cancel",
                        collaboration_id: cancelButton.dataset.collab
                    });

                    showFindAlert(data.message || "Request cancelled.", true);
                } catch (error) {
                    showFindAlert(error.message);
                }

                await refreshList();
            }
        });

        loadStudents();
    }

    markSeen();
    applyCollabFilters();
})();