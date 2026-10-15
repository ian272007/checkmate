<?php
session_start();

require_once __DIR__ . "/../../config/config.php";

if (empty($_SESSION["student_id"])) {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

$studentCode = (string) $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, student_id, course, year_level, role
     FROM users
     WHERE student_id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit("Unable to prepare student query.");
}

$stmt->bind_param("s", $studentCode);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || strtolower(trim($user["role"] ?? "")) !== "student") {
    http_response_code(403);
    exit("Messaging is available to students only.");
}

if (empty($_SESSION["messages_csrf"])) {
    $_SESSION["messages_csrf"] = bin2hex(random_bytes(32));
}

$currentUserId = (int) $user["id"];
$firstName = trim($user["first_name"] ?? "");
$lastName  = trim($user["last_name"] ?? "");
$fullName = trim($firstName . " " . $lastName);

if ($fullName === "") {
    $fullName = "Student";
}

$course = trim($user["course"] ?? "");
$nameParts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
$initials = "";

if (!empty($nameParts)) {
    $initials = mb_substr($nameParts[0], 0, 1, "UTF-8");

    if (count($nameParts) > 1) {
        $initials .= mb_substr(
            $nameParts[count($nameParts) - 1],
            0,
            1,
            "UTF-8"
        );
    }
}

$initials = mb_strtoupper($initials ?: "S", "UTF-8");

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

$csrf = $_SESSION["messages_csrf"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKMATE - Messages</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>

    <link rel="stylesheet"
          href="../../asset/css/question.css?v=<?= (int) @filemtime(__DIR__ . '/../../asset/css/question.css') ?>">
    <link rel="stylesheet"
          href="../../asset/css/messages.css?v=<?= (int) @filemtime(__DIR__ . '/../../asset/css/messages.css') ?>">

    <script>
        window.CHECKMATE_MESSAGES = {
            apiUrl: "messages_api.php",
            csrfToken: <?= json_encode($csrf) ?>,
            currentUserId: <?= $currentUserId ?>
        };
    </script>

    <script src="../../asset/js/messages.js" defer></script>
</head>

<body>

<aside class="sidebar">
    <div class="logo-area">
        <div class="logo-piece">♞</div>
        <div class="logo-text">
            <h1>CHECKMATE</h1>
            <span>PEER COLLABORATION</span>
        </div>
    </div>

    <a href="questions.php" class="new-question-button">
        <span class="button-icon">+</span>
        <span>New Question</span>
    </a>

    <div class="sidebar-section">
        <div class="section-title">WORKSPACE</div>

        <a href="student_home.php" class="menu-item">
            <span class="menu-icon">⌂</span><span>Home</span>
        </a>

        <a href="questions.php" class="menu-item">
            <span class="menu-icon">?</span><span>Questions</span>
        </a>

        <a href="my_question.php" class="menu-item">
            <span class="menu-icon">♧</span><span>My Questions</span>
        </a>

        <a href="collaboration_stu.php" class="menu-item">
            <span class="menu-icon">♟</span><span>Collaboration</span>
        </a>

        <a href="messages.php" class="menu-item active" aria-current="page">
            <span class="menu-icon">✉</span><span>Messages</span>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= e($initials) ?></div>

        <div class="user-information">
            <strong><?= e($fullName) ?></strong>

            <span>
                <?= e($course ?: "Student") ?><br>
                <?= e($user["student_id"] ?? "") ?>
            </span>
        </div>

        <button class="user-more" type="button" aria-label="More options">⋮</button>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-label">STUDENT WORKSPACE</span>
            <span class="topbar-divider">/</span>
            <span class="topbar-current">Messages</span>
        </div>
    </header>

    <section class="questions-page msg-page">

        <div class="page-heading">
            <span class="heading-label">PEER COLLABORATION</span>
            <h2>Messages</h2>
            <p>Chat privately with students you are collaborating with. Messaging opens after a collaboration request is accepted.</p>
        </div>

        <div id="messageAlert"
             class="message-alert"
             role="alert"
             aria-live="polite"
             hidden></div>

        <section class="messenger">
            <aside class="people-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">YOUR INBOX</span>
                        <h2>Messages</h2>
                    </div>
                </div>

                <label class="search-wrap">
                    <span aria-hidden="true">⌕</span>

                    <input id="conversationSearch"
                           type="search"
                           placeholder="Search conversations..."
                           autocomplete="off"
                           aria-label="Search conversations">
                </label>

                <div id="peopleList"
                     class="people-list"
                     aria-live="polite">
                    <div class="list-placeholder">Loading messages...</div>
                </div>
            </aside>

            <section class="chat-panel">
                <div id="chatEmpty" class="chat-empty">
                    <div class="empty-knight" aria-hidden="true">♟</div>

                    <h2>Your conversations</h2>

                    <p>Select a conversation to start chatting privately.</p>
                </div>

                <div id="activeChat" class="active-chat" hidden>
                    <header class="chat-header">
                        <div id="chatAvatar" class="person-avatar">?</div>

                        <div class="chat-person">
                            <h2 id="chatName">Student</h2>
                            <p id="chatMeta">Student account</p>
                        </div>

                        <span class="private-label">PRIVATE CHAT</span>

                        <button id="deleteChatButton"
                                class="delete-chat-btn"
                                type="button"
                                title="Delete conversation">
                            <span aria-hidden="true">🗑</span>
                            <span>Delete</span>
                        </button>
                    </header>

                    <div id="chatMessages"
                         class="chat-messages"
                         aria-live="polite"
                         aria-relevant="additions text"></div>

                    <form id="messageForm" class="message-composer">
                        <label class="sr-only" for="messageInput">
                            Write a message
                        </label>

                        <textarea id="messageInput"
                                  name="message_body"
                                  rows="1"
                                  maxlength="5000"
                                  placeholder="Write your message..."
                                  required></textarea>

                        <button id="sendButton"
                                type="submit"
                                class="send-btn">
                            <span>Send</span>
                            <span aria-hidden="true">➤</span>
                        </button>

                        <div class="composer-note">
                            Enter to send · Shift + Enter for a new line
                        </div>
                    </form>
                </div>
            </section>
        </section>

        <p class="security-note">
            Messages are private to the two students in each conversation.
        </p>

    </section>
</main>

<!-- Delete confirmation -->
<div id="deleteModal" class="confirm-overlay" hidden>
    <div class="confirm-box" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <h3 id="deleteModalTitle">Delete conversation?</h3>
        <p>
            This removes the conversation with <strong id="deleteModalName">this student</strong>
            from your inbox. The other student will still keep their copy.
        </p>
        <div class="confirm-actions">
            <button id="deleteCancel" type="button" class="ghost-btn">Cancel</button>
            <button id="deleteConfirm" type="button" class="danger-btn">Delete</button>
        </div>
    </div>
</div>

</body>
</html>