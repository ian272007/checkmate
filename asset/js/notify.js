/* CHECKMATE - notifications (shared by all student pages)
   - new messages: badge on "Messages", toast, sound
   - new answers to your questions: badge on "My Questions", toast, sound
   - new collaboration requests / accepted requests: badge on "Collaboration", toast, sound
   - warnings from an admin: full-screen popup, shown live while the student is online
     (instant through Server-Sent Events, with polling as a backup)
   - total unread count in the browser tab title
   Needs: messages_api.php (action=unread), notifications_api.php
   (action=answers, action=collab), warnings_api.php (action=check)
   and warnings_stream.php in view/student/ */

(() => {
    "use strict";

    if (window.__checkmateNotify) return;
    window.__checkmateNotify = true;

    const MESSAGES_URL = "messages_api.php";
    const ANSWERS_URL = "notifications_api.php";
    const WARNINGS_URL = "warnings_api.php";
    const WARNINGS_STREAM_URL = "warnings_stream.php";
    const POLL_MS = 4000;
    const BASE_TITLE = document.title;

    let audioCtx = null;
    let unreadMessages = 0;
    let unseenAnswers = 0;
    let unseenCollab = 0;
    let warningOpen = false;

    /* ---------- styles (injected, so no CSS file needs editing) ---------- */

    const style = document.createElement("style");
    style.textContent = `
        .cm-nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            margin-left: auto;
            padding: 0 5px;
            border-radius: 9px;
            background: #d4a657;
            color: #17130c;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }
        .cm-nav-badge[hidden] { display: none !important; }

        #cmToasts {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 340px;
            max-width: calc(100vw - 32px);
            pointer-events: none;
        }
        #cmToasts .cm-toast {
            all: unset;
            box-sizing: border-box;
            position: relative;
            display: flex !important;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            padding: 14px 38px 16px 14px;
            overflow: hidden;
            border: 1px solid #3a3022;
            border-radius: 10px;
            background: #14110c;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .55);
            color: #e7e0d5;
            font-family: Arial, Helvetica, sans-serif;
            cursor: pointer;
            pointer-events: auto;
            animation: cmToastIn .28s cubic-bezier(.2, .8, .2, 1);
        }
        #cmToasts .cm-toast:hover { border-color: #71582d; background: #1b1610; }
        #cmToasts .cm-toast.cm-out { animation: cmToastOut .2s ease forwards; }

        #cmToasts .cm-toast-icon {
            all: unset;
            box-sizing: border-box;
            display: flex;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid #71582d;
            border-radius: 50%;
            background: #292217;
            color: #d4a657;
            font-size: 16px;
            line-height: 1;
        }
        #cmToasts .cm-toast-body {
            all: unset;
            display: block;
            flex: 1;
            min-width: 0;
        }
        #cmToasts .cm-toast-title {
            all: unset;
            display: block;
            margin: 0 0 3px;
            color: #f1e6d0;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }
        #cmToasts .cm-toast-text {
            all: unset;
            display: block;
            overflow: hidden;
            color: #a9a296;
            font-size: 12px;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #cmToasts .cm-toast-close {
            all: unset;
            box-sizing: border-box;
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            color: #8d867a;
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
        }
        #cmToasts .cm-toast-close:hover { background: #292217; color: #d4a657; }
        #cmToasts .cm-toast-bar {
            all: unset;
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            display: block;
            height: 3px;
            background: #d4a657;
            transform-origin: left;
            animation: cmToastBar 7s linear forwards;
        }
        #cmToasts .cm-toast:hover .cm-toast-bar { animation-play-state: paused; }

        @keyframes cmToastIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes cmToastOut {
            to { opacity: 0; transform: translateX(24px); }
        }
        @keyframes cmToastBar {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }

        /* ---------- warning popup (yellow = caution) ---------- */
        #cmWarn {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(4, 6, 8, .82);
            font-family: Arial, Helvetica, sans-serif;
            animation: cmWarnFade .25s ease;
        }
        #cmWarn .cmw-card {
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            background: #11161a;
            border: 1px solid #292f34;
            border-radius: 6px;
            box-shadow: 0 0 0 1px rgba(245, 197, 66, .18), 0 24px 70px rgba(0, 0, 0, .75), 0 0 60px rgba(214, 158, 20, .28);
            text-align: center;
            animation: cmWarnIn .35s cubic-bezier(.2, .9, .3, 1.2);
        }
        /* single solid line along the top of the card, in warning yellow */
        #cmWarn .cmw-strip {
            height: 4px;
            background: #f5c542;
        }
        #cmWarn .cmw-body { padding: 26px 26px 24px; }
        #cmWarn .cmw-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            background: #241c0b;
            border: 1px solid #e0a911;
            border-radius: 50%;
            animation: cmWarnPulse 2s ease-in-out infinite;
        }
        #cmWarn .cmw-label {
            color: #f5c542;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        #cmWarn .cmw-title {
            margin: 8px 0 10px;
            color: #e6e8ea;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 24px;
            font-weight: normal;
        }
        #cmWarn .cmw-text {
            margin: 0 0 18px;
            color: #89929a;
            font-size: 13px;
            line-height: 1.6;
        }
        #cmWarn .cmw-text strong { color: #e6e8ea; }
        #cmWarn .cmw-rows {
            margin-bottom: 14px;
            background: #0f1418;
            border: 1px solid #292f34;
            border-radius: 5px;
            text-align: left;
        }
        #cmWarn .cmw-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 11px 14px;
            border-bottom: 1px solid #242a2f;
            font-size: 12px;
        }
        #cmWarn .cmw-row:last-child { border-bottom: none; }
        #cmWarn .cmw-row span { flex: 0 0 auto; color: #68727b; }
        #cmWarn .cmw-row strong {
            min-width: 0;
            overflow: hidden;
            color: #e6e8ea;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #cmWarn .cmw-row.strikes strong { color: #f5c542; }
        #cmWarn .cmw-hint {
            margin: 0 0 18px;
            color: #e3b94d;
            font-size: 11px;
        }
        /* real errors (for example a failed request) stay red */
        #cmWarn .cmw-error {
            margin: -8px 0 14px;
            color: #e07a7a;
            font-size: 11px;
        }
        #cmWarn .cmw-error[hidden] { display: none; }
        #cmWarn .cmw-btn {
            width: 100%;
            height: 44px;
            background: #f5c542;
            color: #1a1305;
            border: 1px solid #ffd866;
            border-radius: 5px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .2s ease;
        }
        #cmWarn .cmw-btn:hover:not(:disabled) { background: #ffd35c; }
        #cmWarn .cmw-btn:focus-visible { outline: 2px solid #fff3c4; outline-offset: 2px; }
        #cmWarn .cmw-btn:disabled { opacity: .6; cursor: wait; }

        @keyframes cmWarnFade  { from { opacity: 0; } to { opacity: 1; } }
        @keyframes cmWarnIn    { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }
        @keyframes cmWarnPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 197, 66, .45); }
            50%      { box-shadow: 0 0 0 10px rgba(245, 197, 66, 0); }
        }
    `;
    document.head.appendChild(style);

    /* ---------- helpers ---------- */

    function storeGet(key) {
        try { return sessionStorage.getItem(key); } catch { return null; }
    }

    function storeSet(key, value) {
        try { sessionStorage.setItem(key, String(value)); } catch { /* ignore */ }
    }

    // Small number badge on a sidebar link (for example Messages or My Questions)
    function setBadge(page, count) {
        const text = count > 99 ? "99+" : String(count);

        document.querySelectorAll(`a[href$="${page}"]`).forEach(link => {
            let badge = link.querySelector(".cm-nav-badge");

            if (!badge) {
                badge = document.createElement("span");
                badge.className = "cm-nav-badge";
                link.appendChild(badge);
            }

            badge.textContent = text;
            badge.hidden = count <= 0;
        });
    }

    function updateTitle() {
        const total = unreadMessages + unseenAnswers + unseenCollab;
        document.title = total > 0 ? `(${total}) ${BASE_TITLE}` : BASE_TITLE;
    }

    function showToast(href, titleText, previewText, icon = "♟") {
        let box = document.getElementById("cmToasts");

        if (!box) {
            box = document.createElement("div");
            box.id = "cmToasts";
            document.body.appendChild(box);
        }

        while (box.children.length >= 3) box.firstChild.remove();

        const toast = document.createElement("div");
        toast.className = "cm-toast";
        toast.setAttribute("role", "status");

        const iconEl = document.createElement("div");
        iconEl.className = "cm-toast-icon";
        iconEl.textContent = icon;

        const body = document.createElement("div");
        body.className = "cm-toast-body";

        const title = document.createElement("div");
        title.className = "cm-toast-title";
        title.textContent = titleText;

        const preview = document.createElement("div");
        preview.className = "cm-toast-text";
        preview.textContent = previewText;

        body.append(title, preview);

        const close = document.createElement("div");
        close.className = "cm-toast-close";
        close.setAttribute("aria-label", "Dismiss");
        close.textContent = "×";

        const bar = document.createElement("div");
        bar.className = "cm-toast-bar";

        toast.append(iconEl, body, close, bar);
        box.appendChild(toast);

        let timer;

        function dismiss() {
            clearTimeout(timer);
            toast.classList.add("cm-out");
            setTimeout(() => toast.remove(), 200);
        }

        close.addEventListener("click", event => {
            event.stopPropagation();
            dismiss();
        });

        toast.addEventListener("click", () => {
            window.location.href = href;
        });

        bar.addEventListener("animationend", dismiss);
    }

    function beep() {
        try {
            if (!audioCtx) return;
            if (audioCtx.state === "suspended") audioCtx.resume();

            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();

            osc.type = "sine";
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.15, audioCtx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.25);

            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.27);
        } catch { /* sound is optional */ }
    }

    // Browsers only allow sound after the user clicked something on the page.
    document.addEventListener("click", () => {
        if (audioCtx) return;
        try {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        } catch { /* ignore */ }
    }, { once: true });

    async function getJson(url) {
        const response = await fetch(url, {
            credentials: "same-origin",
            cache: "no-store",
            headers: { Accept: "application/json" }
        });

        if (!response.ok) return null;

        const data = await response.json();

        return data && data.ok ? data : null;
    }

    /* ---------- messages ---------- */

    async function pollMessages() {
        try {
            const data = await getJson(`${MESSAGES_URL}?action=unread`);

            if (!data) return;

            unreadMessages = Number(data.unread) || 0;
            setBadge("messages.php", unreadMessages);
            updateTitle();

            const key = `cm_last_notified_${data.me}`;
            const stored = storeGet(key);
            const latest = data.latest;

            if (!latest) {
                if (stored === null) storeSet(key, 0);
                return;
            }

            const latestId = Number(latest.message_id);

            // First visit in this tab: remember the current state, don't popup old messages.
            if (stored === null) {
                storeSet(key, latestId);
                return;
            }

            if (latestId > Number(stored)) {
                storeSet(key, latestId);

                const viewing =
                    Number(window.CHECKMATE_ACTIVE_CONVERSATION) ===
                        Number(latest.conversation_id) &&
                    !document.hidden;

                if (!viewing) {
                    showToast(
                        "messages.php?c=" + encodeURIComponent(latest.conversation_id),
                        "New message from " + latest.sender_name,
                        latest.preview,
                        "✉"
                    );
                    beep();
                }
            }
        } catch {
            /* network hiccup: try again on the next poll */
        }
    }

    /* ---------- answers to my questions ---------- */

    async function pollAnswers() {
        try {
            const data = await getJson(`${ANSWERS_URL}?action=answers`);

            if (!data) return;

            unseenAnswers = Number(data.unseen) || 0;
            setBadge("my_question.php", unseenAnswers);
            updateTitle();

            const latest = data.latest;

            if (!latest) return;

            const key = `cm_last_answer_${data.me}`;
            const stored = Number(storeGet(key)) || 0;
            const latestId = Number(latest.answer_id);

            // Pop up once for each new answer (also right after logging in)
            if (latestId > stored) {
                storeSet(key, latestId);

                showToast(
                    "my_question.php",
                    latest.answerer_name + " answered your question",
                    latest.preview,
                    "?"
                );
                beep();
            }
        } catch {
            /* network hiccup: try again on the next poll */
        }
    }

    /* ---------- collaboration requests / accepted requests ---------- */

    function loadNotified(key) {
        try {
            const list = JSON.parse(storeGet(key) || "[]");
            return Array.isArray(list) ? list : [];
        } catch {
            return [];
        }
    }

    async function pollCollab() {
        try {
            const data = await getJson(`${ANSWERS_URL}?action=collab`);

            if (!data) return;

            unseenCollab = (Number(data.requests) || 0) + (Number(data.accepted) || 0);
            setBadge("collaboration_stu.php", unseenCollab);
            updateTitle();

            const key = `cm_collab_notified_${data.me}`;
            const notified = loadNotified(key);
            const onPage =
                location.pathname.endsWith("collaboration_stu.php") && !document.hidden;
            let ring = false;

            (data.items || []).forEach(item => {
                const tag = `${item.kind}:${item.collaboration_id}`;

                if (notified.includes(tag)) return;

                notified.push(tag);

                // Already looking at the Collaboration page: the list shows it
                if (onPage) return;

                ring = true;

                if (item.kind === "accepted") {
                    showToast(
                        "collaboration_stu.php",
                        item.name + " accepted your collaboration request",
                        "You can now message each other.",
                        "✓"
                    );
                } else {
                    showToast(
                        "collaboration_stu.php",
                        item.name + " sent you a collaboration request",
                        "Open Collaboration to accept or reject.",
                        "♟"
                    );
                }
            });

            storeSet(key, JSON.stringify(notified.slice(-50)));

            if (ring) beep();
        } catch {
            /* network hiccup: try again on the next poll */
        }
    }

    /* ---------- warning popup (appears right away when an admin warns you) ---------- */

    function makeEl(tag, className, text) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    }

    function addRow(box, label, value, extraClass) {
        const row = makeEl("div", "cmw-row" + (extraClass ? " " + extraClass : ""));
        const key = makeEl("span", "", label);
        const val = makeEl("strong", "", value);
        val.title = value;
        row.append(key, val);
        box.appendChild(row);
    }

    function showWarning(data) {
        warningOpen = true;

        const many = data.pending > 1;
        const latest = data.latest || {};

        const overlay = makeEl("div");
        overlay.id = "cmWarn";
        overlay.setAttribute("role", "alertdialog");
        overlay.setAttribute("aria-modal", "true");
        overlay.setAttribute("aria-labelledby", "cmWarnTitle");

        const card = makeEl("div", "cmw-card");
        card.appendChild(makeEl("div", "cmw-strip"));

        const body = makeEl("div", "cmw-body");

        const icon = makeEl("div", "cmw-icon");
        icon.innerHTML =
            '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#f5c542" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>' +
            '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

        const title = makeEl("h2", "cmw-title",
            many ? "You have received " + data.pending + " warnings" : "You have received a warning");
        title.id = "cmWarnTitle";

        const text = makeEl("p", "cmw-text");
        text.append(
            many
                ? "Some of your questions were reported and reviewed by an administrator. They broke the community rules and have been "
                : "A question you posted was reported and reviewed by an administrator. It broke the community rules and has been ",
            makeEl("strong", "", "removed"),
            "."
        );

        const rows = makeEl("div", "cmw-rows");
        addRow(rows, "Reason", latest.reason || "Community rules violation");
        addRow(rows, many ? "Latest removed post" : "Removed post", latest.question_text || "Your question");
        addRow(rows, "Total warnings", String(data.total), "strikes");

        const hint = makeEl("p", "cmw-hint", "Further violations may lead to your account being banned.");
        const error = makeEl("p", "cmw-error", "");
        error.hidden = true;

        const button = makeEl("button", "cmw-btn", "I understand");
        button.type = "button";

        button.addEventListener("click", async () => {
            button.disabled = true;
            error.hidden = true;

            try {
                const form = new URLSearchParams();
                form.append("csrf", data.csrf);

                const response = await fetch(WARNINGS_URL, {
                    method: "POST",
                    body: form,
                    credentials: "same-origin"
                });
                const result = await response.json();

                if (!result.success) throw new Error(result.message);

                overlay.remove();
                document.body.style.overflow = overlay.dataset.prevOverflow || "";
                warningOpen = false;
            } catch (err) {
                error.textContent = (err && err.message) || "Something went wrong. Please try again.";
                error.hidden = false;
                button.disabled = false;
            }
        });

        // the popup can only be closed with the button
        overlay.addEventListener("keydown", event => {
            if (event.key === "Tab" || event.key === "Escape") event.preventDefault();
        });

        body.append(icon, makeEl("div", "cmw-label", "Official notice"), title, text, rows, hint, error, button);
        card.appendChild(body);
        overlay.appendChild(card);

        overlay.dataset.prevOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        document.body.appendChild(overlay);

        button.focus();
        beep();
    }

    async function pollWarnings() {
        if (warningOpen) return;

        try {
            const response = await fetch(`${WARNINGS_URL}?action=check`, {
                credentials: "same-origin",
                cache: "no-store",
                headers: { Accept: "application/json" }
            });

            if (!response.ok) return;

            const data = await response.json();

            if (!data || !data.success || !data.pending || warningOpen) return;

            showWarning(data);
        } catch {
            /* network hiccup: try again on the next poll */
        }
    }

    /* ---------- live warning stream (instant) ---------- */

    function startWarningStream() {
        if (!window.EventSource) return;

        const source = new EventSource(WARNINGS_STREAM_URL);

        source.addEventListener("open", () => {
            console.log("[checkmate] warning stream connected");
        });

        // the server says "a new warning was just added": fetch the details and show the popup
        source.addEventListener("warning", event => {
            console.log("[checkmate] warning event received", event.data);
            pollWarnings();
        });

        // on error the browser reconnects by itself; the normal polling is the backup
        source.addEventListener("error", () => {
            console.log("[checkmate] warning stream reconnecting...");
        });
    }

    /* ---------- start ---------- */

    function pollAll() {
        pollMessages();
        pollAnswers();
        pollCollab();
        pollWarnings();
    }

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) pollAll();
    });

    pollAll();
    window.setInterval(pollAll, POLL_MS);
    startWarningStream();
})();