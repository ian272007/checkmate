(() => {
    "use strict";

    const cfg = window.CHECKMATE_MESSAGES;
    if (!cfg) return;

    const $ = id => document.getElementById(id);

    const LOCKED_TEXT =
        "Messaging is available after a collaboration request is accepted.";

    const state = {
        conversations: [],
        activeConversationId: null,
        activeOtherId: null,
        activeOther: null,
        lastKey: "",
        busy: false
    };

    const list = $("peopleList");
    const search = $("conversationSearch");
    const alertBox = $("messageAlert");
    const chatEmpty = $("chatEmpty");
    const activeChat = $("activeChat");
    const chatMessages = $("chatMessages");
    const messageForm = $("messageForm");
    const messageInput = $("messageInput");
    const sendButton = $("sendButton");
    const deleteChatButton = $("deleteChatButton");
    const deleteModal = $("deleteModal");
    const deleteConfirm = $("deleteConfirm");
    const deleteCancel = $("deleteCancel");
    const composerNote = document.querySelector(".composer-note");
    const composerNoteDefault = composerNote
        ? composerNote.textContent.trim()
        : "";

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[char]);
    }

    function showAlert(message = "", success = false) {
        alertBox.textContent = message;
        alertBox.hidden = !message;
        alertBox.classList.toggle("success", Boolean(message && success));
    }

    async function api(action, options = {}) {
        const method = options.method || "GET";
        const url = new URL(cfg.apiUrl, window.location.href);

        url.searchParams.set("action", action);

        if (method === "GET" && options.params) {
            Object.entries(options.params).forEach(([key, value]) => {
                url.searchParams.set(key, String(value));
            });
        }

        const init = {
            method,
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        };

        if (method === "POST") {
            init.headers["Content-Type"] =
                "application/x-www-form-urlencoded;charset=UTF-8";

            init.headers["X-CSRF-Token"] = cfg.csrfToken;

            init.body = new URLSearchParams({
                action,
                ...(options.data || {})
            }).toString();
        }

        const response = await fetch(url, init);

        let data;

        try {
            data = await response.json();
        } catch {
            throw new Error(
                "Invalid server response. Check your PHP error log."
            );
        }

        if (!response.ok || !data.ok) {
            throw new Error(data.error || "Request failed.");
        }

        return data;
    }

    function formatTime(value) {
        if (!value) return "";

        const date = new Date(String(value).replace(" ", "T"));

        if (Number.isNaN(date.getTime())) return "";

        const today = new Date();

        if (date.toDateString() === today.toDateString()) {
            return date.toLocaleTimeString([], {
                hour: "numeric",
                minute: "2-digit"
            });
        }

        return date.toLocaleDateString([], {
            month: "short",
            day: "numeric"
        });
    }

    function initials(name) {
        const text = String(name || "?").trim();
        const parts = text.split(/\s+/).filter(Boolean);

        if (!parts.length) return "?";

        return (
            parts[0].charAt(0) +
            (parts.length > 1 ? parts[parts.length - 1].charAt(0) : "")
        ).toUpperCase();
    }

    function isActiveLocked() {
        const conversation = state.conversations.find(
            item => Number(item.conversation_id) ===
                    Number(state.activeConversationId)
        );

        return Boolean(conversation) && conversation.can_message === false;
    }

    function updateComposerState() {
        if (!state.activeConversationId) return;

        const locked = isActiveLocked();

        messageInput.disabled = locked;
        sendButton.disabled = locked || state.busy;
        messageInput.placeholder = locked
            ? "Messaging is locked"
            : "Write your message...";

        if (composerNote) {
            composerNote.textContent = locked
                ? LOCKED_TEXT
                : composerNoteDefault;
        }

        messageForm.classList.toggle("locked", locked);
    }

    async function loadConversations() {
        const data = await api("conversations");
        state.conversations = data.conversations || [];

        renderList();
        updateComposerState();
    }

    function renderList() {
        const query = search.value.trim().toLowerCase();

        const entries = state.conversations.filter(item => {
            const text =
                `${item.name} ${item.student_id} ${item.last_message || ""}`;

            return text.toLowerCase().includes(query);
        });

        if (!entries.length) {
            list.innerHTML = `
                <div class="list-placeholder">
                    No conversations yet.<br>
                    Messaging opens once a collaboration request is accepted.
                </div>
            `;
            return;
        }

        list.innerHTML = entries.map(item => {
            const selected =
                Number(item.conversation_id) ===
                Number(state.activeConversationId);

            const preview = item.last_message || "Start a conversation";

            const unread = Number(item.unread_count) > 0
                ? `<span class="unread-badge">${
                    Number(item.unread_count) > 99
                        ? "99+"
                        : Number(item.unread_count)
                  }</span>`
                : "";

            return `
                <button
                    type="button"
                    class="person-row ${selected ? "selected" : ""}"
                    data-id="${Number(item.other_id)}"
                    data-conversation="${Number(item.conversation_id)}"
                >
                    <span class="person-avatar">
                        ${escapeHtml(initials(item.name))}
                    </span>

                    <span class="person-row-copy">
                        <span class="person-row-top">
                            <span class="person-row-name">
                                ${escapeHtml(item.name)}
                            </span>

                            <span class="person-row-time">
                                ${escapeHtml(formatTime(item.last_sent_at))}
                            </span>
                        </span>

                        <span class="person-row-preview">
                            ${escapeHtml(preview)}
                        </span>

                        <span class="person-role">
                            ${escapeHtml(item.student_id || "Student")}
                        </span>
                    </span>

                    ${unread}
                </button>
            `;
        }).join("");
    }

    function updateChatHeader(person) {
        $("chatName").textContent = person?.name || "Student";

        $("chatMeta").textContent =
            `${person?.student_id || "Student account"}` +
            `${person?.course ? " · " + person.course : ""}`;

        $("chatAvatar").textContent = initials(person?.name);
    }

    async function openConversation(conversationId, otherId, person) {
        state.activeConversationId = Number(conversationId);
        state.activeOtherId = Number(otherId);

        window.CHECKMATE_ACTIVE_CONVERSATION = state.activeConversationId;

        state.activeOther =
            person ||
            state.conversations.find(
                item => Number(item.other_id) === Number(otherId)
            );

        updateChatHeader(state.activeOther);

        chatEmpty.hidden = true;
        activeChat.hidden = false;
        state.lastKey = "";

        renderList();
        updateComposerState();

        await loadMessages(true);

        if (!messageInput.disabled) messageInput.focus();
    }

    function closeActiveChat() {
        state.activeConversationId = null;
        state.activeOtherId = null;
        state.activeOther = null;
        state.lastKey = "";
        window.CHECKMATE_ACTIVE_CONVERSATION = null;

        chatMessages.innerHTML = "";
        activeChat.hidden = true;
        chatEmpty.hidden = false;
    }

    async function loadMessages(forceScroll = false) {
        if (!state.activeConversationId) return;

        const data = await api("messages", {
            params: {
                conversation_id: state.activeConversationId
            }
        });

        if (!state.activeConversationId) return;

        const messages = data.messages || [];

        const key = messages.length
            ? `${messages.length}:${messages[messages.length - 1].message_id}:${messages[messages.length - 1].is_read}`
            : "empty";

        if (key !== state.lastKey) {
            const nearBottom =
                chatMessages.scrollHeight -
                chatMessages.scrollTop -
                chatMessages.clientHeight < 100;

            state.lastKey = key;

            if (!messages.length) {
                chatMessages.innerHTML = `
                    <div class="list-placeholder">
                        No messages yet. Say hello to start the conversation.
                    </div>
                `;
            } else {
                chatMessages.innerHTML = messages.map(message => {
                    const mine =
                        Number(message.sender_id) === Number(cfg.currentUserId);

                    return `
                        <div class="message-line ${mine ? "mine" : ""}">
                            ${
                                mine ? "" : `
                                    <div class="message-sender">
                                        ${escapeHtml(message.sender_name)}
                                    </div>
                                `
                            }

                            <div class="message-bubble">${escapeHtml(
                                message.message_body
                            )}</div>

                            <div class="message-time">
                                ${escapeHtml(formatTime(message.sent_at))}
                                ${mine && message.is_read ? " · Read" : ""}
                            </div>
                        </div>
                    `;
                }).join("");
            }

            if (forceScroll || nearBottom) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        await loadConversations();
    }

    async function sendMessage(event) {
        event.preventDefault();

        if (state.busy || !state.activeConversationId) return;

        if (isActiveLocked()) {
            showAlert(LOCKED_TEXT);
            return;
        }

        const body = messageInput.value.trim();

        if (!body) return;

        state.busy = true;
        sendButton.disabled = true;

        try {
            await api("send", {
                method: "POST",
                data: {
                    conversation_id: String(state.activeConversationId),
                    message_body: body
                }
            });

            messageInput.value = "";
            messageInput.style.height = "auto";

            showAlert("");
            await loadMessages(true);
        } catch (error) {
            showAlert(error.message);

            loadConversations().catch(() => {});
        } finally {
            state.busy = false;
            updateComposerState();

            if (!messageInput.disabled) messageInput.focus();
        }
    }

    function openDeleteModal() {
        if (!state.activeConversationId) return;

        $("deleteModalName").textContent =
            state.activeOther?.name || "this student";

        deleteModal.hidden = false;
        deleteCancel.focus();
    }

    function closeDeleteModal() {
        deleteModal.hidden = true;
    }

    async function deleteConversation() {
        const conversationId = state.activeConversationId;

        if (!conversationId) {
            closeDeleteModal();
            return;
        }

        deleteConfirm.disabled = true;

        try {
            await api("delete", {
                method: "POST",
                data: {
                    conversation_id: String(conversationId)
                }
            });

            closeDeleteModal();
            closeActiveChat();
            await loadConversations();
            showAlert("Conversation deleted.", true);
        } catch (error) {
            closeDeleteModal();
            showAlert(error.message);
        } finally {
            deleteConfirm.disabled = false;
        }
    }

    deleteChatButton.addEventListener("click", openDeleteModal);
    deleteCancel.addEventListener("click", closeDeleteModal);
    deleteConfirm.addEventListener("click", deleteConversation);

    deleteModal.addEventListener("click", event => {
        if (event.target === deleteModal) closeDeleteModal();
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && !deleteModal.hidden) {
            closeDeleteModal();
        }
    });

    list.addEventListener("click", async event => {
        const row = event.target.closest(".person-row");

        if (!row) return;

        try {
            showAlert("");

            const item = state.conversations.find(
                conversation =>
                    Number(conversation.conversation_id) ===
                    Number(row.dataset.conversation)
            );

            await openConversation(
                row.dataset.conversation,
                row.dataset.id,
                item
            );
        } catch (error) {
            showAlert(error.message);
        }
    });

    search.addEventListener("input", renderList);

    messageForm.addEventListener("submit", sendMessage);

    messageInput.addEventListener("input", () => {
        messageInput.style.height = "auto";
        messageInput.style.height =
            `${Math.min(messageInput.scrollHeight, 120)}px`;
    });

    messageInput.addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            messageForm.requestSubmit();
        }
    });

    async function refresh() {
        try {
            await loadConversations();

            if (state.activeConversationId) {
                await loadMessages(false);
            }
        } catch (error) {
            if (/log in|session/i.test(error.message)) {
                showAlert(error.message);
            }
        }
    }

    async function init() {
        try {
            await loadConversations();

            const wanted = Number(
                new URLSearchParams(window.location.search).get("c")
            );

            const found = state.conversations.find(
                item => Number(item.conversation_id) === wanted
            );

            if (found) {
                await openConversation(
                    found.conversation_id,
                    found.other_id,
                    found
                );
            }

            window.setInterval(refresh, 3500);
        } catch (error) {
            showAlert(error.message);

            list.innerHTML = `
                <div class="list-placeholder">
                    Unable to load messages.<br>
                    ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    init();
})();