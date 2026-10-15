<?php
// warnings_stream.php - Server-Sent Events: tells the student's browser
// the moment a NEW warning is inserted for them.
session_start();

if (empty($_SESSION["student_id"]) || ($_SESSION["role"] ?? "") !== "student") {
    http_response_code(401);
    exit;
}

$studentCode = (string) $_SESSION["student_id"];

// release the session lock so other pages don't wait on this stream
session_write_close();

require_once __DIR__ . "/../../config/config.php";

ignore_user_abort(false);
set_time_limit(0);

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache, no-store");
header("X-Accel-Buffering: no");
header("Content-Encoding: none");
ini_set("zlib.output_compression", "0");
ini_set("output_buffering", "0");
ini_set("implicit_flush", "1");
if (function_exists("apache_setenv")) { apache_setenv("no-gzip", "1"); }
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

function latestWarningId($conn, $studentCode) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(MAX(w.warning_id), 0) AS last_id
         FROM warnings w
         JOIN users u ON u.id = w.user_id
         WHERE u.student_id = ?"
    );
    $stmt->bind_param("s", $studentCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) $row["last_id"];
}

// After a reconnect the browser sends Last-Event-ID, so a warning that was
// inserted during the 1-second gap between two streams is not missed.
$lastEventId = isset($_SERVER["HTTP_LAST_EVENT_ID"]) ? (int) $_SERVER["HTTP_LAST_EVENT_ID"] : 0;
$baseline    = $lastEventId > 0 ? $lastEventId : latestWarningId($conn, $studentCode);

echo "retry: 1000\n\n";
echo ":" . str_repeat(" ", 2048) . "\n\n";   // padding to force the first flush
echo "id: $baseline\n\n";                    // remembered by the browser as Last-Event-ID
flush();

$endAt = time() + 25;   // end every 25s; EventSource reconnects by itself

while (time() < $endAt && !connection_aborted()) {

    $current = latestWarningId($conn, $studentCode);

    if ($current > $baseline) {
        $baseline = $current;
        echo "id: $current\nevent: warning\ndata: {\"id\": $current}\n\n";
    } else {
        echo ": ping\n\n";   // keeps the connection alive
    }

    flush();
    sleep(1);
}