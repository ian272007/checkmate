<?php

session_start();

if (
    !isset($_SESSION["student_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../../authentication/Login/login.php");
    exit();
}

require_once "../../config/config.php";

$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, student_id, course
     FROM users
     WHERE student_id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("User query failed: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Student account not found.");
}

$user_id = (int)$user["id"];

/* NOTE: warnings are no longer handled here.
   The warning popup is shown live by asset/js/notify.js
   (it asks warnings_api.php?action=check every few seconds). */

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function personName($first, $last): string
{
    $name = trim(trim((string)$first) . " " . trim((string)$last));
    return $name !== "" ? $name : "Student";
}

function firstLetter($text): string
{
    $letter = mb_strtoupper(
        mb_substr(trim((string)$text), 0, 1, "UTF-8"),
        "UTF-8"
    );

    return $letter !== "" ? $letter : "S";
}

function timeAgo($seconds): string
{
    $seconds = max(0, (int)$seconds);

    if ($seconds < 60) {
        return "Just now";
    }

    if ($seconds < 3600) {
        return floor($seconds / 60) . " min ago";
    }

    if ($seconds < 86400) {
        $hours = (int)floor($seconds / 3600);
        return $hours . ($hours === 1 ? " hr ago" : " hrs ago");
    }

    $days = (int)floor($seconds / 86400);

    if ($days < 30) {
        return $days . ($days === 1 ? " day ago" : " days ago");
    }

    return floor($days / 30) . " mo ago";
}

/* Same security token the Questions page uses
   (question_post_api.php checks it before saving a question) */
if (empty($_SESSION["questions_csrf"])) {
    $_SESSION["questions_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["questions_csrf"];

/* Dropdown choices for the Ask a Question box */
function loadOptions(mysqli $conn, string $table, string $column): array
{
    $result = $conn->query(
        "SELECT `$column` AS option_name FROM `$table`
         WHERE status = 'Active' ORDER BY `$column` ASC"
    );

    $options = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = $row["option_name"];
        }
    }

    return $options;
}

$subjects = loadOptions($conn, "question_subjects", "subject_name");
$courses  = loadOptions($conn, "question_courses", "course_name");
$types    = loadOptions($conn, "question_types", "type_name");

$firstInitial = substr(
    trim($user["first_name"] ?? ""),
    0,
    1
);

$lastInitial = substr(
    trim($user["last_name"] ?? ""),
    0,
    1
);

$initials = strtoupper($firstInitial . $lastInitial);

$displayName = trim(
    ($user["first_name"] ?? "") . " " .
    ($user["last_name"] ?? "")
);

/* RECENT QUESTIONS: newest ACTIVE questions posted by OTHER students
   (same filter as questions.php, so the arrow always finds the question) */
$recentQuestions = [];

try {
    $stmt = $conn->prepare(
        "SELECT q.question_id,
                q.question,
                TIMESTAMPDIFF(SECOND, q.create_at, NOW()) AS seconds_ago,
                u.first_name,
                u.last_name
         FROM questions q
         INNER JOIN users u ON u.id = q.user_id
         WHERE q.user_id <> ?
           AND q.status = 'Active'
         ORDER BY q.create_at DESC, q.question_id DESC
         LIMIT 3"
    );

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $recentQuestions[] = $row;
        }

        $stmt->close();
    }
} catch (Throwable $ex) {
    $recentQuestions = [];
}

/* YOUR PEERS: students who have an ACCEPTED collaboration with you.
   conversation_id = newest accepted collaboration_id with that peer
   (same rule messages_api.php uses), so the arrow opens the right chat. */
$peers = [];

