<?php
// warnings_api.php
//   GET  ?action=check -> does this student have an unseen warning? (polled by notify.js)
//   POST               -> the student confirms ("I Understand") their warning(s)
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function respond($ok, $message = "", $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(["success" => $ok, "message" => $message], $extra));
    exit;
}

if (empty($_SESSION["student_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    respond(false, "Please log in again.", 401);
}

require_once __DIR__ . "/../../config/config.php";

$studentCode = (string) $_SESSION["student_id"];

// ---------- CHECK (called every few seconds) ----------
if ($_SERVER["REQUEST_METHOD"] === "GET") {

    if (($_GET["action"] ?? "") !== "check") {
        respond(false, "Invalid request.", 400);
    }

    if (empty($_SESSION["csrf_student"])) {
        $_SESSION["csrf_student"] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION["csrf_student"];

    // don't make other pages wait on this frequent request
    session_write_close();

    try {
        // how many warnings in total, and how many are not confirmed yet
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total, SUM(w.acknowledged = 0) AS pending
             FROM warnings w
             JOIN users u ON u.id = w.user_id
             WHERE u.student_id = ?"
        );
        $stmt->bind_param("s", $studentCode);
        $stmt->execute();
        $counts = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $pending = (int) ($counts["pending"] ?? 0);
        $total   = (int) ($counts["total"] ?? 0);
        $latest  = null;

        if ($pending > 0) {
            $stmt = $conn->prepare(
                "SELECT w.reason, w.question_text
                 FROM warnings w
                 JOIN users u ON u.id = w.user_id
                 WHERE u.student_id = ? AND w.acknowledged = 0
                 ORDER BY w.warning_id DESC
                 LIMIT 1"
            );
            $stmt->bind_param("s", $studentCode);
            $stmt->execute();
            $latest = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        respond(true, "OK", 200, [
            "pending" => $pending,
            "total"   => $total,
            "latest"  => $latest,
            "csrf"    => $csrf
        ]);
    } catch (Throwable $e) {
        respond(false, "Something went wrong.", 500);
    }
}

// ---------- CONFIRM ("I Understand") ----------
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request.", 405);
}

$token = $_POST["csrf"] ?? "";
if (empty($_SESSION["csrf_student"]) || !hash_equals($_SESSION["csrf_student"], $token)) {
    respond(false, "Invalid session token. Refresh the page.", 403);
}

// only ever touches the logged-in student's own warnings
$stmt = $conn->prepare(
    "UPDATE warnings w
     JOIN users u ON u.id = w.user_id
     SET w.acknowledged = 1
     WHERE u.student_id = ? AND w.acknowledged = 0"
);
if (!$stmt) {
    respond(false, "Something went wrong.", 500);
}
$stmt->bind_param("s", $studentCode);
$stmt->execute();
$stmt->close();

respond(true, "OK");