<?php
/* =========================================================
   Edit or delete the logged-in student's OWN question.
   Put this file in: view/student/question_manage_api.php
   POST fields: action (edit|delete), question_id, csrf
   edit also takes: question, subject, question_course,
                    question_type, attachment (file),
                    remove_attachment (1)
   ========================================================= */

session_start();
header("Content-Type: application/json");

require_once "../../config/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

const MAX_QUESTION_LENGTH = 5000;
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

$action = (string)($_POST["action"] ?? "");
$questionId = (int)($_POST["question_id"] ?? 0);

if (!in_array($action, ["edit", "delete"], true) || $questionId <= 0) {
    reply(false, "Invalid request.", [], 400);
}

$newFile = null; // a file saved during this request (cleaned up if something fails)

try {
    // Who is asking
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION["student_id"]);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$me) {
        reply(false, "Student account not found.", [], 403);
    }

    $userId = (int)$me["id"];

    // The question must exist, be active, and belong to this student
    $stmt = $conn->prepare(
        "SELECT question_id, subject, question_course, question_type, attachment
         FROM questions
         WHERE question_id = ? AND user_id = ? AND status = 'Active'
         LIMIT 1"
    );
    $stmt->bind_param("ii", $questionId, $userId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        reply(false, "This question no longer exists or isn't yours.", [], 404);
    }

    /* =====================================================
       DELETE
       (answers are removed automatically by the foreign key)
       ===================================================== */
    if ($action === "delete") {
        $stmt = $conn->prepare(
            "DELETE FROM questions
             WHERE question_id = ? AND user_id = ? AND status = 'Active'"
        );
        $stmt->bind_param("ii", $questionId, $userId);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$deleted) {
            reply(false, "Could not delete the question.", [], 500);
        }

        removeUploadedFile($current["attachment"]);

        reply(true, "Your question was deleted.");
    }

    /* =====================================================
       EDIT
       ===================================================== */

    // Locked once somebody has answered
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM answers WHERE question_id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $answerCount = (int)$stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    if ($answerCount > 0) {
        reply(
            false,
            "This question can't be edited anymore because it already has answers.",
            ["locked" => true],
            409
        );
    }

    $question = trim((string)($_POST["question"] ?? ""));
    $subject = trim((string)($_POST["subject"] ?? ""));
    $course = trim((string)($_POST["question_course"] ?? ""));
    $type = trim((string)($_POST["question_type"] ?? ""));
    $removeAttachment = ((string)($_POST["remove_attachment"] ?? "")) === "1";

    $subjects = loadOptions($conn, "question_subjects", "subject_name");
    $courses = loadOptions($conn, "question_courses", "course_name");
    $types = loadOptions($conn, "question_types", "type_name");

    // Keeping the value it already has is always fine, even if the admin
    // has since turned that option off.
    if ($question === "") {
        reply(false, "Please enter your question.", [], 400);
    }
    if (mb_strlen($question, "UTF-8") > MAX_QUESTION_LENGTH) {
        reply(false, "Your question must be " . MAX_QUESTION_LENGTH . " characters or fewer.", [], 400);
    }
    if (!in_array($subject, $subjects, true) && $subject !== (string)$current["subject"]) {
        reply(false, "Please select a valid subject.", [], 400);
    }
    if (!in_array($course, $courses, true) && $course !== (string)$current["question_course"]) {
        reply(false, "Please select a valid course.", [], 400);
    }
    if (!in_array($type, $types, true) && $type !== (string)$current["question_type"]) {
        reply(false, "Please select a valid question type.", [], 400);
    }

    // Optional new attachment
    if (
        isset($_FILES["attachment"]) &&
        $_FILES["attachment"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["attachment"]["error"] !== UPLOAD_ERR_OK) {
            reply(false, "The attachment could not be uploaded.", [], 400);
        }

        if ($_FILES["attachment"]["size"] > 5 * 1024 * 1024) {
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

    // Decide what the attachment column should hold
    if ($newFile !== null) {
        $finalAttachment = $newFile;
    } elseif ($removeAttachment) {
        $finalAttachment = null;
    } else {
        $finalAttachment = $current["attachment"];
    }

    $stmt = $conn->prepare(
        "UPDATE questions
         SET question = ?, subject = ?, question_course = ?,
             question_type = ?, attachment = ?
         WHERE question_id = ? AND user_id = ? AND status = 'Active'"
    );
    $stmt->bind_param(
        "sssssii",
        $question,
        $subject,
        $course,
        $type,
        $finalAttachment,
        $questionId,
        $userId
    );
    $stmt->execute();
    $stmt->close();

    // Old file is no longer used
    if ($current["attachment"] !== null && $current["attachment"] !== $finalAttachment) {
        removeUploadedFile($current["attachment"]);
    }

    reply(true, "Your question was updated.", [
        "question"   => $question,
        "subject"    => $subject,
        "course"     => $course,
        "type"       => $type,
        "attachment" => $finalAttachment
    ]);

} catch (Throwable $ex) {
    // the database failed after a new file was saved: don't leave it behind
    removeUploadedFile($newFile);
    reply(false, "Something went wrong. Please try again.", [], 500);
}