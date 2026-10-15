<?php
// student_status_api.php - called by student.js when the admin clicks Ban or Unban.
//   ban   -> user becomes Banned + all their Active questions become Archived
//   unban -> user becomes Active + the questions archived BY THE BAN become Active again
// Questions an admin archived by hand are never touched by an unban.
// Returns JSON: { "success": true/false, "message": "...", "status": "Banned", "affected": 3 }

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

// 2. CSRF check (same token as archive_question.php)
$token = $_POST["csrf"] ?? "";
if (!isset($_SESSION["csrf"]) || !hash_equals($_SESSION["csrf"], $token)) {
    reply(false, "Invalid session token. Refresh the page and try again.", 403);
}

// 3. validate the id and the action
$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
if ($id <= 0) {
    reply(false, "Invalid student id.", 400);
}

$action = $_POST["action"] ?? "";
if ($action !== "ban" && $action !== "unban") {
    reply(false, "Invalid action.", 400);
}

try {
    // everything below happens together, or not at all
    $conn->begin_transaction();

    // only student accounts can be banned (never admins)
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ? AND role = \"student\" FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $conn->rollback();
        reply(false, "Student not found.", 404);
    }

    $affected = 0;

    if ($action === "ban") {

        $stmt = $conn->prepare("UPDATE users SET status = \"Banned\" WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // hide every question that is still Active (already archived ones are left alone)
        $stmt = $conn->prepare(
            "UPDATE questions SET status = \"Archived\", archived_by_ban = 1
             WHERE user_id = ? AND status = \"Active\""
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        $newStatus = "Banned";

    } else {

        $stmt = $conn->prepare("UPDATE users SET status = \"Active\" WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // bring back ONLY the questions that the ban archived
        $stmt = $conn->prepare(
            "UPDATE questions SET status = \"Active\", archived_by_ban = 0
             WHERE user_id = ? AND archived_by_ban = 1"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        $newStatus = "Active";
    }

    $conn->commit();

    reply(true, $action === "ban" ? "Student banned." : "Student unbanned.", 200, [
        "status"   => $newStatus,
        "affected" => $affected
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    reply(false, "Could not update the student.", 500);
}