<?php
session_start();

require_once "../../config/config.php";

if (empty($_SESSION["student_id"])) {
    header("Location: ../../authentication/Login/login.php");
    exit();
}

$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare(
    "SELECT id, first_name, last_name, student_id, course
     FROM users
     WHERE student_id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("User query failed: " . $conn->error);
}

$stmt->bind_param("s", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Student account not found.");
}

$user_id = (int)$user["id"];
$error = "";

if (empty($_SESSION["questions_csrf"])) {
    $_SESSION["questions_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["questions_csrf"];

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Load active dropdown choices
function loadOptions(mysqli $conn, string $table, string $column): array
{
    $sql = "SELECT `$column` AS option_name
            FROM `$table`
            WHERE status = 'Active'
            ORDER BY `$column` ASC";

    $result = $conn->query($sql);
    $options = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = $row["option_name"];
        }
    }

    return $options;
}

$subjects = loadOptions($conn, "question_subjects", "subject_name");
$courses = loadOptions($conn, "question_courses", "course_name");
$types = loadOptions($conn, "question_types", "type_name");

/* =========================================================
   ANSWER SOMEONE ELSE'S QUESTION
   ========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "answer"
) {
    $answerQuestionId = (int)($_POST["question_id"] ?? 0);
    $answerText = trim((string)($_POST["answer"] ?? ""));
    $notice = "answer_failed";

    $sentToken = (string)($_POST["csrf"] ?? "");

    if ($sentToken === "" || !hash_equals($csrf, $sentToken)) {
        $notice = "answer_csrf";
    } elseif ($answerQuestionId <= 0 || $answerText === "") {
        $notice = "answer_empty";
    } elseif (mb_strlen($answerText, "UTF-8") > 5000) {
        $notice = "answer_long";
    } else {
        try {
            // The question must be active and posted by someone else
            $stmt = $conn->prepare(
                "SELECT question_id
                 FROM questions
                 WHERE question_id = ?
                   AND user_id <> ?
                   AND status = 'Active'
                 LIMIT 1"
            );

            if (!$stmt) {
                throw new Exception("prepare failed");
            }

            $stmt->bind_param("ii", $answerQuestionId, $user_id);
            $stmt->execute();
            $allowed = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$allowed) {
                $notice = "answer_denied";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO answers (question_id, user_id, answer)
                     VALUES (?, ?, ?)"
                );

                if (!$stmt) {
                    throw new Exception("prepare failed");
                }

                $stmt->bind_param(
                    "iis",
                    $answerQuestionId,
                    $user_id,
                    $answerText
                );

                if ($stmt->execute()) {
                    $notice = "answer_posted";
                }

                $stmt->close();
            }
        } catch (Throwable $ex) {
            $notice = "answer_failed";
        }
    }

    header(
        "Location: questions.php?notice=" . $notice .
        "#question-" . $answerQuestionId
    );
    exit();
}

/* =========================================================
   POST A NEW QUESTION
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $question = trim($_POST["question"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $course = trim($_POST["question_course"] ?? "");
    $type = trim($_POST["question_type"] ?? "");
    $attachmentName = null;

    if ($question === "") {
        $error = "Please enter your question.";
    } elseif (!in_array($subject, $subjects, true)) {
        $error = "Please select a valid subject.";
    } elseif (!in_array($course, $courses, true)) {
        $error = "Please select a valid course.";
    } elseif (!in_array($type, $types, true)) {
        $error = "Please select a valid question type.";
    }

    // Handle optional attachment
    if (
        $error === "" &&
        isset($_FILES["attachment"]) &&
        $_FILES["attachment"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["attachment"]["error"] !== UPLOAD_ERR_OK) {
            $error = "The attachment could not be uploaded.";
        } elseif ($_FILES["attachment"]["size"] > 5 * 1024 * 1024) {
            $error = "Attachment must be 5 MB or smaller.";
        } else {
            $originalName = $_FILES["attachment"]["name"];
            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            $allowedExtensions = [
                "pdf", "doc", "docx", "ppt", "pptx", "txt",
                "jpg", "jpeg", "png", "xls", "xlsx", "csv"
            ];

            if (!in_array($extension, $allowedExtensions, true)) {
                $error = "Allowed files: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG, XLS, XLSX, and CSV.";
            } else {
                $uploadDirectory = "../../uploads/questions/";

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true)
                ) {
                    $error = "Could not create the upload folder.";
                } else {
                    $attachmentName = bin2hex(random_bytes(16))
                        . "." . $extension;

                    $destination = $uploadDirectory . $attachmentName;

                    if (!move_uploaded_file(
                        $_FILES["attachment"]["tmp_name"],
                        $destination
                    )) {
                        $error = "Could not save the uploaded file.";
                        $attachmentName = null;
                    }
                }
            }
        }
    }

    if ($error === "") {
        $stmt = $conn->prepare(
            "INSERT INTO questions
             (user_id, question, status, create_at,
             subject, question_course, question_type, attachment)
            VALUES (?, ?, 'Active', NOW(), ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $error = "Insert prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "isssss",
                $user_id,
                $question,
                $subject,
                $course,
                $type,
                $attachmentName
            );

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: questions.php?notice=question_posted");
                exit();
            } else {
                $error = "Could not save your question.";
                $stmt->close();

                // Remove uploaded file if database insert fails
                if ($attachmentName !== null) {
                    $filePath = "../../uploads/questions/" . $attachmentName;

                    if (is_file($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }
    }
}

/* =========================================================
   COMMUNITY QUESTIONS (posted by OTHER students only)
   Your own questions are in "My Questions".
   already_reported = 1 when YOU already reported that question.
   ========================================================= */

