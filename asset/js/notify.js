/* CHECKMATE - notifications (shared by all student pages)
   - new messages: badge on "Messages", toast, sound
   - new answers to your questions: badge on "My Questions", toast, sound
   - new collaboration requests / accepted requests: badge on "Collaboration", toast, sound
   - total unread count in the browser tab title
   Needs: messages_api.php (action=unread) and notifications_api.php
   (action=answers, action=collab) in view/student/ */

(() => {
    "use strict";

    if (window.__checkmateNotify) return;
    window.__checkmateNotify = true;

    const MESSAGES_URL = "messages_api.php";
    const ANSWERS_URL = "notifications_api.php";
    const POLL_MS = 4000;
    const BASE_TITLE = document.title;

    let audioCtx = null;
    let unreadMessages = 0;
    let unseenAnswers = 0;
    let unseenCollab = 0;

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

    function pollAll() {
        pollMessages();
        pollAnswers();
        pollCollab();
    }

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) pollAll();
    });

    pollAll();
    window.setInterval(pollAll, POLL_MS);
})();