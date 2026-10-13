<?php
// questions_live_api.php - called every few seconds by live_questions.js
// Returns the ids of all Active questions: { "success": true, "ids": [13, 15, ...] }

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store");

require_once "../../config/config.php";   // same connection file the other student pages use

// only logged-in students
if (empty($_SESSION["student_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

// release the session lock so these frequent requests don't make other pages wait
session_write_close();

try {
    $ids = [];
    $result = $conn->query("SELECT question_id FROM questions WHERE status = 'Active'");

    if (!$result) {
        throw new Exception("Query failed.");
    }

    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row["question_id"];
    }

    echo json_encode(["success" => true, "ids" => $ids]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Could not load questions."]);
}