<?php
// question_type_api.php - called by question_type.js
// Handles: add, update, delete for question types.
// Always answers with JSON: { "success": true/false, "message": "...", "item": {...} }

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
$name   = trim($_POST["name"] ?? "");
$status = $_POST["status"] ?? "Active";

// ---------- helper: how many questions use this type ----------
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

        // no duplicates
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

    // ---------- UPDATE ----------
    if ($action === "update") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }
        if ($name === "" || mb_strlen($name) > 100) {
            reply(false, "Please enter a type name (max 100 characters).", [], 400);
        }
        if (!in_array($status, ["Active", "Inactive"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        // find the current name
        $stmt = $conn->prepare("SELECT type_name AS name FROM question_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            reply(false, "Question type not found.", [], 404);
        }
        $oldName = $res->fetch_assoc()["name"];
        $stmt->close();

        // no duplicates (ignoring itself)
        $stmt = $conn->prepare("SELECT id FROM question_types WHERE type_name = ? AND id <> ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            reply(false, "Another question type already uses that name.", [], 409);
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE question_types SET type_name = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $status, $id);
        $stmt->execute();
        $stmt->close();

        // questions store the type name as text, so keep them in sync when it is renamed
        if ($oldName !== $name) {
            $stmt = $conn->prepare("UPDATE questions SET question_type = ? WHERE question_type = ?");
            $stmt->bind_param("ss", $name, $oldName);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();

        reply(true, "Question type updated.", [
            "item" => ["id" => $id, "name" => $name, "status" => $status, "used" => countUsed($conn, $name)]
        ]);
    }

    // ---------- DELETE ----------
    if ($action === "delete") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }

        $stmt = $conn->prepare("DELETE FROM question_types WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) {
            reply(false, "Question type not found.", [], 404);
        }

        reply(true, "Question type deleted.");
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    $conn->rollback();
    reply(false, "Something went wrong. Please try again.", [], 500);
}