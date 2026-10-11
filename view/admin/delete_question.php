<?php
// delete_question.php - called by question_ad.js when the admin clicks Delete.
// Returns JSON: { "success": true/false, "message": "..." }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";   // your connection file (CHANGE the name/path if it is different)

// make mysqli throw real errors so the try/catch + rollback below works
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// folder where uploaded attachments are saved (CHANGE this to your real upload folder)
$uploadDir = __DIR__ . "/../../uploads/";

function reply($success, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(["success" => $success, "message" => $message]);
    exit;
}

// 1. only admins, only POST
// (CHANGE 'role' if your login saves the role under a different session key)
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

// 3. validate id
$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
if ($id <= 0) {
    reply(false, "Invalid question id.", 400);
}

try {
    // collect attachment file names first so the files can be removed after
    $files = [];

    $stmt = $conn->prepare("SELECT attachment FROM questions WHERE question_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        reply(false, "Question not found.", 404);
    }
    $row = $res->fetch_assoc();
    if (!empty($row["attachment"])) {
        $files[] = $row["attachment"];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT attachment FROM answers WHERE question_id = ? AND attachment IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $files[] = $r["attachment"];
    }
    $stmt->close();

    $conn->begin_transaction();

    // collaborations have no foreign key to questions, so delete them manually
    // (their messages are removed automatically by ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM collaborations WHERE question_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // answers are removed by ON DELETE CASCADE,
    // reports keep existing but question_id becomes NULL (ON DELETE SET NULL)
    $stmt = $conn->prepare("DELETE FROM questions WHERE question_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // remove the uploaded files (basename() stops path tricks like ../../)
    foreach ($files as $file) {
        $path = $uploadDir . basename($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    reply(true, "Question deleted.");

} catch (Throwable $e) {
    $conn->rollback();
    reply(false, "Could not delete the question.", 500);
}