<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (empty($_SESSION["student_id"])) {
    respond(["ok" => false, "error" => "Please log in again."], 401);
}

require_once __DIR__ . "/../../config/config.php";

const LOCKED_MESSAGE =
    "Messaging is only available between students after a collaboration request is accepted.";

$studentCode = (string) $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, student_id, email, course, role
     FROM users
     WHERE student_id = ?
     LIMIT 1"
);

if (!$stmt) {
    respond(["ok" => false, "error" => "Unable to prepare user query."], 500);
}

$stmt->bind_param("s", $studentCode);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me || strtolower(trim($me["role"] ?? "")) !== "student") {
    respond(["ok" => false, "error" => "Messaging is available to students only."], 403);
}

$meId = (int) $me["id"];

/*
 * "Delete conversation" is per-user: it only hides the chat (and everything
 * up to cleared_message_id) for the user who deleted it. The other student
 * keeps their copy. This table is created automatically.
 */
$conn->query(
    "CREATE TABLE IF NOT EXISTS conversation_hidden (
        user_id INT NOT NULL,
        other_id INT NOT NULL,
        cleared_message_id INT NOT NULL DEFAULT 0,
        hidden_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, other_id)
    ) ENGINE=InnoDB"
);

function displayName(array $user): string
{
    $name = trim(
        (string) ($user["first_name"] ?? "") . " " .
        (string) ($user["last_name"] ?? "")
    );

    return $name !== "" ? $name : "Student";
}

function requireCsrf(): void
{
    $sent = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    $known = $_SESSION["messages_csrf"] ?? "";

    if (!$known || !$sent || !hash_equals($known, $sent)) {
        respond(["ok" => false, "error" => "Security token invalid. Refresh the page."], 403);
    }
}

/*
 * ERD rule: MESSAGES belongs to COLLABORATIONS (collaboration_id).
 * A message can exist only under an ACCEPTED collaboration.
 * The "conversation_id" sent to/from the front-end is a collaboration_id.
 */

/* The accepted collaboration this user is part of (or null) */
function openCollab(mysqli $conn, int $collabId, int $userId): ?array
{
    $stmt = $conn->prepare(
        "SELECT collaboration_id, requester_id, receiver_id
         FROM collaborations
         WHERE collaboration_id = ?
           AND status = 'Accepted'
           AND (requester_id = ? OR receiver_id = ?)
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("iii", $collabId, $userId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/* Newest accepted collaboration between two students (or null) */
function latestAcceptedCollab(mysqli $conn, int $a, int $b): ?int
{
    $stmt = $conn->prepare(
        "SELECT collaboration_id
         FROM collaborations
         WHERE status = 'Accepted'
           AND (
                (requester_id = ? AND receiver_id = ?)
                OR (requester_id = ? AND receiver_id = ?)
           )
         ORDER BY collaboration_id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("iiii", $a, $b, $b, $a);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row["collaboration_id"] : null;
}

function partnerOf(array $collab, int $meId): int
{
    return (int) $collab["requester_id"] === $meId
        ? (int) $collab["receiver_id"]
        : (int) $collab["requester_id"];
}

/* cleared_message_id if this user deleted the chat with $otherId, else null */
function clearedId(mysqli $conn, int $userId, int $otherId): ?int
{
    $stmt = $conn->prepare(
        "SELECT cleared_message_id
         FROM conversation_hidden
         WHERE user_id = ? AND other_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $userId, $otherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row["cleared_message_id"] : null;
}

$action = $_GET["action"] ?? $_POST["action"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requireCsrf();
}

/* UNREAD SUMMARY (used by notify.js) */

if ($action === "unread") {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM messages m
         JOIN collaborations c ON c.collaboration_id = m.collaboration_id
         WHERE m.receiver_id = ?
           AND m.is_read = 0
           AND c.status = 'Accepted'"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to count unread messages."], 500);
    }

    $stmt->bind_param("i", $meId);
    $stmt->execute();
    $countRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int) ($countRow["total"] ?? 0);
    $latest = null;

    if ($total > 0) {
        $stmt = $conn->prepare(
            "SELECT m.message_id, m.collaboration_id, m.message,
                    u.first_name, u.last_name
             FROM messages m
             JOIN collaborations c ON c.collaboration_id = m.collaboration_id
             JOIN users u ON u.id = m.sender_id
             WHERE m.receiver_id = ?
               AND m.is_read = 0
               AND c.status = 'Accepted'
             ORDER BY m.message_id DESC
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("i", $meId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $latest = [
                    "message_id" => (int) $row["message_id"],
                    "conversation_id" => (int) $row["collaboration_id"],
                    "sender_name" => displayName($row),
                    "preview" => mb_substr((string) $row["message"], 0, 80)
                ];
            }
        }
    }

    respond(["ok" => true, "me" => $meId, "unread" => $total, "latest" => $latest]);
}

/* FIND STUDENTS (only students whose collaboration with you was accepted) */

if ($action === "users") {
    $q = trim((string) ($_GET["q"] ?? ""));
    $like = "%" . $q . "%";
    $users = [];

    try {
        $stmt = $conn->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.student_id, u.course
             FROM users u
             WHERE u.id <> ?
               AND LOWER(TRIM(u.role)) = 'student'
               AND (
                   u.first_name LIKE ?
                   OR u.last_name LIKE ?
                   OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?
                   OR u.student_id LIKE ?
                   OR u.email LIKE ?
               )
               AND EXISTS (
                   SELECT 1
                   FROM collaborations cb
                   WHERE cb.status = 'Accepted'
                     AND (
                          (cb.requester_id = ? AND cb.receiver_id = u.id)
                          OR (cb.requester_id = u.id AND cb.receiver_id = ?)
                     )
               )
             ORDER BY u.first_name, u.last_name
             LIMIT 100"
        );

        if (!$stmt) {
            throw new Exception("prepare failed");
        }

        $stmt->bind_param("isssssii", $meId, $like, $like, $like, $like, $like, $meId, $meId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $users[] = [
                "id" => (int) $row["id"],
                "name" => displayName($row),
                "student_id" => $row["student_id"],
                "course" => $row["course"] ?? ""
            ];
        }

        $stmt->close();
    } catch (Throwable $ex) {
        $users = [];
    }

    respond(["ok" => true, "users" => $users]);
}

