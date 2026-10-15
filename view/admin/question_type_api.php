<?php
// question_type_api.php - called by question_type.js
// Handles: add, archive, restore for question types.
// Always answers with JSON: { "success": true/false, "message": "...", ... }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function reply($success, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(["success" => $success, "message" => $message], $extra));
    exit;
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    reply(false, "Not allowed.", [], 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "Invalid request.", [], 405);
}

$token = $_POST["csrf"] ?? "";
if (!isset($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
    reply(false, "Invalid session token. Refresh the page and try again.", [], 403);
}

$action = $_POST["action"] ?? "";
$id     = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
$name   = trim($_POST["name"] ?? "");
$status = $_POST["status"] ?? "Active";

function countUsed($conn, $name) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM questions WHERE question_type = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()["c"];
    $stmt->close();
    return $count;
}

try {

    // ---------- ADD ----------
    if ($action === "add") {

        if ($name === "" || mb_strlen($name) > 100) {
            reply(false, "Please enter a type name (max 100 characters).", [], 400);
        }
        if (!in_array($status, ["Active", "Inactive"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        $stmt = $conn->prepare("SELECT id FROM question_types WHERE type_name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            reply(false, "That question type already exists.", [], 409);
        }

        $stmt = $conn->prepare("INSERT INTO question_types (type_name, status) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $status);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        reply(true, "Question type added.", [
            "item" => ["id" => (int) $newId, "name" => $name, "status" => $status, "used" => countUsed($conn, $name)]
        ]);
    }

    // ---------- ARCHIVE / RESTORE ----------
    if ($action === "archive" || $action === "restore") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }

        $stmt = $conn->prepare("SELECT id FROM question_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$found) {
            reply(false, "Question type not found.", [], 404);
        }

        $newStatus = ($action === "archive") ? "Archived" : "Active";

        $stmt = $conn->prepare("UPDATE question_types SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();

        reply(true, $action === "archive" ? "Question type archived." : "Question type restored.", [
            "status" => $newStatus
        ]);
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    reply(false, "Something went wrong. Please try again.", [], 500);
}