<?php
/* =========================================================
   Post a NEW question (used by the Home page "Make a Move").
   Put this file in: view/student/question_post_api.php
   POST fields: csrf, question, subject, question_course,
                question_type, attachment (optional file)
   ========================================================= */

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store");

require_once "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

const MAX_QUESTION_LENGTH = 5000;
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB (same as questions.php)
const UPLOAD_DIR = "../../uploads/questions/";

function reply($success, $message, $extra = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(array_merge(
        ["success" => $success, "message" => $message],
        $extra
    ));
    exit;
}

function loadOptions(mysqli $conn, string $table, string $column): array
{
    $result = $conn->query(
        "SELECT `$column` AS option_name
         FROM `$table`
         WHERE status = 'Active'
         ORDER BY `$column` ASC"
    );

    $options = [];
    while ($row = $result->fetch_assoc()) {
        $options[] = $row["option_name"];
    }
    return $options;
}

function removeUploadedFile(?string $name): void
{
    if ($name === null || $name === "") {
        return;
    }

    $path = UPLOAD_DIR . basename($name);

    if (is_file($path)) {
        @unlink($path);
    }
}

/* ---------- basic checks ---------- */

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

$newFile = null; // file saved during this request (removed if something fails)

try {
    // Who is posting
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION["student_id"]);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me) {
        reply(false, "Student account not found.", [], 403);
    }

    $userId = (int)$me["id"];

    $question = trim((string)($_POST["question"] ?? ""));
    $subject  = trim((string)($_POST["subject"] ?? ""));
    $course   = trim((string)($_POST["question_course"] ?? ""));
    $type     = trim((string)($_POST["question_type"] ?? ""));

    $subjects = loadOptions($conn, "question_subjects", "subject_name");
    $courses  = loadOptions($conn, "question_courses", "course_name");
    $types    = loadOptions($conn, "question_types", "type_name");

    if ($question === "") {
        reply(false, "Please enter your question.", [], 400);
    }

    if (mb_strlen($question, "UTF-8") > MAX_QUESTION_LENGTH) {
        reply(false, "Your question must be " . MAX_QUESTION_LENGTH . " characters or fewer.", [], 400);
    }

    if (!in_array($subject, $subjects, true)) {
        reply(false, "Please select a valid subject.", [], 400);
    }

    if (!in_array($course, $courses, true)) {
        reply(false, "Please select a valid course.", [], 400);
    }

    if (!in_array($type, $types, true)) {
        reply(false, "Please select a valid question type.", [], 400);
    }

    // Optional attachment
    if (
        isset($_FILES["attachment"]) &&
        $_FILES["attachment"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["attachment"]["error"] !== UPLOAD_ERR_OK) {
            reply(false, "The attachment could not be uploaded.", [], 400);
        }

        if ($_FILES["attachment"]["size"] > MAX_FILE_SIZE) {
            reply(false, "Attachment must be 5 MB or smaller.", [], 400);
        }

        $extension = strtolower(
            pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION)
        );

        $allowedExtensions = [
            "pdf", "doc", "docx", "ppt", "pptx", "txt",
            "jpg", "jpeg", "png", "xls", "xlsx", "csv"
        ];

        if (!in_array($extension, $allowedExtensions, true)) {
            reply(
                false,
                "Allowed files: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG, XLS, XLSX, and CSV.",
                [],
                400
            );
        }

        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
            reply(false, "Could not create the upload folder.", [], 500);
        }

        $newFile = bin2hex(random_bytes(16)) . "." . $extension;

        if (!move_uploaded_file(
            $_FILES["attachment"]["tmp_name"],
            UPLOAD_DIR . $newFile
        )) {
            $newFile = null;
            reply(false, "Could not save the uploaded file.", [], 500);
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO questions
         (user_id, question, status, create_at,
          subject, question_course, question_type, attachment)
         VALUES (?, ?, 'Active', NOW(), ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isssss",
        $userId,
        $question,
        $subject,
        $course,
        $type,
        $newFile
    );
    $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();

    reply(true, "Your question has been posted.", ["question_id" => $newId]);

} catch (Throwable $ex) {
    // database failed after the file was saved: don't leave it behind
    removeUploadedFile($newFile);
    reply(false, "Something went wrong. Please try again.", [], 500);
}