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
     FROM users WHERE student_id = ? LIMIT 1"
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

function e($value): string
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* Load only the questions asked by this student.
   Change 'Active' if you also want to show closed questions. */
$questions = [];

$stmt = $conn->prepare(
    "SELECT question_id, question, create_at,
            subject, question_course, question_type, attachment
     FROM questions
     WHERE user_id = ? AND status = 'Active'
     ORDER BY create_at DESC"
);
if (!$stmt) {
    die("Question query failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}
$stmt->close();

/* Load every answer to those questions, with the answerer's name */
$answersByQuestion = [];
$answersUnavailable = false;

if (count($questions) > 0) {
    try {
        $ids = array_map("intval", array_column($questions, "question_id"));
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $bindTypes = str_repeat("i", count($ids));

        $stmt = $conn->prepare(
            "SELECT a.answer_id, a.question_id, a.answer, a.create_at,
             TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS full_name, u.course
             FROM answers a
             INNER JOIN users u ON a.user_id = u.id
             WHERE a.question_id IN ($placeholders)
             ORDER BY a.create_at ASC"
        );

        if (!$stmt) {
            throw new Exception("Answer query failed.");
        }

        $stmt->bind_param($bindTypes, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $answersByQuestion[(int)$row["question_id"]][] = $row;
        }
        $stmt->close();
    } catch (Throwable $ex) {
        $answersUnavailable = true;
    }
}

/* Opening this page counts as seeing the new answers,
   so the notification badge and pop-up are cleared. */
try {
    $stmt = $conn->prepare(
        "UPDATE answers a
         INNER JOIN questions q ON q.question_id = a.question_id
         SET a.is_seen = 1
         WHERE q.user_id = ? AND a.is_seen = 0"
    );

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
} catch (Throwable $ex) {
    /* the page still works without this */
}

/* Summary numbers */
$totalQuestions = count($questions);
$answeredQuestions = 0;
$totalAnswers = 0;

foreach ($questions as $q) {
    $count = count($answersByQuestion[(int)$q["question_id"]] ?? []);
    $totalAnswers += $count;
    if ($count > 0) {
        $answeredQuestions++;
    }
}

$waitingQuestions = $totalQuestions - $answeredQuestions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKMATE - My Questions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>
    <link rel="stylesheet" href="../../asset/css/question.css">
    <link rel="stylesheet" href="../../asset/css/my_question.css">
</head>
<body>

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
            <span class="menu-icon">⌂</span><span>Home</span>
        </a>

        <a href="questions.php" class="menu-item">
            <span class="menu-icon">?</span><span>Questions</span>
        </a>

        <a href="my_question.php" class="menu-item active">
            <span class="menu-icon">♧</span><span>My Questions</span>
        </a>

        <a href="collaboration_stu.php" class="menu-item">
            <span class="menu-icon">♟</span><span>Collaboration</span>
        </a>

        <a href="messages.php" class="menu-item">
            <span class="menu-icon">✉</span><span>Messages</span>
        </a>
    </div>

    <div class="sidebar-user">
        
        <div class="user-avatar">
            <?= e(strtoupper(
                substr(trim($user["first_name"] ?? ""), 0, 1) .
                substr(trim($user["last_name"] ?? ""), 0, 1)
            )) ?>
        </div>

        <div class="user-information">
            <strong>
                <?= e(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")) ?: "Student") ?>
            </strong>
            <span><?= e($user["course"]) ?> <br> <?= e($user["student_id"]) ?></span>
        </div>
        <button class="user-more" type="button" aria-label="More options">⋮</button>
    </div>
</aside>

<main class="main-content">
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-label">STUDENT WORKSPACE</span>
            <span class="topbar-divider">/</span>
            <span class="topbar-current">My Questions</span>
        </div>
    </header>

    <section class="questions-page">

        <div class="page-heading">
            <span class="heading-label">YOUR ACTIVITY</span>
            <h2>My Questions</h2>
            <p>Track the questions you asked and see who helped you answer them.</p>
        </div>

        <?php if ($answersUnavailable): ?>
            <div class="form-message form-message-error">
                Answers could not be loaded right now. Your questions are still shown below.
            </div>
        <?php endif; ?>

        <div class="my-summary">

            <div class="summary-card">
                <span class="summary-label">ASKED</span>
                <strong><?= $totalQuestions ?></strong>
                <span class="summary-hint">Questions you posted</span>
            </div>

            <div class="summary-card">
                <span class="summary-label">ANSWERED</span>
                <strong><?= $answeredQuestions ?></strong>
                <span class="summary-hint">
                    <?= $totalAnswers ?> answer<?= $totalAnswers === 1 ? "" : "s" ?> received
                </span>
            </div>

            <div class="summary-card">
                <span class="summary-label">WAITING</span>
                <strong><?= $waitingQuestions ?></strong>
                <span class="summary-hint">Still need an answer</span>
            </div>

        </div>

        <section class="questions-section">

            <div class="section-heading">
                <div>
                    <span class="heading-label">YOUR QUESTIONS</span>
                    <h3>Asked by you</h3>
                </div>
            </div>

            <div class="filter-tabs" role="tablist" aria-label="Filter questions">
                <button type="button" class="filter-tab active" data-filter="all">
                    All (<?= $totalQuestions ?>)
                </button>
                <button type="button" class="filter-tab" data-filter="answered">
                    Answered (<?= $answeredQuestions ?>)
                </button>
                <button type="button" class="filter-tab" data-filter="waiting">
                    Waiting (<?= $waitingQuestions ?>)
                </button>
            </div>

            <div class="search-box">
                <input type="text" id="searchMyQuestions"
                       placeholder="Search your questions or answers...">
            </div>

            <div class="questions-list" id="myQuestionsList">

                <?php if ($totalQuestions > 0): ?>

                    <?php foreach ($questions as $row): ?>
                        <?php
                        $qid = (int)$row["question_id"];
                        $answers = $answersByQuestion[$qid] ?? [];
                        $answerCount = count($answers);
                        $status = $answerCount > 0 ? "answered" : "waiting";

                        // Distinct answerer names, in the order they answered
                        $answerers = [];
                        foreach ($answers as $a) {
                            if (!in_array($a["full_name"], $answerers, true)) {
                                $answerers[] = $a["full_name"];
                            }
                        }
                        $shownNames = array_slice($answerers, 0, 2);
                        $moreNames = count($answerers) - count($shownNames);
                        ?>

                        <div class="my-question" data-status="<?= $status ?>" data-qid="<?= $qid ?>">

                            <div class="my-question-header"
                                 role="button"
                                 tabindex="0"
                                 aria-expanded="false"
                                 aria-controls="answers-<?= $qid ?>">

                                <div class="question-icon">♟</div>

                                <div class="question-details">
                                    <h3><?= e($row["question"]) ?></h3>

                                    <div class="question-tags">
                                        <span><?= e($row["subject"]) ?></span>
                                        <span><?= e($row["question_course"]) ?></span>
                                        <span><?= e($row["question_type"]) ?></span>
                                    </div>

                                    <div class="question-meta">
                                        <span>Asked</span>
                                        <span>·</span>
                                        <span><?= e(date(
                                            "M d, Y h:i A",
                                            strtotime($row["create_at"])
                                        )) ?></span>
                                    </div>

                                    <?php if ($answerCount > 0): ?>
                                        <div class="answered-by">
                                            <div class="mini-avatars">
                                                <?php foreach ($shownNames as $name): ?>
                                                    <span class="mini-avatar">
                                                        <?= e(strtoupper(substr($name, 0, 1))) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <span>
                                                Answered by
                                                <strong><?= e(implode(", ", $shownNames)) ?></strong><?php if ($moreNames > 0): ?>
                                                    and <?= $moreNames ?> more<?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <span class="answer-badge <?= $status ?>">
                                    <?php if ($answerCount > 0): ?>
                                        <?= $answerCount ?> answer<?= $answerCount === 1 ? "" : "s" ?>
                                    <?php else: ?>
                                        No answers yet
                                    <?php endif; ?>
                                </span>

                                <div class="question-arrow">→</div>
                            </div>

                            <div class="answers-panel" id="answers-<?= $qid ?>" hidden>

                                <?php if (!empty($row["attachment"])): ?>
                                    <a class="attachment-link"
                                       href="../../uploads/questions/<?= rawurlencode(basename($row["attachment"])) ?>"
                                       target="_blank" rel="noopener">
                                        View your attachment ↗
                                    </a>
                                <?php endif; ?>

                                <div class="answers-label">
                                    ANSWERS (<?= $answerCount ?>)
                                </div>

                                <?php if ($answerCount > 0): ?>

                                    <?php foreach ($answers as $a): ?>
                                        <div class="answer-item">

                                            <div class="answer-avatar">
                                                <?= e(strtoupper(substr($a["full_name"], 0, 1))) ?>
                                            </div>

                                            <div class="answer-body">
                                                <div class="answer-author">
                                                    <strong><?= e($a["full_name"]) ?></strong>
                                                    <span>
                                                        <?= e($a["course"]) ?> ·
                                                        <?= e(date(
                                                            "M d, Y h:i A",
                                                            strtotime($a["create_at"])
                                                        )) ?>
                                                    </span>
                                                </div>

                                                <div class="answer-text">
                                                    <?= nl2br(e($a["answer"])) ?>
                                                </div>
                                            </div>

                                        </div>
                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div class="no-answers">
                                        Nobody has answered this question yet. Check back soon.
                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                    <div class="no-questions" id="filterEmpty" hidden>
                        <div>♟</div>
                        <h3>No matching questions</h3>
                        <p>Try a different filter or search.</p>
                    </div>

                <?php else: ?>

                    <div class="no-questions">
                        <div>♟</div>
                        <h3>You haven't asked anything yet</h3>
                        <p>Post your first question and your peers will answer it here.</p>
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../asset/js/my_question.js"></script>
</body>
</html>

<?php
$conn->close();
?>