$questions = [];

$stmt = $conn->prepare(
    "SELECT
    q.question_id,
    q.question,
    q.create_at,
    q.subject,
    q.question_course,
    q.question_type,
    q.attachment,
    u.first_name,
    u.last_name,
    u.student_id,
    EXISTS(
        SELECT 1 FROM reports rp
        WHERE rp.question_id = q.question_id
          AND rp.reporter_id = ?
    ) AS already_reported
FROM questions q
INNER JOIN users u ON q.user_id = u.id
WHERE q.status = 'Active'
  AND q.user_id <> ?
ORDER BY q.create_at DESC"
);

if (!$stmt) {
    die("Question query failed: " . $conn->error);
}

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

$stmt->close();

// Pop-up messages shown after posting a question or an answer
$notices = [
    "question_posted" => ["success", "Your question has been posted."],
    "answer_posted"   => ["success", "Your answer has been posted."],
    "answer_empty"    => ["error", "Please write your answer before posting."],
    "answer_long"     => ["error", "Your answer must be 5000 characters or fewer."],
    "answer_denied"   => ["error", "You can only answer other students' active questions."],
    "answer_csrf"     => ["error", "Security token invalid. Please refresh the page."],
    "answer_failed"   => ["error", "Your answer could not be posted. Please try again."]
];

$noticeKey = (string)($_GET["notice"] ?? "");
$flash = $notices[$noticeKey] ?? null;

// Generate initials from first and last name
$firstInitial = mb_substr(
    trim($user["first_name"] ?? ""),
    0,
    1,
    "UTF-8"
);

$lastInitial = mb_substr(
    trim($user["last_name"] ?? ""),
    0,
    1,
    "UTF-8"
);

$initials = mb_strtoupper(
    $firstInitial . $lastInitial,
    "UTF-8"
);

