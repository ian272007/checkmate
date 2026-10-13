<?php
session_start();
header("Content-Type: application/json");

require_once "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function reply($success, $message, $extra = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(array_merge(
        ["success" => $success, "message" => $message],
        $extra
    ));
    exit;
}

// Must match $reportReasons in questions.php
$allowedReasons = [
    "Inappropriate question",
    "Incorrect subject",
    "Spam",
    "Inappropriate Answer",
    "Harassment or Bullying",
    "Misleading Information"
];

if (empty($_SESSION["student_id"])) {
    reply(false, "Please log in first.", [], 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "Invalid request.", [], 405);
}

$token = (string)($_POST["csrf"] ?? "");
if (
    empty($_SESSION["questions_csrf"]) ||
    $token === "" ||
    !hash_equals($_SESSION["questions_csrf"], $token)
) {
    reply(false, "Security token invalid. Please refresh the page.", [], 403);
}

$questionId = (int)($_POST["question_id"] ?? 0);
$reason = trim((string)($_POST["reason"] ?? ""));

if ($questionId <= 0 || !in_array($reason, $allowedReasons, true)) {
    reply(false, "Invalid report.", [], 400);
}

try {
    // Who is reporting
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION["student_id"]);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me) {
        reply(false, "Student account not found.", [], 403);
    }

    $reporterId = (int)$me["id"];

    // Question must exist, be active, and belong to someone else
    $stmt = $conn->prepare(
        "SELECT question_id FROM questions
         WHERE question_id = ? AND status = 'Active' AND user_id <> ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $questionId, $reporterId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ok) {
        reply(false, "You can't report this question.", [], 404);
    }

    // One report per student per question
    $stmt = $conn->prepare(
        "SELECT report_id FROM reports
         WHERE question_id = ? AND reporter_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $questionId, $reporterId);
    $stmt->execute();
    $dup = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
        reply(false, "You already reported this question.", ["already" => true]);
    }

    $stmt = $conn->prepare(
        "INSERT INTO reports (question_id, reporter_id, reason, details, status, create_at)
         VALUES (?, ?, ?, '', 'Pending', NOW())"
    );
    $stmt->bind_param("iis", $questionId, $reporterId, $reason);
    $stmt->execute();
    $stmt->close();

    reply(true, "Thanks! Your report was sent to the admin.");

} catch (Throwable $ex) {
    reply(false, "Could not send your report. Please try again.", [], 500);
}