/* LIST CONVERSATIONS (one per accepted partner, minus the ones you deleted) */

if ($action === "conversations") {
    $items = [];

    try {
        /* Every accepted partner, with their newest accepted collaboration */
        $stmt = $conn->prepare(
            "SELECT MAX(c.collaboration_id) AS conversation_id,
                    CASE WHEN c.requester_id = ? THEN c.receiver_id
                         ELSE c.requester_id END AS other_id
             FROM collaborations c
             WHERE c.status = 'Accepted'
               AND (c.requester_id = ? OR c.receiver_id = ?)
             GROUP BY other_id"
        );

        if (!$stmt) {
            throw new Exception("prepare failed");
        }

        $stmt->bind_param("iii", $meId, $meId, $meId);
        $stmt->execute();
        $pairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $userStmt = $conn->prepare(
            "SELECT id, first_name, last_name, student_id, course
             FROM users
             WHERE id = ? AND LOWER(TRIM(role)) = 'student'
             LIMIT 1"
        );

        /* only messages newer than what this user cleared */
        $lastStmt = $conn->prepare(
            "SELECT message_id, message, create_at
             FROM messages
             WHERE message_id > ?
               AND (
                    (sender_id = ? AND receiver_id = ?)
                    OR (sender_id = ? AND receiver_id = ?)
               )
             ORDER BY message_id DESC
             LIMIT 1"
        );

        $unreadStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM messages
             WHERE message_id > ?
               AND sender_id = ? AND receiver_id = ? AND is_read = 0"
        );

        if (!$userStmt || !$lastStmt || !$unreadStmt) {
            throw new Exception("prepare failed");
        }

        foreach ($pairs as $pair) {
            $otherId = (int) $pair["other_id"];

            $userStmt->bind_param("i", $otherId);
            $userStmt->execute();
            $other = $userStmt->get_result()->fetch_assoc();

            if (!$other) {
                continue;
            }

            $cleared = clearedId($conn, $meId, $otherId);
            $after = $cleared ?? 0;

            $lastStmt->bind_param("iiiii", $after, $meId, $otherId, $otherId, $meId);
            $lastStmt->execute();
            $last = $lastStmt->get_result()->fetch_assoc();

            /* deleted by me and nothing new since -> keep it hidden */
            if ($cleared !== null && !$last) {
                continue;
            }

            $unreadStmt->bind_param("iii", $after, $otherId, $meId);
            $unreadStmt->execute();
            $unread = $unreadStmt->get_result()->fetch_assoc();

            $items[] = [
                "conversation_id" => (int) $pair["conversation_id"],
                "other_id" => $otherId,
                "name" => displayName($other),
                "student_id" => $other["student_id"],
                "course" => $other["course"] ?? "",
                "last_message" => $last["message"] ?? "",
                "last_sent_at" => $last["create_at"] ?? "",
                "unread_count" => (int) ($unread["total"] ?? 0),
                "can_message" => true
            ];
        }

        $userStmt->close();
        $lastStmt->close();
        $unreadStmt->close();

        usort($items, function ($x, $y) {
            return strcmp((string) $y["last_sent_at"], (string) $x["last_sent_at"]);
        });
    } catch (Throwable $ex) {
        respond(["ok" => false, "error" => "Unable to load conversations."], 500);
    }

    respond(["ok" => true, "conversations" => $items]);
}

/* START OR OPEN A CONVERSATION */

if ($action === "start") {
    $otherId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);

    if (!$otherId || $otherId === $meId) {
        respond(["ok" => false, "error" => "Choose another student."], 422);
    }

    $stmt = $conn->prepare(
        "SELECT id FROM users
         WHERE id = ? AND LOWER(TRIM(role)) = 'student'
         LIMIT 1"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to find student."], 500);
    }

    $stmt->bind_param("i", $otherId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$exists) {
        respond(["ok" => false, "error" => "You can only message another student."], 403);
    }

    $collabId = latestAcceptedCollab($conn, $meId, $otherId);

    if ($collabId === null) {
        respond(["ok" => false, "error" => LOCKED_MESSAGE], 403);
    }

    respond(["ok" => true, "conversation_id" => $collabId]);
}