// Full name for community question posts
$displayName = trim(
    ($user["first_name"] ?? "") . " " .
    ($user["last_name"] ?? "")
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKMATE - Questions</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>
    <!-- filemtime() gives the time the file was last saved, so the link changes when the file changes -->
<link rel="stylesheet" href="../../asset/css/question.css?v=<?= filemtime(__DIR__ . '/../../asset/css/question.css') ?>">
<link rel="stylesheet" href="../../asset/css/question_answer.css?v=<?= filemtime(__DIR__ . '/../../asset/css/question_answer.css') ?>">
</head>

<body>

<?php if ($flash !== null): ?>
    <div id="flashData"
         data-type="<?= e($flash[0]) ?>"
         data-message="<?= e($flash[1]) ?>"
         hidden></div>
<?php endif; ?>

<aside class="sidebar">

    <div class="logo-area">
        <div class="logo-piece">♞</div>

        <div class="logo-text">
            <h1>CHECKMATE</h1>
            <span>PEER COLLABORATION</span>
        </div>
    </div>

    <a href="questions.php" class="new-question-button">
        <span class="button-icon">+</span>
        <span>New Question</span>
    </a>

    <div class="sidebar-section">
        <div class="section-title">WORKSPACE</div>

        <a href="student_home.php" class="menu-item">
            <span class="menu-icon">⌂</span>
            <span>Home</span>
        </a>

        <a href="questions.php" class="menu-item active">
            <span class="menu-icon">?</span>
            <span>Questions</span>
        </a>

        <a href="my_question.php" class="menu-item">
            <span class="menu-icon">♧</span>
            <span>My Questions</span>
        </a>

        <a href="collaboration_stu.php" class="menu-item">
            <span class="menu-icon">♟</span>
            <span>Collaboration</span>
        </a>

        <a href="messages.php" class="menu-item">
            <span class="menu-icon">✉</span>
            <span>Messages</span>
        </a>
    </div>

    <!-- SIDEBAR USER: FIRST + LAST NAME INITIALS -->
    <div class="sidebar-user">

        <div class="user-avatar">
            <?= e($initials) ?>
        </div>

        <div class="user-information">

            <strong>
                <?= e(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")) ?: "Student") ?>
            </strong>

            <span>
                <?= e($user["course"] ?? "") ?><br>
               
                <?= e($user["student_id"] ?? "") ?>
            </span>
        </div>

        <button
            class="user-more"
            type="button"
            aria-label="More options"
        >⋮</button>

    </div>

</aside>

<main class="main-content">

    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-label">STUDENT WORKSPACE</span>
            <span class="topbar-divider">/</span>
            <span class="topbar-current">Questions</span>
        </div>

    </header>

    <section class="questions-page">

        <div class="page-heading">
            <span class="heading-label">PEER QUESTIONS</span>
            <h2>Questions</h2>
            <p>
                See what other students are asking, answer their questions,
                and share your own.
            </p>
        </div>

        <section class="ask-question-box">

            <div class="ask-heading">
                <div class="ask-icon">♞</div>

                <div>
                    <h3>Ask the community</h3>
                    <p>Share what you need help with.</p>
                </div>
            </div>

            <?php if ($error !== ""): ?>
                <div class="form-message form-message-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <div class="question-composer">

                    <div class="question-fields-grid">

                        <div class="question-field">
                            <select
                                name="subject"
                                id="subject"
                                aria-label="Subject"
                                required
                            >
                                <option value="">Subject +</option>

                                <?php foreach ($subjects as $option): ?>
                                    <option
                                        value="<?= e($option) ?>"
                                        <?= (($_POST["subject"] ?? "") === $option) ? "selected" : "" ?>
                                    >
                                        <?= e($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="question-field">
                            <select
                                name="question_course"
                                id="question_course"
                                aria-label="Course"
                                required
                            >
                                <option value="">Course +</option>

                                <?php foreach ($courses as $option): ?>
                                    <option
                                        value="<?= e($option) ?>"
                                        <?= (($_POST["question_course"] ?? "") === $option) ? "selected" : "" ?>
                                    >
                                        <?= e($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="question-field">
                            <select
                                name="question_type"
                                id="question_type"
                                aria-label="Question Type"
                                required
                            >
                                <option value="">Question Type +</option>

                                <?php foreach ($types as $option): ?>
                                    <option
                                        value="<?= e($option) ?>"
                                        <?= (($_POST["question_type"] ?? "") === $option) ? "selected" : "" ?>
                                    >
                                        <?= e($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <textarea
                        id="question"
                        name="question"
                        placeholder="What do you need help with?"
                        aria-label="Your Question"
                        required
                    ><?= e($_POST["question"] ?? "") ?></textarea>

                    <div class="attachment-toolbar">

                        <div class="attachment-menu-container">

                            <button
                                type="button"
                                class="attachment-plus"
                                id="attachmentPlus"
                                aria-label="Add attachment"
                                aria-expanded="false"
                            >+</button>

                            <div
                                class="attachment-menu"
                                id="attachmentMenu"
                                hidden
                            >
                                <button
                                    type="button"
                                    class="attachment-option"
                                    data-attachment="photos"
                                >
                                    <span class="attachment-option-icon">▧</span>
                                    <span>Photos</span>
                                </button>

                                <button
                                    type="button"
                                    class="attachment-option"
                                    data-attachment="files"
                                >
                                    <span class="attachment-option-icon">▤</span>
                                    <span>Files</span>
                                </button>
                            </div>

                        </div>

                        <span
                            class="attachment-selected"
                            id="attachmentSelected"
                        >
                            Add attachment (optional)
                        </span>

                        <input
                            type="file"
                            name="attachment"
                            id="attachment"
                            accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.xls,.xlsx,.csv"
                            hidden
                        >

                    </div>

                </div>

                <div class="ask-form-bottom">
                    <span>Ask your question to other students.</span>
                    <button type="submit">Post Question →</button>
                </div>

            </form>

        </section>

        <section class="questions-section">

            <div class="section-heading">
                <div>
                    <span class="heading-label">COMMUNITY</span>
                    <h3>Questions</h3>
                </div>
            </div>

            <div class="search-box">
                <input
                    type="text"
                    id="searchQuestions"
                    placeholder="Search questions..."
                >
            </div>

            <div class="questions-list" id="questionsList" data-csrf="<?= e($csrf) ?>">

                <?php if (count($questions) > 0): ?>

                    <?php foreach ($questions as $row): ?>

                        <?php
                        $qid = (int)$row["question_id"];

                        $questionAuthor = trim(
                            ($row["first_name"] ?? "") . " " .
                            ($row["last_name"] ?? "")
                        );

                        $alreadyReported = !empty($row["already_reported"]);
                        ?>

                        <div class="question-card" id="question-<?= $qid ?>">

                            <!-- REPORT MENU (three dots, top right) -->
                            <div class="report-menu">
                                <button
                                    type="button"
                                    class="report-dots"
                                    aria-label="Report options"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                >⋮</button>

                                <div class="report-dropdown" hidden>
                                    <?php if ($alreadyReported): ?>
                                        <div class="report-done">✓ You already reported this question</div>
                                    <?php else: ?>
                                        <button type="button" class="report-option">⚑ Report</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="question-icon">♟</div>

                            <div class="question-details">

                                <h3><?= e($row["question"]) ?></h3>

                                <div class="question-tags">
                                    <span><?= e($row["subject"]) ?></span>
                                    <span><?= e($row["question_course"]) ?></span>
                                    <span><?= e($row["question_type"]) ?></span>
                                </div>

                                <div class="question-meta">
                                    <span><?= e($questionAuthor) ?></span>
                                    <span>·</span>

                                    <span>
                                        <?= e(date(
                                            "M d, Y h:i A",
                                            strtotime($row["create_at"])
                                        )) ?>
                                    </span>
                                </div>

                                <?php if (!empty($row["attachment"])): ?>
                                    <a
                                        class="attachment-link"
                                        href="../../uploads/questions/<?= rawurlencode(basename($row["attachment"])) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        View attachment ↗
                                    </a>
                                <?php endif; ?>

                                <button
                                    type="button"
                                    class="answer-toggle answer-toggle-btn"
                                    aria-expanded="false"
                                    aria-controls="answer-form-<?= $qid ?>"
                                >✎ Answer</button>

                            </div>

                            <div
                                class="question-arrow answer-toggle"
                                role="button"
                                tabindex="0"
                                title="Answer this question"
                                aria-controls="answer-form-<?= $qid ?>"
                            >→</div>

                            <form
                                method="POST"
                                class="answer-form"
                                id="answer-form-<?= $qid ?>"
                                hidden
                            >
                                <input type="hidden" name="action" value="answer">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="question_id" value="<?= $qid ?>">

                                <label class="sr-only" for="answer-<?= $qid ?>">
                                    Your answer
                                </label>

                                <textarea
                                    id="answer-<?= $qid ?>"
                                    name="answer"
                                    maxlength="5000"
                                    placeholder="Write your answer..."
                                    required
                                ></textarea>

                                <div class="answer-form-bottom">
                                    <span>
                                        <?= e($questionAuthor ?: "The student") ?> will see your answer in My Questions.
                                    </span>

                                    <div class="answer-form-buttons">
                                        <button type="button" class="answer-cancel">Cancel</button>
                                        <button type="submit" class="answer-submit">Post Answer →</button>
                                    </div>
                                </div>
                            </form>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="no-questions">
                        <div>♟</div>
                        <h3>No questions from other students yet</h3>
                        <p>When other students post a question, it will show up here.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </section>

</main>

<!-- REPORT POPUP -->
<div class="report-modal" id="reportModal" hidden>
    <div class="report-modal-box" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">

        <span class="report-modal-label">REPORT</span>
        <h3 id="reportModalTitle">Report this question</h3>
        <p class="report-modal-sub">
            Tell the admin what is wrong. You can add a screenshot as proof.
        </p>

        <form id="reportForm" enctype="multipart/form-data">
            <input type="hidden" name="question_id" id="reportQuestionId">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <label class="report-field-label">Image (optional)</label>

            <div class="report-upload">
                <button type="button" class="report-upload-btn" id="reportImagePick">+ Add image</button>
                <span id="reportImageName">No image selected</span>
                <input
                    type="file"
                    name="image"
                    id="reportImage"
                    accept=".jpg,.jpeg,.png,.webp"
                    hidden
                >
            </div>

            <div class="report-preview" id="reportPreviewBox" hidden>
                <img id="reportPreview" alt="Selected image preview">
                <button type="button" id="reportImageRemove" aria-label="Remove image">×</button>
            </div>

            <label class="report-field-label" for="reportDescription">Description</label>
            <textarea
                id="reportDescription"
                name="details"
                maxlength="1000"
                placeholder="Describe why you are reporting this question..."
                required
            ></textarea>

            <div class="report-modal-error" id="reportError" hidden></div>

            <div class="report-modal-buttons">
                <button type="button" class="report-cancel" id="reportCancel">Cancel</button>
                <button type="submit" class="report-submit" id="reportSubmit">Send Report →</button>
            </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../asset/js/question.js?v=<?= filemtime(__DIR__ . '/../../asset/js/question.js') ?>"></script>
<script src="../../asset/js/live_questions.js?v=<?= filemtime(__DIR__ . '/../../asset/js/live_questions.js') ?>"></script>

</body>
</html>

<?php
$conn->close();
?>