try {
    $stmt = $conn->prepare(
        "SELECT u.id,
                u.first_name,
                u.last_name,
                MAX(c.collaboration_id) AS conversation_id,
                MAX(c.create_at) AS last_at
         FROM collaborations c
         INNER JOIN users u
            ON u.id = CASE
                WHEN c.requester_id = ? THEN c.receiver_id
                ELSE c.requester_id
            END
         WHERE (c.requester_id = ? OR c.receiver_id = ?)
           AND c.status = 'Accepted'
         GROUP BY u.id, u.first_name, u.last_name
         ORDER BY last_at DESC
         LIMIT 5"
    );

    if ($stmt) {
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $peers[] = $row;
        }

        $stmt->close();
    }
} catch (Throwable $ex) {
    $peers = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Student Home</title>

    <!-- Bootstrap 5.3.3 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>
    <!-- CHECKMATE Student Home CSS -->
    <link
        rel="stylesheet"
        href="../../asset/css/student_home.css"
    >

    <!-- Styles for the subject / course / type / attachment row -->
    <link
        rel="stylesheet"
        href="../../asset/css/student_home_ask.css?v=<?= filemtime(__DIR__ . '/../../asset/css/student_home_ask.css') ?>"
    >

    <!-- Force CHECKMATE link styling -->
    <style>
        a.new-question-button,
        a.new-question-button:link,
        a.new-question-button:visited,
        a.new-question-button:hover,
        a.new-question-button:active,
        a.new-question-button:focus {
            text-decoration: none !important;
            text-decoration-line: none !important;
            outline: none !important;
            box-shadow: none !important;
        }

        .empty-note {
            padding: 26px 22px;
            color: #718398;
            font-size: 12px;
            line-height: 1.7;
        }

        .question-item {
            cursor: pointer;
        }

        /* keep the arrow box from shrinking inside the question row */
        .question-item .open-button {
            flex-shrink: 0;
        }
    </style>

</head>

<body>


<!-- SIDEBAR -->
<aside class="sidebar">

    <!-- LOGO -->
    <div class="logo-area">
        <div class="logo-piece">♞</div>

        <div class="logo-text">
            <h1>CHECKMATE</h1>
            <span>PEER COLLABORATION</span>
        </div>
    </div>

    <!-- NEW QUESTION -->
    <a href="questions.php" class="new-question-button">
        <span class="button-icon">+</span>
        <span>New Question</span>
    </a>

    <!-- WORKSPACE MENU -->
    <div class="sidebar-section">

        <div class="section-title">WORKSPACE</div>

        <!-- HOME -->
        <a href="student_home.php" class="menu-item active">
            <span class="menu-icon">⌂</span>
            <span>Home</span>
        </a>

        <!-- QUESTIONS -->
        <a href="questions.php" class="menu-item">
            <span class="menu-icon">?</span>
            <span>Questions</span>
        </a>

        <!-- MY QUESTIONS -->
        <a href="my_question.php" class="menu-item">
            <span class="menu-icon">♧</span>
            <span>My Questions</span>
        </a>

        <!-- COLLABORATION -->
        <a href="collaboration_stu.php" class="menu-item">
            <span class="menu-icon">♟</span>
            <span>Collaboration</span>
        </a>

        <!-- MESSAGES -->
        <a href="messages.php" class="menu-item">
            <span class="menu-icon">✉</span>
            <span>Messages</span>
        </a>

    </div>

    <!-- STUDENT PROFILE -->
    <div class="sidebar-user">

        <div class="user-avatar">
            <?= e($initials) ?>
        </div>

        <div class="user-information">

            <strong>
                <?= e(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")) ?: "Student") ?>
            </strong>

            <span>
                <?= e($user["course"] ?? "") ?> <br>
                
                <?= e($user["student_id"] ?? "") ?>
            </span>
        </div>

        <button
            class="user-more"
            type="button"
            aria-label="More options"
        >⋮</button>

    </div>

</aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">

            <div class="topbar-left">

                <span class="topbar-label">
                    STUDENT WORKSPACE
                </span>

                <span class="topbar-divider">
                    /
                </span>

                <span class="topbar-current">
                    Home
                </span>

            </div>

        </header>

        <!-- WELCOME SECTION -->
        <section class="welcome-section">

            <div class="welcome-small">
                ♟ YOUR MOVE
            </div>

            <h2>
                What are you working on?
            </h2>

            <p>
                Ask a question, find a peer, and make your next move.
            </p>

        </section>

        <!-- QUICK QUESTION BOX -->
        <section class="ask-box" id="askBox">

            <div class="ask-box-top">

                <div class="ask-icon">
                    ?
                </div>

                <div>

                    <h3>
                        Ask a Question
                    </h3>

                    <p>
                        Share what you're stuck on with your classmates.
                    </p>

                </div>

            </div>

            <!-- Post a question straight from Home -->
            <div id="questionForm" data-csrf="<?= e($csrf) ?>">

                <textarea
                    id="questionInput"
                    class="question-input"
                    placeholder="What would you like to ask?"
                    aria-label="Write a question"
                    maxlength="5000"
                ></textarea>

                <!-- SUBJECT / COURSE / QUESTION TYPE -->
                <div class="ask-fields">

                    <select id="askSubject" aria-label="Subject" required>
                        <option value="">Subject</option>
                        <?php foreach ($subjects as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="askCourse" aria-label="Course" required>
                        <option value="">Course</option>
                        <?php foreach ($courses as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="askType" aria-label="Question type" required>
                        <option value="">Question Type</option>
                        <?php foreach ($types as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>

                </div>

                <!-- message shown when something is missing or went wrong -->
                <div class="ask-message" id="askMessage" role="alert" hidden></div>

                <div class="ask-bottom">

                    <!-- ATTACHMENT -->
                    <div class="ask-attach">

                        <button
                            type="button"
                            class="attach-plus"
                            id="attachPlus"
                            aria-label="Add attachment"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >+</button>

                        <div class="attach-menu" id="attachMenu" hidden>
                            <button type="button" class="attach-option" data-attachment="photos">
                                <span class="attach-option-icon">▧</span>
                                <span>Photos</span>
                            </button>

                            <button type="button" class="attach-option" data-attachment="files">
                                <span class="attach-option-icon">▤</span>
                                <span>Files</span>
                            </button>
                        </div>

                        <span class="attach-name" id="attachName">Add attachment (optional)</span>

                        <button
                            type="button"
                            class="attach-clear"
                            id="attachClear"
                            aria-label="Remove attachment"
                            hidden
                        >✕</button>

                        <input
                            type="file"
                            id="attachInput"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.xls,.xlsx,.csv"
                            hidden
                        >

                    </div>

                    <!-- MAKE A MOVE -->
                    <button
                        type="button"
                        class="ask-button"
                        id="askButton"
                    >
                        <span class="ask-button-label" id="askButtonLabel">Make a Move</span>
                        <span>→</span>
                    </button>

                </div>

            </div>

        </section>

        <!-- DASHBOARD -->
        <section class="dashboard-section">

            <!-- QUICK MOVES -->
            <div class="quick-heading">

                <div class="heading-label">
                    QUICK MOVES
                </div>

                <h2>
                    Choose your next move
                </h2>

            </div>

            <!-- QUICK CARDS -->
            <div class="quick-grid">

                <!-- BROWSE QUESTIONS -->
                <div
                    class="quick-card"
                    onclick="window.location.href='questions.php'"
                    role="link"
                    tabindex="0"
                    onkeydown="if(event.key === 'Enter' || event.key === ' '){event.preventDefault();window.location.href='questions.php';}"
                >

                    <div class="quick-card-icon">
                        ♧
                    </div>

                    <div class="quick-card-content">

                        <h4>
                            Browse Questions
                        </h4>

                        <p>
                            See what your classmates are asking.
                        </p>

                    </div>

                    <div class="card-arrow">
                        →
                    </div>

                </div>

                <!-- COLLABORATE -->
                <div
                    class="quick-card"
                    role="link"
                    onclick="window.location.href='collaboration_stu.php'"
                    tabindex="0"
                    onkeydown="if(event.key === 'Enter' || event.key === ' '){event.preventDefault();window.location.href='collaboration_stu.php';}"
                >

                    <div class="quick-card-icon">
                        ♟
                    </div>

                    <div class="quick-card-content">

                        <h4>
                            Collaborate
                        </h4>

                        <p>
                            Find classmates to work and learn with.
                        </p>

                    </div>

                    <div class="card-arrow">
                        →
                    </div>

                </div>

            </div>

            <!-- TWO COLUMN DASHBOARD -->
            <div class="dashboard-columns mt-3">

                <!-- RECENT QUESTIONS -->
                <div class="dashboard-panel">

                    <div class="panel-header">

                        <div>

                            <div class="panel-label">
                                RECENT
                            </div>

                            <h3>
                                Questions
                            </h3>

                        </div>

                        <a
                            href="questions.php"
                            class="view-all"
                        >
                            View All
                            <span>→</span>
                        </a>

                    </div>

                    <div class="questions-list">

                        <?php if (!empty($recentQuestions)): ?>

                            <?php foreach ($recentQuestions as $index => $q): ?>
                                <?php $qUrl = "questions.php#question-" . (int)$q["question_id"]; ?>

                                <div
                                    class="question-item"
                                    onclick="window.location.href='<?= e($qUrl) ?>'"
                                >

                                    <div class="question-number">
                                        <?= str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT) ?>
                                    </div>

                                    <div class="question-content">

                                        <div class="question-text">
                                            <?= e(mb_strimwidth((string)$q["question"], 0, 90, "…", "UTF-8")) ?>
                                        </div>

                                        <div class="question-meta">
                                            <span><?= e(personName($q["first_name"], $q["last_name"])) ?></span>
                                            <span>•</span>
                                            <span><?= e(timeAgo($q["seconds_ago"])) ?></span>
                                        </div>

                                    </div>

                                    <button
                                        type="button"
                                        class="open-button"
                                        onclick="event.stopPropagation(); window.location.href='<?= e($qUrl) ?>'"
                                        aria-label="Open question: <?= e(mb_strimwidth((string)$q["question"], 0, 60, "…", "UTF-8")) ?>"
                                    >
                                        →
                                    </button>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-note">
                                No questions from other students yet.
                            </div>

                        <?php endif; ?>

                    </div>

                </div>

                <!-- COLLABORATION -->
                <div class="dashboard-panel">

                    <div class="panel-header">

                        <div>

                            <div class="panel-label">
                                YOUR PEERS
                            </div>

                            <h3>
                                Collaboration
                            </h3>

                        </div>

                        <a
                            href="collaboration_stu.php"
                            class="view-all"
                        >
                            View All
                            <span>→</span>
                        </a>

                    </div>

                    <div class="collaboration-list">

                        <?php if (!empty($peers)): ?>

                            <?php foreach ($peers as $peer): ?>
                                <?php
                                $peerName = personName($peer["first_name"], $peer["last_name"]);
                                $chatUrl = "messages.php?c=" . (int)$peer["conversation_id"];
                                ?>

                                <div class="collaboration-item">

                                    <div class="collaborator-avatar">
                                        <?= e(firstLetter($peerName)) ?>
                                    </div>

                                    <div class="collaborator-info">

                                        <div class="collaborator-name">
                                            <?= e($peerName) ?>
                                        </div>

                                        <div class="collaborator-status active">
                                            ● Active
                                        </div>

                                    </div>

                                    <button
                                        type="button"
                                        class="open-button"
                                        onclick="window.location.href='<?= e($chatUrl) ?>'"
                                        aria-label="Open conversation with <?= e($peerName) ?>"
                                    >
                                        →
                                    </button>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="empty-note">
                                No accepted collaborations yet.
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="panel-footer-note">
                        Connect with classmates and learn together.
                    </div>

                </div>

            </div>

        </section>

        <!-- FOOTER -->
        <footer class="chess-footer">

            <div class="footer-piece">
                ♟
            </div>

            <div class="footer-text">

                <strong>
                    Every question is a move.
                </strong>

                <span>
                    Learn together. Move forward.
                </span>

            </div>

            <div class="footer-piece">
                ♟
            </div>

        </footer>

    </main>

    <!-- TOAST -->
    <div
        class="toast"
        id="toast"
        role="status"
        aria-live="polite"
    ></div>

    <!-- BOOTSTRAP 5.3.3 JAVASCRIPT -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>

    <!-- CHECKMATE JAVASCRIPT -->
    <script src="../../asset/js/student_home.js?v=<?= filemtime(__DIR__ . '/../../asset/js/student_home.js') ?>"></script>

</body>

</html>