<?php
session_start();
require_once "../../config/config.php";

function jsonOut(array $data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

$getAction = $_GET["action"] ?? "";
$postAction = $_POST["action"] ?? "";

$isAjax =
    in_array($getAction, ["search", "summary"], true) ||
    $postAction === "send" ||
    (($_POST["ajax"] ?? "") === "1");

if (empty($_SESSION["student_id"])) {
    if ($isAjax) {
        jsonOut(["ok" => false, "error" => "Please log in again."], 401);
    }

    header("Location: ../../authentication/Login/login.php");
    exit();
}

$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, student_id, course
     FROM users WHERE student_id = ? LIMIT 1"
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

if (empty($_SESSION["collab_csrf"])) {
    $_SESSION["collab_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["collab_csrf"];

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* Pending / Accepted / Rejected (anything else counts as pending) */
function collabStatus($status): string
{
    $key = strtolower(trim((string)$status));
    return in_array($key, ["accepted", "rejected"], true) ? $key : "pending";
}

/* Changes whenever a request is added, answered or cancelled */
function collabSignature(array $rows): string
{
    $parts = [];

    foreach ($rows as $r) {
        $parts[] = (int)$r["collaboration_id"] . ":" . collabStatus($r["status"]);
    }

    sort($parts);

    return md5(implode("|", $parts));
}

/* One cheap query used by BOTH the live check and the page itself,
   so the two signatures can never disagree. */
function collabSignatureFor(mysqli $conn, int $uid): string
{
    $stmt = $conn->prepare(
        "SELECT collaboration_id, status
         FROM collaborations
         WHERE requester_id = ? OR receiver_id = ?"
    );

    if (!$stmt) {
        throw new Exception("prepare failed");
    }

    $stmt->bind_param("ii", $uid, $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return collabSignature($rows);
}

function csrfOk(string $sent): bool
{
    $known = $_SESSION["collab_csrf"] ?? "";

    return $known !== "" && $sent !== "" && hash_equals($known, $sent);
}

/* =========================================================
   JSON ENDPOINTS
   ========================================================= */

/* Live summary (used to refresh the page when something changes) */
if ($_SERVER["REQUEST_METHOD"] === "GET" && $getAction === "summary") {
    // Release the session lock so polling never blocks other requests.
    session_write_close();

    try {
        jsonOut(["ok" => true, "signature" => collabSignatureFor($conn, $user_id)]);
    } catch (Throwable $ex) {
        jsonOut(["ok" => false, "error" => "Unable to check updates."], 500);
    }
}

/* Search students */
if ($_SERVER["REQUEST_METHOD"] === "GET" && $getAction === "search") {
    session_write_close();

    $q = trim((string)($_GET["q"] ?? ""));
    $questionId = (int)($_GET["question_id"] ?? 0);
    $like = "%" . $q . "%";

    try {
        $stmt = $conn->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.student_id,
                    u.course, u.year_level,
                    (
                        SELECT CASE
                            WHEN SUM(c.status = 'Accepted') > 0
                                THEN 'Accepted'
                            WHEN SUM(c.question_id = ? AND c.status = 'Pending') > 0
                                THEN 'Pending'
                            WHEN SUM(c.question_id = ? AND c.requester_id = ? AND c.status = 'Rejected') > 0
                                THEN 'Rejected'
                            ELSE NULL
                        END
                        FROM collaborations c
                        WHERE (c.requester_id = ? AND c.receiver_id = u.id)
                           OR (c.requester_id = u.id AND c.receiver_id = ?)
                    ) AS existing_status,
                    (
                        SELECT c2.collaboration_id
                        FROM collaborations c2
                        WHERE c2.requester_id = ?
                          AND c2.receiver_id = u.id
                          AND c2.question_id = ?
                          AND c2.status = 'Pending'
                        ORDER BY c2.collaboration_id DESC
                        LIMIT 1
                    ) AS cancel_id
             FROM users u
             WHERE u.id <> ?
               AND LOWER(TRIM(u.role)) = 'student'
               AND (
                    u.first_name LIKE ?
                    OR u.last_name LIKE ?
                    OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?
                    OR u.student_id LIKE ?
                    OR u.course LIKE ?
               )
             ORDER BY u.first_name, u.last_name
             LIMIT 20"
        );

        if (!$stmt) {
            throw new Exception("prepare failed");
        }

        $stmt->bind_param(
            "iiiiiiiisssss",
            $questionId,
            $questionId,
            $user_id,
            $user_id,
            $user_id,
            $user_id,
            $questionId,
            $user_id,
            $like,
            $like,
            $like,
            $like,
            $like
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $users = [];

        while ($row = $result->fetch_assoc()) {
            $name = trim(
                trim((string)$row["first_name"]) . " " .
                trim((string)$row["last_name"])
            );

            $users[] = [
                "id" => (int)$row["id"],
                "name" => $name !== "" ? $name : "Student",
                "student_id" => (string)$row["student_id"],
                "course" => (string)($row["course"] ?? ""),
                "year_level" => (string)($row["year_level"] ?? ""),
                "status" => $row["existing_status"] === null
                    ? null
                    : collabStatus($row["existing_status"]),
                /* set only when YOU sent a still-pending request (can be cancelled) */
                "cancel_id" => $row["cancel_id"] === null
                    ? null
                    : (int)$row["cancel_id"]
            ];
        }

        $stmt->close();

        jsonOut(["ok" => true, "users" => $users]);
    } catch (Throwable $ex) {
        jsonOut([
            "ok" => false,
            "error" => "Unable to search students. Make sure the collaborations table exists."
        ], 500);
    }
}

/* Send a collaboration request */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $postAction === "send") {
    if (!csrfOk((string)($_POST["csrf"] ?? ""))) {
        jsonOut([
            "ok" => false,
            "error" => "Security token invalid. Refresh the page."
        ], 403);
    }

    $questionId = (int)($_POST["question_id"] ?? 0);
    $receiverId = (int)($_POST["receiver_id"] ?? 0);

    if ($questionId <= 0 || $receiverId <= 0 || $receiverId === $user_id) {
        jsonOut([
            "ok" => false,
            "error" => "Choose one of your questions and another student."
        ], 422);
    }

    try {
        /* The question must be yours and still active */
        $stmt = $conn->prepare(
            "SELECT question_id
             FROM questions
             WHERE question_id = ? AND user_id = ? AND status = 'Active'
             LIMIT 1"
        );
        if (!$stmt) {
            throw new Exception("prepare failed");
        }
        $stmt->bind_param("ii", $questionId, $user_id);
        $stmt->execute();
        $ownQuestion = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ownQuestion) {
            jsonOut([
                "ok" => false,
                "error" => "You can only ask for help on your own active questions."
            ], 403);
        }

        /* The receiver must be another student */
        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ? AND LOWER(TRIM(role)) = 'student'
             LIMIT 1"
        );
        if (!$stmt) {
            throw new Exception("prepare failed");
        }
        $stmt->bind_param("i", $receiverId);
        $stmt->execute();
        $receiverOk = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$receiverOk) {
            jsonOut([
                "ok" => false,
                "error" => "You can only send a request to another student."
            ], 403);
        }

        /* Block duplicates in either direction, or if already collaborating */
        $stmt = $conn->prepare(
            "SELECT collaboration_id
             FROM collaborations
             WHERE (
                    (requester_id = ? AND receiver_id = ?)
                 OR (requester_id = ? AND receiver_id = ?)
             )
               AND (status = 'Accepted' OR question_id = ?)
             LIMIT 1"
        );
        if (!$stmt) {
            throw new Exception("prepare failed");
        }
        $stmt->bind_param(
            "iiiii",
            $user_id,
            $receiverId,
            $receiverId,
            $user_id,
            $questionId
        );
        $stmt->execute();
        $already = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($already) {
            jsonOut([
                "ok" => false,
                "error" => "You already have a request or collaboration with this student."
            ], 409);
        }

        $stmt = $conn->prepare(
            "INSERT INTO collaborations
                (question_id, requester_id, receiver_id, status)
             VALUES (?, ?, ?, 'Pending')"
        );
        if (!$stmt) {
            throw new Exception("prepare failed");
        }
        $stmt->bind_param("iii", $questionId, $user_id, $receiverId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("insert failed");
        }
        $stmt->close();

        jsonOut(["ok" => true, "message" => "Request sent. Waiting for their reply."]);
    } catch (Throwable $ex) {
        jsonOut([
            "ok" => false,
            "error" => "Could not send the request. Make sure the collaborations table exists."
        ], 500);
    }
}

