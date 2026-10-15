<?php
// archive_question.php - called by question_ad.js when the admin clicks Archive or Restore.
// Nothing is deleted. The question's status just changes: Active <-> Archived.
// Returns JSON: { "success": true/false, "message": "...", "status": "Archived" }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function reply($success, $message, $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(["success" => $success, "message" => $message], $extra));
    exit;
}

// 1. only admins, only POST
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    reply(false, "Not allowed.", 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "Invalid request.", 405);
}

// 2. CSRF check
$token = $_POST["csrf"] ?? "";
if (!isset($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
    reply(false, "Invalid session token. Refresh the page and try again.", 403);
}

// 3. validate id and action
$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
if ($id <= 0) {
    reply(false, "Invalid question id.", 400);
}

$action = $_POST["action"] ?? "";
if ($action !== "archive" && $action !== "restore") {
    reply(false, "Invalid action.", 400);
}

$newStatus = ($action === "archive") ? "Archived" : "Active";

try {
    $stmt = $conn->prepare("SELECT status FROM questions WHERE question_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        reply(false, "Question not found.", 404);
    }

    // already in the wanted state, nothing to do
    if ($row["status"] === $newStatus) {
        reply(true, "Question is already " . strtolower($newStatus) . ".", 200, ["status" => $newStatus]);
    }

    // only archived questions can be restored
    if ($action === "restore" && $row["status"] !== "Archived") {
        reply(false, "Only archived questions can be restored.", 400);
    }

    $stmt = $conn->prepare("UPDATE questions SET status = ? WHERE question_id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    $stmt->close();

    reply(
        true,
        $action === "archive" ? "Question archived." : "Question restored.",
        200,
        ["status" => $newStatus]
    );

} catch (Throwable $e) {
    reply(false, "Could not update the question.", 500);
}