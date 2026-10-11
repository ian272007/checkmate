<?php
// report_api.php - called by report.js
// Handles: status (change a report's status) and delete (delete a report).
// Always answers with JSON: { "success": true/false, "message": "..." }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";   // your connection file

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function reply($success, $message, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(["success" => $success, "message" => $message], $extra));
    exit;
}

// ---------- 1. who is allowed ----------
// (CHANGE 'role' if your login saves the role under a different session key)
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

// ---------- 2. read the form ----------
$action = $_POST["action"] ?? "";
$id     = isset($_POST["id"]) ? (int) $_POST["id"] : 0;

if ($id <= 0) {
    reply(false, "Invalid report id.", [], 400);
}

try {

    // make sure the report exists
    $stmt = $conn->prepare("SELECT report_id FROM reports WHERE report_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$found) {
        reply(false, "Report not found.", [], 404);
    }

    // ---------- CHANGE STATUS ----------
    if ($action === "status") {

        $status = $_POST["status"] ?? "";

        if (!in_array($status, ["Pending", "Review", "Resolved", "Rejected"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        reply(true, "Report updated.", ["status" => $status]);
    }

    // ---------- DELETE ----------
    // only the report is deleted, the reported question stays
    if ($action === "delete") {

        $stmt = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        reply(true, "Report deleted.");
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    reply(false, "Something went wrong. Please try again.", [], 500);
}