/* =========================================================
   ACCEPT / REJECT (receiver) AND CANCEL (sender)
   Works with AJAX (ajax=1 -> JSON) and as a normal form
   (redirect) if JavaScript is unavailable.
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $collabId = (int)($_POST["collaboration_id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $ajax = ($_POST["ajax"] ?? "") === "1";
    $result = "failed";

    if (
        csrfOk((string)($_POST["csrf"] ?? "")) &&
        $collabId > 0 &&
        in_array($action, ["accept", "reject", "cancel"], true)
    ) {
        try {
            if ($action === "cancel") {
                // Only the sender can cancel, and only while it is still pending.
                $stmt = $conn->prepare(
                    "DELETE FROM collaborations
                     WHERE collaboration_id = ?
                       AND requester_id = ?
                       AND status = 'Pending'"
                );

                if ($stmt) {
                    $stmt->bind_param("ii", $collabId, $user_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        $result = "cancelled";
                    }

                    $stmt->close();
                }
            } else {
                $newStatus = $action === "accept" ? "Accepted" : "Rejected";

                // Only the receiver can answer, and only while it is still pending.
                $stmt = $conn->prepare(
                    "UPDATE collaborations
                     SET status = ?
                     WHERE collaboration_id = ?
                       AND receiver_id = ?
                       AND status = 'Pending'"
                );

                if ($stmt) {
                    $stmt->bind_param("sii", $newStatus, $collabId, $user_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        $result = strtolower($newStatus);
                    }

                    $stmt->close();
                }
            }
        } catch (Throwable $ex) {
            $result = "failed";
        }
    }

    if ($ajax) {
        $messages = [
            "accepted"  => "Request accepted. The collaboration is now open.",
            "rejected"  => "Request rejected.",
            "cancelled" => "Request cancelled."
        ];

        if ($result === "failed") {
            jsonOut([
                "ok" => false,
                "error" => "That request could not be updated. It may already have been answered or cancelled."
            ], 409);
        }

        jsonOut(["ok" => true, "result" => $result, "message" => $messages[$result]]);
    }

    header("Location: collaboration_stu.php?updated=" . $result);
    exit();
}

/* =========================================================
   PAGE DATA
   ========================================================= */

