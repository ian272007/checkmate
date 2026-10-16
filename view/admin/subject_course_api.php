<?php
// subject_course_api.php - called by subject_course.js
// Handles: add, update, archive, restore, delete for programs ("course") and subjects,
// plus set_programs (which programs offer a subject, with optional year level / semester).
// Always answers with JSON: { "success": true/false, "message": "...", "item": {...} }

session_start();
header("Content-Type: application/json");

require "../../config/config.php";   // your connection file
require_once "../../config/program_helpers.php";

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

// ---------- 2. which table ----------
// only these names are ever put into the SQL, so nothing from the user is trusted
$types = [
    "course"  => ["table" => "question_courses",  "col" => "course_name",  "qcol" => "question_course"],
    "subject" => ["table" => "question_subjects", "col" => "subject_name", "qcol" => "subject"],
];

$type = $_POST["type"] ?? "";
if (!isset($types[$type])) {
    reply(false, "Invalid type.", [], 400);
}

$table = $types[$type]["table"];
$col   = $types[$type]["col"];
$qcol  = $types[$type]["qcol"];   // the column in `questions` that stores this name

$action = $_POST["action"] ?? "";
$id     = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
$name   = trim($_POST["name"] ?? "");
$status = $_POST["status"] ?? "Active";

// "course" is called "program" in the screens
$word = ($type === "course") ? "program" : "subject";

// ---------- helper: how many questions use this name ----------
function countUsed($conn, $qcol, $name) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM questions WHERE $qcol = ?");
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
            reply(false, "Please enter a $word name (max 100 characters).", [], 400);
        }
        if (!in_array($status, ["Active", "Inactive"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        // no duplicates
        $stmt = $conn->prepare("SELECT id FROM $table WHERE $col = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            reply(false, "That $word already exists.", [], 409);
        }

        $stmt = $conn->prepare("INSERT INTO $table ($col, status) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $status);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        reply(true, ucfirst($word) . " added.", [
            "item" => ["id" => (int) $newId, "name" => $name, "status" => $status, "used" => countUsed($conn, $qcol, $name)]
        ]);
    }

    // ---------- UPDATE ----------
    if ($action === "update") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }
        if ($name === "" || mb_strlen($name) > 100) {
            reply(false, "Please enter a $word name (max 100 characters).", [], 400);
        }
        if (!in_array($status, ["Active", "Inactive"], true)) {
            reply(false, "Invalid status.", [], 400);
        }

        // find the current name
        $stmt = $conn->prepare("SELECT $col AS name FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            reply(false, ucfirst($word) . " not found.", [], 404);
        }
        $oldName = $res->fetch_assoc()["name"];
        $stmt->close();

        // no duplicates (ignoring itself)
        $stmt = $conn->prepare("SELECT id FROM $table WHERE $col = ? AND id <> ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            reply(false, "Another $word already uses that name.", [], 409);
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE $table SET $col = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $status, $id);
        $stmt->execute();
        $stmt->close();

        // questions store the name as text, so keep them in sync when it is renamed
        if ($oldName !== $name) {
            $stmt = $conn->prepare("UPDATE questions SET $qcol = ? WHERE $qcol = ?");
            $stmt->bind_param("ss", $name, $oldName);
            $stmt->execute();
            $stmt->close();

            // students store their registered program as text too (users.course),
            // so a renamed program must be renamed there as well or those
            // students would suddenly have "no valid program"
            if ($type === "course") {
                $stmt = $conn->prepare("UPDATE users SET course = ? WHERE course = ?");
                $stmt->bind_param("ss", $name, $oldName);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();

        reply(true, ucfirst($word) . " updated.", [
            "item" => ["id" => $id, "name" => $name, "status" => $status, "used" => countUsed($conn, $qcol, $name)]
        ]);
    }

    // ---------- ARCHIVE / RESTORE ----------
    if ($action === "archive" || $action === "restore") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }

        $stmt = $conn->prepare("SELECT id FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$found) {
            reply(false, ucfirst($word) . " not found.", [], 404);
        }

        $newStatus = ($action === "archive") ? "Archived" : "Active";

        $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        $stmt->close();

        reply(true, ucfirst($word) . ($action === "archive" ? " archived." : " restored."), [
            "status" => $newStatus
        ]);
    }

    // ---------- SET PROGRAMS OF A SUBJECT ----------
    // programs = JSON list of { course_id, year_level, semester }
    // Replaces ONLY this subject's links. Nothing else is touched.
    if ($action === "set_programs") {

        if ($type !== "subject") {
            reply(false, "Invalid type.", [], 400);
        }
        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }

        $stmt = $conn->prepare("SELECT id FROM question_subjects WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$found) {
            reply(false, "Subject not found.", [], 404);
        }

        $list = json_decode((string) ($_POST["programs"] ?? "[]"), true);
        if (!is_array($list)) {
            reply(false, "Invalid program list.", [], 400);
        }

        // validate every entry on the server; one entry per program
        $clean = [];

        foreach ($list as $item) {
            if (!is_array($item)) {
                reply(false, "Invalid program list.", [], 400);
            }

            $courseId = (int) ($item["course_id"] ?? 0);
            $year     = trim((string) ($item["year_level"] ?? ""));
            $sem      = trim((string) ($item["semester"] ?? ""));

            if ($courseId <= 0) {
                reply(false, "Invalid program.", [], 400);
            }
            if ($year !== "" && !in_array($year, PROGRAM_YEAR_LEVELS, true)) {
                reply(false, "Invalid year level.", [], 400);
            }
            if ($sem !== "" && !in_array($sem, PROGRAM_SEMESTERS, true)) {
                reply(false, "Invalid semester.", [], 400);
            }

            $clean[$courseId] = [
                "year_level" => $year,
                "semester"   => $sem
            ];
        }

        // every program id must really exist
        $check = $conn->prepare("SELECT id FROM question_courses WHERE id = ?");
        foreach (array_keys($clean) as $courseId) {
            $check->bind_param("i", $courseId);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $check->close();
                reply(false, "One of the selected programs does not exist.", [], 400);
            }
        }
        $check->close();

        $conn->begin_transaction();

        $stmt = $conn->prepare("DELETE FROM course_subjects WHERE subject_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "INSERT INTO course_subjects (course_id, subject_id, year_level, semester)
             VALUES (?, ?, ?, ?)"
        );

        $mappings = [];

        foreach ($clean as $courseId => $place) {
            $yearValue = ($place["year_level"] === "") ? null : $place["year_level"];
            $semValue  = ($place["semester"] === "") ? null : $place["semester"];

            $stmt->bind_param("iiss", $courseId, $id, $yearValue, $semValue);
            $stmt->execute();

            $mappings[] = [
                "course_id"  => (int) $courseId,
                "year_level" => $place["year_level"],
                "semester"   => $place["semester"]
            ];
        }

        $stmt->close();
        $conn->commit();

        reply(true, "Program assignments saved.", ["mappings" => $mappings]);
    }

    // ---------- DELETE ----------
    if ($action === "delete") {

        if ($id <= 0) {
            reply(false, "Invalid id.", [], 400);
        }

        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) {
            reply(false, ucfirst($word) . " not found.", [], 404);
        }

        reply(true, ucfirst($word) . " deleted.");
    }

    reply(false, "Unknown action.", [], 400);

} catch (Throwable $e) {
    $conn->rollback();
    reply(false, "Something went wrong. Please try again.", [], 500);
}