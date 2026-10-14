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

$studentCode = (string) $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id FROM users WHERE student_id = ? LIMIT 1"
);

if (!$stmt) {
    respond(["ok" => false, "error" => "Unable to load user."], 500);
}

$stmt->bind_param("s", $studentCode);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$me) {
    respond(["ok" => false, "error" => "Student account not found."], 404);
}

$meId = (int) $me["id"];
$action = $_GET["action"] ?? "";

/* New answers (not yet seen) to the questions I asked */

if ($action === "answers") {
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM answers a
             INNER JOIN questions q ON q.question_id = a.question_id
             WHERE q.user_id = ?
               AND a.user_id <> ?
               AND a.is_seen = 0"
        );

        if (!$stmt) {
            throw new Exception("prepare failed");
        }

        $stmt->bind_param("ii", $meId, $meId);
        $stmt->execute();
        $countRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (int) ($countRow["total"] ?? 0);
        $latest = null;

        if ($total > 0) {
            $stmt = $conn->prepare(
                "SELECT a.answer_id, a.question_id, a.answer,
                        u.first_name, u.last_name
                 FROM answers a
                 INNER JOIN questions q ON q.question_id = a.question_id
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE q.user_id = ?
                   AND a.user_id <> ?
                   AND a.is_seen = 0
                 ORDER BY a.answer_id DESC
                 LIMIT 1"
            );

            if (!$stmt) {
                throw new Exception("prepare failed");
            }

            $stmt->bind_param("ii", $meId, $meId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $name = trim(
                    (string) ($row["first_name"] ?? "") . " " .
                    (string) ($row["last_name"] ?? "")
                );

                $latest = [
                    "answer_id" => (int) $row["answer_id"],
                    "question_id" => (int) $row["question_id"],
                    "answerer_name" => $name !== "" ? $name : "A student",
                    "preview" => mb_substr((string) $row["answer"], 0, 80)
                ];
            }
        }

        respond([
            "ok" => true,
            "me" => $meId,
            "unseen" => $total,
            "latest" => $latest
        ]);
    } catch (Throwable $ex) {
        respond(["ok" => false, "error" => "Unable to check answers."], 500);
    }
}

/* Unseen collaboration requests (received) and accepted requests (sent) */

if ($action === "collab") {
    try {
        $stmt = $conn->prepare(
            "SELECT 'request' AS kind, c.collaboration_id,
                    u.first_name, u.last_name
             FROM collaborations c
             INNER JOIN users u ON u.id = c.requester_id
             WHERE c.receiver_id = ?
               AND c.status = 'Pending'
               AND c.receiver_seen = 0
             UNION ALL
             SELECT 'accepted' AS kind, c.collaboration_id,
                    u.first_name, u.last_name
             FROM collaborations c
             INNER JOIN users u ON u.id = c.receiver_id
             WHERE c.requester_id = ?
               AND c.status = 'Accepted'
               AND c.requester_seen = 0
             ORDER BY collaboration_id DESC"
        );

        if (!$stmt) {
            throw new Exception("prepare failed");
        }

        $stmt->bind_param("ii", $meId, $meId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $requests = 0;
        $accepted = 0;
        $items = [];

        foreach ($rows as $row) {
            if ($row["kind"] === "request") {
                $requests++;
            } else {
                $accepted++;
            }

            if (count($items) < 5) {
                $name = trim(
                    (string) ($row["first_name"] ?? "") . " " .
                    (string) ($row["last_name"] ?? "")
                );

                $items[] = [
                    "kind" => $row["kind"],
                    "collaboration_id" => (int) $row["collaboration_id"],
                    "name" => $name !== "" ? $name : "A student"
                ];
            }
        }

        respond([
            "ok" => true,
            "me" => $meId,
            "requests" => $requests,
            "accepted" => $accepted,
            "items" => $items
        ]);
    } catch (Throwable $ex) {
        respond(["ok" => false, "error" => "Unable to check collaborations."], 500);
    }
}

respond(["ok" => false, "error" => "Unknown action."], 400);