/* Your active questions (for the "Find a peer" box) */
$myQuestions = [];

try {
    $stmt = $conn->prepare(
        "SELECT question_id, question, subject
         FROM questions
         WHERE user_id = ? AND status = 'Active'
         ORDER BY create_at DESC
         LIMIT 50"
    );

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $myQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Throwable $ex) {
    $myQuestions = [];
}

/* Signature is read BEFORE the list, so if something changes in between
   the next live check simply refreshes again (never the other way round). */
$signature = "";

try {
    $signature = collabSignatureFor($conn, $user_id);
} catch (Throwable $ex) {
    $signature = "";
}

/* Every collaboration this student is part of */
$collabs = [];
$loadError = false;

try {
    $stmt = $conn->prepare(
        "SELECT c.collaboration_id, c.question_id, c.requester_id,
                c.receiver_id, c.status, c.create_at,
                q.question, q.subject, q.question_course,
                q.question_type, q.attachment,
                TRIM(CONCAT_WS(' ', req.first_name, req.last_name)) AS requestor_name,
                req.course AS requestor_course,
                req.student_id AS requestor_sid,
                TRIM(CONCAT_WS(' ', rec.first_name, rec.last_name)) AS receiver_name,
                rec.course AS receiver_course,
                rec.student_id AS receiver_sid
         FROM collaborations c
         INNER JOIN questions q ON c.question_id = q.question_id
         INNER JOIN users req ON c.requester_id = req.id
         INNER JOIN users rec ON c.receiver_id = rec.id
         WHERE c.requester_id = ? OR c.receiver_id = ?
         ORDER BY c.create_at DESC, c.collaboration_id DESC"
    );

    if (!$stmt) {
        throw new Exception("Collaboration query failed.");
    }

    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $collabs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $ex) {
    $loadError = true;
    $collabs = [];
}