/* LOAD MESSAGES */

if ($action === "messages") {
    $collabId = filter_input(INPUT_GET, "conversation_id", FILTER_VALIDATE_INT);
    $collab = $collabId ? openCollab($conn, $collabId, $meId) : null;

    if (!$collab) {
        respond(["ok" => false, "error" => LOCKED_MESSAGE], 403);
    }

    $partnerId = partnerOf($collab, $meId);
    $after = clearedId($conn, $meId, $partnerId) ?? 0;

    $stmt = $conn->prepare(
        "UPDATE messages
         SET is_read = 1
         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to update read status."], 500);
    }

    $stmt->bind_param("ii", $partnerId, $meId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT m.message_id, m.sender_id, m.message, m.is_read, m.create_at,
                u.first_name AS sender_first_name,
                u.last_name AS sender_last_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.message_id > ?
           AND (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
           )
         ORDER BY m.message_id ASC
         LIMIT 500"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to load messages."], 500);
    }

    $stmt->bind_param("iiiii", $after, $meId, $partnerId, $partnerId, $meId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];

    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            "message_id" => (int) $row["message_id"],
            "sender_id" => (int) $row["sender_id"],
            "sender_name" => displayName([
                "first_name" => $row["sender_first_name"],
                "last_name" => $row["sender_last_name"]
            ]),
            "message_body" => $row["message"],
            "is_read" => (bool) $row["is_read"],
            "sent_at" => $row["create_at"]
        ];
    }

    $stmt->close();

    respond(["ok" => true, "messages" => $messages]);
}

/* SEND MESSAGE */

if ($action === "send") {
    $collabId = filter_input(INPUT_POST, "conversation_id", FILTER_VALIDATE_INT);
    $body = trim((string) ($_POST["message_body"] ?? ""));

    $collab = $collabId ? openCollab($conn, $collabId, $meId) : null;

    if (!$collab) {
        respond(["ok" => false, "error" => LOCKED_MESSAGE], 403);
    }

    if ($body === "" || mb_strlen($body) > 5000) {
        respond(["ok" => false, "error" => "Message must contain 1 to 5000 characters."], 422);
    }

    $receiverId = partnerOf($collab, $meId);

    $stmt = $conn->prepare(
        "SELECT id FROM users
         WHERE id = ? AND LOWER(TRIM(role)) = 'student'
         LIMIT 1"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to verify recipient."], 500);
    }

    $stmt->bind_param("i", $receiverId);
    $stmt->execute();
    $recipient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recipient) {
        respond(["ok" => false, "error" => "Recipient is not a student."], 403);
    }

    $stmt = $conn->prepare(
        "INSERT INTO messages (collaboration_id, sender_id, receiver_id, message)
         VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to prepare message."], 500);
    }

    $cid = (int) $collab["collaboration_id"];
    $stmt->bind_param("iiis", $cid, $meId, $receiverId, $body);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(["ok" => false, "error" => "Unable to send message."], 500);
    }

    $messageId = (int) $stmt->insert_id;
    $stmt->close();

    respond(["ok" => true, "message_id" => $messageId]);
}

/* DELETE CONVERSATION (hidden for this user only) */

if ($action === "delete") {
    $collabId = filter_input(INPUT_POST, "conversation_id", FILTER_VALIDATE_INT);
    $collab = $collabId ? openCollab($conn, $collabId, $meId) : null;

    if (!$collab) {
        respond(["ok" => false, "error" => "Conversation not found."], 404);
    }

    $partnerId = partnerOf($collab, $meId);

    /* newest message id between the two students right now */
    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(message_id), 0) AS last_id
         FROM messages
         WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to delete conversation."], 500);
    }

    $stmt->bind_param("iiii", $meId, $partnerId, $partnerId, $meId);
    $stmt->execute();
    $lastId = (int) ($stmt->get_result()->fetch_assoc()["last_id"] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO conversation_hidden (user_id, other_id, cleared_message_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
             cleared_message_id = VALUES(cleared_message_id),
             hidden_at = CURRENT_TIMESTAMP"
    );

    if (!$stmt) {
        respond(["ok" => false, "error" => "Unable to delete conversation."], 500);
    }

    $stmt->bind_param("iii", $meId, $partnerId, $lastId);

    if (!$stmt->execute()) {
        $stmt->close();
        respond(["ok" => false, "error" => "Unable to delete conversation."], 500);
    }

    $stmt->close();

    /* clear leftover unread so the notification badge goes away too */
    $stmt = $conn->prepare(
        "UPDATE messages
         SET is_read = 1
         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    );

    if ($stmt) {
        $stmt->bind_param("ii", $partnerId, $meId);
        $stmt->execute();
        $stmt->close();
    }

    respond(["ok" => true]);
}

respond(["ok" => false, "error" => "Unknown action."], 400);