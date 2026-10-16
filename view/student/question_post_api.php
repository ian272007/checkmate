<?php
/* =========================================================
   Post a NEW question (used by the Questions page and the
   Home page "Make a Move").
   Put this file in: view/student/question_post_api.php
   POST fields: csrf, question, subject, question_type,
                attachment (optional file)
   The program is NOT taken from the form: it is always the
   student's own registered program (users.course), and the
   subject must be linked to that program.
   Failed answers may include "field" so the page can focus it.
   ========================================================= */

session_start();
header("Content-Type: application/json");
header("Cache-Control: no-store");

require_once "../../config/config.php";
require_once "../../config/program_helpers.php";

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
    // Who is posting (and which program they are registered in)
    $stmt = $conn->prepare("SELECT id, course FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION["student_id"]);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me) {
        reply(false, "Student account not found.", [], 403);
    }

    $userId = (int)$me["id"];

    // The program is always the student's own (any value in the form is ignored)
    $program = trim((string)$me["course"]);

    $question = trim((string)($_POST["question"] ?? ""));
    $subject  = trim((string)($_POST["subject"] ?? ""));
    $type     = trim((string)($_POST["question_type"] ?? ""));

    $types = loadOptions($conn, "question_types", "type_name");

    if ($question === "") {
        reply(false, "Please enter your question.", ["field" => "question"], 400);
    }

    if (mb_strlen($question, "UTF-8") > MAX_QUESTION_LENGTH) {
        reply(false, "Your question must be " . MAX_QUESTION_LENGTH . " characters or fewer.", ["field" => "question"], 400);
    }

    // Missing / unknown / archived program: refuse, never assign a default
    $programState = checkStudentProgram($conn, $program);

    if ($programState !== "ok") {
        reply(false, programProblemMessage($programState), [], 400);
    }

    // The subject must be linked to THIS student's program
    $subjects = loadSubjectsForProgram($conn, $program);

    if (count($subjects) === 0) {
        reply(false, "No subjects are set up for your program yet. Please contact the administrator.", [], 400);
    }

    if (!in_array($subject, $subjects, true)) {
        reply(false, "Please select a valid subject for your program.", ["field" => "subject"], 400);
    }

    if (!in_array($type, $types, true)) {
        reply(false, "Please select a valid question type.", ["field" => "question_type"], 400);
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

    // the questions table still calls this column question_course
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
        $program,
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