/* Numbers shown in the cards AND the tabs (they always match):
   Received = requests sent to you that wait for your reply
   Sent     = requests you sent that wait for a reply
   Active   = accepted collaborations (either direction)
   All      = everything, including rejected (kept as history) */
$receivedCount = 0;
$sentCount = 0;
$activeCount = 0;

foreach ($collabs as $c) {
    $isReceiver = (int)$c["receiver_id"] === $user_id;
    $key = collabStatus($c["status"]);

    if ($key === "accepted") {
        $activeCount++;
    } elseif ($key === "pending") {
        if ($isReceiver) {
            $receivedCount++;
        } else {
            $sentCount++;
        }
    }
}

$totalCollabs = count($collabs);

if ($signature === "") {
    $signature = collabSignature($collabs);
}

$updated = $_GET["updated"] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKMATE - Collaboration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>
    <link rel="stylesheet" href="../../asset/css/question.css">
    <link rel="stylesheet" href="../../asset/css/collaboration_stu.css">
    <link rel="stylesheet"
          href="../../asset/css/collaboration_find.css?v=<?= (int) @filemtime(__DIR__ . '/../../asset/css/collaboration_find.css') ?>">
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

        <a href="collaboration_stu.php" class="menu-item active">
            <span class="menu-icon">♟</span><span>Collaboration</span>
        </a>

        <a href="messages.php" class="menu-item">
            <span class="menu-icon">✉</span><span>Messages</span>
        </a>
    </div>

    <!-- SIDEBAR USER: same markup as questions.php -->
    <div class="sidebar-user">

        <div class="user-avatar">
            <?= e(strtoupper(
                substr(trim($user["first_name"] ?? ""), 0, 1) .
                substr(trim($user["last_name"] ?? ""), 0, 1)
            )) ?>
        </div>

        <div class="user-information">
            <strong>
                <?= e(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")) ?: "Student") ?>
            </strong>

            <span>
                <?= e($user["course"] ?? "") ?><br>
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
            <span class="topbar-current">Collaboration</span>
        </div>
    </header>

    <section class="questions-page">

        <div class="page-heading">
            <span class="heading-label">PEER COLLABORATION</span>
            <h2>Collaboration</h2>
            <p>Find a peer to help you, answer help requests, and open your active collaborations.</p>
        </div>

        <?php if ($loadError): ?>
            <div class="form-message form-message-error">
                Collaboration requests could not be loaded right now.
                Make sure the collaborations table exists.
            </div>
        <?php endif; ?>

        <?php if ($updated === "accepted"): ?>
            <div class="form-message form-message-success">
                Request accepted. The collaboration is now open.
            </div>
        <?php elseif ($updated === "rejected"): ?>
            <div class="form-message form-message-success">
                Request rejected.
            </div>
        <?php elseif ($updated === "cancelled"): ?>
            <div class="form-message form-message-success">
                Request cancelled.
            </div>
        <?php elseif ($updated === "failed"): ?>
            <div class="form-message form-message-error">
                That request could not be updated. It may already have been answered or cancelled.
            </div>
        <?php endif; ?>

        <!-- Live feedback for accept / reject / cancel (filled by JavaScript) -->
        <div id="collabFlash" class="form-message" role="status" aria-live="polite" hidden></div>

        <div class="my-summary">

            <div class="summary-card">
                <span class="summary-label">RECEIVED</span>
                <strong><?= $receivedCount ?></strong>
                <span class="summary-hint">Waiting for your reply</span>
            </div>

            <div class="summary-card">
                <span class="summary-label">SENT</span>
                <strong><?= $sentCount ?></strong>
                <span class="summary-hint">Waiting for a response</span>
            </div>

            <div class="summary-card">
                <span class="summary-label">ACTIVE</span>
                <strong><?= $activeCount ?></strong>
                <span class="summary-hint">Open collaborations</span>
            </div>

        </div>

        <!-- FIND A PEER -->
        <section class="questions-section find-peer">

            <div class="section-heading">
                <div>
                    <span class="heading-label">FIND A PEER</span>
                    <h3>Ask a classmate for help</h3>
                </div>
            </div>

            <div class="find-body">

                <?php if (count($myQuestions) > 0): ?>

                    <label class="find-label" for="findQuestion">
                        1. CHOOSE YOUR QUESTION
                    </label>

                    <select id="findQuestion" class="find-select">
                        <?php foreach ($myQuestions as $mq): ?>
                            <option value="<?= (int)$mq["question_id"] ?>">
                                <?= e(mb_strimwidth((string)$mq["question"], 0, 80, "…", "UTF-8")) ?>
                                <?= !empty($mq["subject"]) ? " (" . e($mq["subject"]) . ")" : "" ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="find-label" for="findSearch">
                        2. FIND A STUDENT
                    </label>

                    <input type="text"
                           id="findSearch"
                           class="find-input"
                           placeholder="Search by name, student ID or course..."
                           autocomplete="off">

                    <div id="findAlert" class="find-alert" role="alert" hidden></div>

                    <div id="findResults" class="find-results" aria-live="polite">
                        <div class="find-note">Loading students...</div>
                    </div>

                <?php else: ?>

                    <div class="find-note find-note-box">
                        You have no active questions yet.
                        <a href="questions.php">Post a question</a> first,
                        then pick a classmate to help you with it.
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <section class="questions-section">

            <div class="section-heading">
                <div>
                    <span class="heading-label">YOUR REQUESTS</span>
                    <h3>Collaboration activity</h3>
                </div>
            </div>

            <div class="filter-tabs" role="tablist" aria-label="Filter collaborations">
                <button type="button" class="filter-tab active" data-filter="all">
                    All (<?= $totalCollabs ?>)
                </button>
                <button type="button" class="filter-tab" data-filter="received">
                    Received (<?= $receivedCount ?>)
                </button>
                <button type="button" class="filter-tab" data-filter="sent">
                    Sent (<?= $sentCount ?>)
                </button>
                <button type="button" class="filter-tab" data-filter="active">
                    Active (<?= $activeCount ?>)
                </button>
            </div>

            <div class="search-box">
                <input type="text" id="searchCollaborations"
                       placeholder="Filter your requests by question or student...">
            </div>

            <div class="questions-list" id="collabList"
                 data-signature="<?= e($signature) ?>">

                <?php if ($totalCollabs > 0): ?>

                    <?php foreach ($collabs as $c): ?>
                        <?php
                        $cid = (int)$c["collaboration_id"];
                        $isReceiver = (int)$c["receiver_id"] === $user_id;
                        $key = collabStatus($c["status"]);

                        if ($isReceiver) {
                            $partnerName = $c["requestor_name"];
                            $partnerCourse = $c["requestor_course"];
                            $partnerSid = $c["requestor_sid"];
                        } else {
                            $partnerName = $c["receiver_name"];
                            $partnerCourse = $c["receiver_course"];
                            $partnerSid = $c["receiver_sid"];
                        }

                        $partnerName = trim((string)$partnerName) !== ""
                            ? $partnerName
                            : "Student";

                        $summary = $isReceiver
                            ? $partnerName . " asked for your help"
                            : "You asked " . $partnerName . " for help";

                        if ($key === "accepted") {
                            $groups = "active";
                        } elseif ($key === "pending") {
                            $groups = $isReceiver ? "received" : "sent";
                        } else {
                            $groups = "";
                        }

                        $dateText = date(
                            "M d, Y h:i A",
                            strtotime((string)$c["create_at"])
                        );
                        ?>

                        <div class="my-question" data-groups="<?= e($groups) ?>">

                            <div class="my-question-header"
                                 role="button"
                                 tabindex="0"
                                 aria-expanded="false"
                                 aria-controls="collab-<?= $cid ?>">

                                <div class="question-icon">♟</div>

                                <div class="question-details">
                                    <h3><?= e($c["question"]) ?></h3>

                                    <div class="question-tags">
                                        <span><?= e($c["subject"]) ?></span>
                                        <span><?= e($c["question_course"]) ?></span>
                                        <span><?= e($c["question_type"]) ?></span>
                                    </div>

                                    <div class="question-meta">
                                        <span><?= e($summary) ?></span>
                                        <span>·</span>
                                        <span><?= e($dateText) ?></span>
                                    </div>

                                    <?php if ($key === "pending"): ?>
                                        <div class="collab-actions">

                                            <?php if ($isReceiver): ?>

                                                <form method="POST" class="collab-form">
                                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="collaboration_id" value="<?= $cid ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" class="collab-btn accept">
                                                        ✓ Accept
                                                    </button>
                                                </form>

                                                <form method="POST" class="collab-form"
                                                      data-confirm="Reject this collaboration request?">
                                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="collaboration_id" value="<?= $cid ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="collab-btn reject">
                                                        ✕ Reject
                                                    </button>
                                                </form>

                                            <?php else: ?>

                                                <form method="POST" class="collab-form"
                                                      data-confirm="Cancel this collaboration request?">
                                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="collaboration_id" value="<?= $cid ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="collab-btn cancel">
                                                        ✕ Cancel request
                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                        </div>
                                    <?php endif; ?>
                                </div>

                                <span class="answer-badge <?= $key ?>">
                                    <?= e(ucfirst($key)) ?>
                                </span>

                                <div class="question-arrow">→</div>
                            </div>

                            <div class="answers-panel" id="collab-<?= $cid ?>" hidden>

                                <div class="answers-label">
                                    <?= $isReceiver ? "REQUESTED BY" : "REQUESTED TO" ?>
                                </div>

                                <div class="answer-item">

                                    <div class="answer-avatar">
                                        <?= e(mb_strtoupper(mb_substr($partnerName, 0, 1, "UTF-8"), "UTF-8")) ?>
                                    </div>

                                    <div class="answer-body">
                                        <div class="answer-author">
                                            <strong><?= e($partnerName) ?></strong>
                                            <span>
                                                <?= e($partnerCourse) ?> · <?= e($partnerSid) ?>
                                            </span>
                                        </div>

                                        <div class="answer-text">
                                            <?php if ($key === "accepted"): ?>
                                                <?php if ($isReceiver): ?>
                                                    You accepted this request. You are now helping
                                                    <?= e($partnerName) ?> with this question.
                                                <?php else: ?>
                                                    <?= e($partnerName) ?> accepted your request and
                                                    is ready to help with this question.
                                                <?php endif; ?>
                                            <?php elseif ($key === "rejected"): ?>
                                                <?php if ($isReceiver): ?>
                                                    You rejected this request.
                                                <?php else: ?>
                                                    <?= e($partnerName) ?> was not able to help with
                                                    this question.
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($isReceiver): ?>
                                                    This request is waiting for your reply.
                                                <?php else: ?>
                                                    Waiting for <?= e($partnerName) ?> to respond.
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                </div>

                                <?php if (!empty($c["attachment"])): ?>
                                    <a class="attachment-link"
                                       href="../../uploads/questions/<?= rawurlencode(basename($c["attachment"])) ?>"
                                       target="_blank" rel="noopener">
                                        View question attachment ↗
                                    </a>
                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    <div class="no-questions" id="filterEmpty" hidden>
                        <div>♟</div>
                        <h3>No matching collaborations</h3>
                        <p>Try a different filter or search.</p>
                    </div>

                <?php else: ?>

                    <div class="no-questions">
                        <div>♟</div>
                        <h3>No collaborations yet</h3>
                        <p>Requests you send or receive will show up here.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </section>
</main>

<script>
    window.CHECKMATE_COLLAB = {
        url: "collaboration_stu.php",
        csrf: <?= json_encode($csrf) ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../asset/js/collaboration_stu.js?v=<?= (int) @filemtime(__DIR__ . '/../../asset/js/collaboration_stu.js') ?>"></script>
</body>
</html>

<?php
$conn->close();
?>