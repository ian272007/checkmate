<?php
session_start();
require "../../config/config.php";   // your connection file (CHANGE the name/path if it is different)

// make mysqli throw real errors and read/write Filipino/emoji text correctly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// only admins can open this page
// (CHANGE 'role' and the login path if yours are different)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

// token used to protect the delete button
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf"];

// ---------- questions (with the name of the student who asked) ----------
$sql = "SELECT q.question_id AS id,
               q.question,
               q.status,
               q.subject,
               q.question_type AS type,
               q.question_course AS course,
               TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS student
        FROM questions q
        LEFT JOIN users u ON u.id = q.user_id
        ORDER BY q.question_id DESC";

$questions = [];
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int) $row["id"];
    if ($row["student"] === "") {
        $row["student"] = "Unknown";
    }
    $questions[] = $row;
}

// ---------- subjects for the filter ----------
$subjects = [];
$result = $conn->query("SELECT subject_name FROM question_subjects WHERE status = 'Active' ORDER BY subject_name");
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row["subject_name"];
}

// ---------- statuses for the filter ----------
$statuses = [];
$result = $conn->query("SELECT DISTINCT status FROM questions WHERE status IS NOT NULL ORDER BY status");
while ($row = $result->fetch_assoc()) {
    $statuses[] = $row["status"];
}

// ---------- sidebar numbers ----------
$studentCount = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()["c"];
$pendingReports = (int) $conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'Pending'")->fetch_assoc()["c"];

// short helper for printing safe text in html
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Questions</title>

    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/question_ad.css?v=<?= filemtime(__DIR__ . '/../../asset/css/question_ad.css') ?>">
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "questions";
$pageTitle       = "Questions";
$pageSub         = "Review and moderate student questions.";
$sidebarStudents = $studentCount;
$sidebarPending  = $pendingReports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <section class="content">

            <!-- Search and filters -->
            <div class="toolbar">

                <div class="search-box">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search questions or students...">
                </div>

                <select id="categoryFilter" class="filter-select">
                    <option value="all">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= e($subject) ?>"><?= e($subject) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= e($status) ?>"><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>

            </div>


            <!-- Questions table -->
            <div class="table-wrap">

                <table class="questions-table">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Question</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <!-- rows are filled in by question_ad.js -->
                    <tbody id="questionsBody"></tbody>

                </table>

            </div>


            <div class="table-footer">

                <div class="showing-text" id="showingText"></div>

            </div>

        </section>

    </main>


    <div class="toast" id="toast"></div>

    <!-- data from the database, used by question_ad.js -->
    <script>
        var questions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var csrfToken = <?= json_encode($csrf) ?>;
        var pendingReports = <?= $pendingReports ?>;
    </script>

    <script src="../../asset/js/question_ad.js"></script>

</body>
</html>