<?php
session_start();
header("Content-Type: application/json");

require_once "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function reply($success, $message, $extra = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(array_merge(
        ["success" => $success, "message" => $message],
        $extra
    ));
    exit;
}

// Saved in reports.reason (the column cannot be empty)
const REPORT_REASON = "Student report";

if (empty($_SESSION["student_id"])) {
    reply(false, "Please log in first.", [], 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(false, "Invalid request.", [], 405);
}

$token = (string)($_POST["csrf"] ?? "");
if (
    empty($_SESSION["questions_csrf"]) ||
    $token === "" ||
    !hash_equals($_SESSION["questions_csrf"], $token)
) {
    reply(false, "Security token invalid. Please refresh the page.", [], 403);
}

$questionId = (int)($_POST["question_id"] ?? 0);
$details = trim((string)($_POST["details"] ?? ""));

if ($questionId <= 0) {
    reply(false, "Invalid report.", [], 400);
}

if ($details === "") {
    reply(false, "Please write a description.", [], 400);
}

if (mb_strlen($details, "UTF-8") > 1000) {
    reply(false, "Description must be 1000 characters or fewer.", [], 400);
}

// ---------- check the optional image BEFORE saving anything ----------
$hasImage = isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE;
$imageExt = "";

if ($hasImage) {
    if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
        reply(false, "The image could not be uploaded.", [], 400);
    }

    if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
        reply(false, "Image must be 5 MB or smaller.", [], 400);
    }

    // check the real file type, not just the name
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES["image"]["tmp_name"]);

    $allowedMime = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedMime[$mime])) {
        reply(false, "Only JPG, PNG or WEBP images are allowed.", [], 400);
    }

    $imageExt = $allowedMime[$mime];
}

$imageName = null;

try {
    // Who is reporting
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION["student_id"]);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me) {
        reply(false, "Student account not found.", [], 403);
    }

    $reporterId = (int)$me["id"];

    // Question must exist, be active, and belong to someone else
    $stmt = $conn->prepare(
        "SELECT question_id FROM questions
         WHERE question_id = ? AND status = 'Active' AND user_id <> ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $questionId, $reporterId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ok) {
        reply(false, "You can't report this question.", [], 404);
    }

    // One report per student per question
    $stmt = $conn->prepare(
        "SELECT report_id FROM reports
         WHERE question_id = ? AND reporter_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $questionId, $reporterId);
    $stmt->execute();
    $dup = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
        reply(false, "You already reported this question.", ["already" => true]);
    }

    // ---------- save the image ----------
    if ($hasImage) {
        $folder = "../../uploads/reports/";

        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            reply(false, "Could not create the upload folder.", [], 500);
        }

        $imageName = bin2hex(random_bytes(16)) . "." . $imageExt;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $folder . $imageName)) {
            $imageName = null;
            reply(false, "Could not save the image.", [], 500);
        }
    }

    // ---------- save the report ----------
    $reason = REPORT_REASON;

    $stmt = $conn->prepare(
        "INSERT INTO reports
         (question_id, reporter_id, reason, details, image, status, create_at)
         VALUES (?, ?, ?, ?, ?, 'Pending', NOW())"
    );
    $stmt->bind_param(
        "iisss",
        $questionId,
        $reporterId,
        $reason,
        $details,
        $imageName
    );
    $stmt->execute();
    $stmt->close();

    reply(true, "Thanks! Your report was sent to the admin.");

} catch (Throwable $ex) {
    // remove the image if the report could not be saved
    if ($imageName !== null) {
        $path = "../../uploads/reports/" . $imageName;
        if (is_file($path)) {
            unlink($path);
        }
    }

    reply(false, "Could not send your report. Please try again.", [], 500);
}