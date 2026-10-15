<?php
// report_api.php - called by report.js
// Handles: list (live updates), status (change a report's status), archive, restore,
//          warn (warn student + remove the post), ban and unban.
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
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    reply(false, "Not allowed.", [], 403);
}

// ---------- LIVE LIST (called every few seconds by report.js) ----------
if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["action"] ?? "") === "list") {

    // release the session lock so these frequent requests
    // don't make other pages wait
    session_write_close();

    require_once __DIR__ . "/report_data.php";

    try {
        [$reports, $reasons] = loadReports($conn);

        $pending = 0;
        foreach ($reports as $r) {
            if ($r["status"] === "Pending") { $pending++; }
        }

        reply(true, "OK", [
            "reports" => $reports,
            "reasons" => $reasons,
            "pending" => $pending
        ]);
    } catch (Throwable $e) {
        reply(false, "Could not load reports.", [], 500);
    }
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

    // ---------- CHANGE STATUS (from the View popup) ----------
    if ($action === "status") {

        $status = $_POST["status"] ?? "";

        if (!in_array($status, ["Pending", "Review", "Resolved", "Rejected", "Archived"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();

        reply(true, "Report updated.", ["status" => $status]);
    }

    // ---------- ARCHIVE / RESTORE ----------
    // (kept in case the View popup or other code still uses them)
    if ($action === "archive" || $action === "restore") {

        $newStatus = ($action === "archive") ? "Archived" : "Pending";

        $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();

        reply(true, $action === "archive" ? "Report archived." : "Report restored.", [
            "status" => $newStatus
        ]);
    }

    // ---------- WARN the reported student ----------
    // records a warning, removes (hides) the reported question,
    // and marks the report as Resolved - all in one step
    if ($action === "warn") {

        $stmt = $conn->prepare(
            "SELECT r.reason, q.question_id, q.question, u.id AS uid, u.role
             FROM reports r
             JOIN questions q ON q.question_id = r.question_id
             JOIN users u ON u.id = q.user_id
             WHERE r.report_id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            reply(false, "The reported question no longer exists, so there is no one to warn.", [], 404);
        }
        if ($row["role"] !== "student") {
            reply(false, "Only student accounts can be warned.", [], 400);
        }

        // already warned for this report?
        $stmt = $conn->prepare("SELECT 1 FROM warnings WHERE report_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $already = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($already) {
            reply(false, "This report already has a warning.", [], 409);
        }

        $studentId  = (int) $row["uid"];
        $questionId = (int) $row["question_id"];

        $conn->begin_transaction();

        try {
            // 1) save the warning (with a copy of the question text)
            $stmt = $conn->prepare(
                "INSERT INTO warnings (user_id, report_id, question_id, reason, question_text)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiss", $studentId, $id, $questionId, $row["reason"], $row["question"]);
            $stmt->execute();
            $stmt->close();

            // 2) take the post down (kept in the database as evidence)
            $stmt = $conn->prepare("UPDATE questions SET status = 'Removed' WHERE question_id = ?");
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $stmt->close();

            // 3) resolve the report
            $stmt = $conn->prepare("UPDATE reports SET status = 'Resolved' WHERE report_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Throwable $inner) {
            $conn->rollback();
            throw $inner;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM warnings WHERE user_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()["c"];
        $stmt->close();

        reply(true, "Student warned.", [
            "status"        => "Resolved",
            "student_uid"   => $studentId,
            "warning_count" => $count
        ]);
    }

    // ---------- BAN / UNBAN the reported student ----------
    if ($action === "ban" || $action === "unban") {

        // the student who wrote the reported question
        $stmt = $conn->prepare(
            "SELECT u.id, u.role
             FROM reports r
             JOIN questions q ON q.question_id = r.question_id
             JOIN users u ON u.id = q.user_id
             WHERE r.report_id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            reply(false, "The reported student could not be found.", [], 404);
        }
        if ($student["role"] !== "student") {
            reply(false, "Only student accounts can be banned.", [], 400);
        }

        $userStatus = ($action === "ban") ? "Banned" : "Active";
        $studentId  = (int) $student["id"];

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $userStatus, $studentId);
        $stmt->execute();
        $stmt->close();

        $extra = ["user_status" => $userStatus, "student_uid" => $studentId];

        // banning also marks this report as Resolved
        if ($action === "ban") {
            $stmt = $conn->prepare("UPDATE reports SET status = 'Resolved' WHERE report_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $extra["status"] = "Resolved";
        }

        reply(true, $action === "ban" ? "Student banned." : "Student unbanned.", $extra);
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    reply(false, "Something went wrong. Please try again.", [], 500);
}