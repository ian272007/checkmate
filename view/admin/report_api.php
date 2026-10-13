<?php
// report_api.php - called by report.js
// Handles: list (live updates), status (change a report's status) and delete.
// Always answers with JSON: { "success": true/false, "message": "..." }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";   // your connection file

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// folder where question attachments are saved (same one questions.php uses)
$uploadDir = __DIR__ . "/../../uploads/questions/";

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

    // make sure the report exists (and remember which question it points to)
    $stmt = $conn->prepare("SELECT report_id, question_id FROM reports WHERE report_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$report) {
        reply(false, "Report not found.", [], 404);
    }

    $questionId = (int) ($report["question_id"] ?? 0);

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
    // deletes the report AND the reported question
    // (its answers, collaborations, other reports and uploaded files too)
    if ($action === "delete") {

        $files = [];

        if ($questionId > 0) {

            // collect attachment file names first so the files can be removed after
            $stmt = $conn->prepare("SELECT attachment FROM questions WHERE question_id = ?");
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && !empty($row["attachment"])) {
                $files[] = $row["attachment"];
            }

            $stmt = $conn->prepare(
                "SELECT attachment FROM answers
                 WHERE question_id = ? AND attachment IS NOT NULL"
            );
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $files[] = $r["attachment"];
            }
            $stmt->close();
        }

        $conn->begin_transaction();

        if ($questionId > 0) {

            // collaborations have no foreign key to questions, so delete them manually
            // (their messages are removed by ON DELETE CASCADE)
            $stmt = $conn->prepare("DELETE FROM collaborations WHERE question_id = ?");
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $stmt->close();

            // every report about this question (the one clicked and any others)
            $stmt = $conn->prepare("DELETE FROM reports WHERE question_id = ?");
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $stmt->close();

            // the question itself (answers are removed by ON DELETE CASCADE)
            $stmt = $conn->prepare("DELETE FROM questions WHERE question_id = ?");
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $stmt->close();
        }

        // covers reports whose question was already deleted earlier
        $stmt = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
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

        reply(true, "Report and question deleted.");
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
    reply(false, "Something went wrong. Please try again.", [], 500);
}