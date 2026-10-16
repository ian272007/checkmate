<?php
session_start();
require "../../config/config.php";   // your connection file
require_once "../../config/program_helpers.php";

// make mysqli throw real errors and read text correctly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// only admins can open this page
// (CHANGE 'role' and the login path if yours are different)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

// token used to protect add / archive actions
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf"];

// ---------- courses / programs (with how many questions use each one) ----------
$courses = [];
$result = $conn->query(
    "SELECT c.id, c.course_name AS name, c.status,
            (SELECT COUNT(*) FROM questions q WHERE q.question_course = c.course_name) AS used
     FROM question_courses c
     ORDER BY c.id"
);
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int) $row["id"];
    $row["used"] = (int) $row["used"];
    $courses[] = $row;
}

// ---------- subjects (with how many questions use each one) ----------
$subjects = [];
$result = $conn->query(
    "SELECT s.id, s.subject_name AS name, s.status,
            (SELECT COUNT(*) FROM questions q WHERE q.subject = s.subject_name) AS used
     FROM question_subjects s
     ORDER BY s.id"
);
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int) $row["id"];
    $row["used"] = (int) $row["used"];
    $subjects[] = $row;
}

// ---------- which programs each subject is linked to ----------
// (needs the migration: course_subjects.year_level and .semester)
$programMap = [];
$result = $conn->query(
    "SELECT subject_id, course_id, year_level, semester FROM course_subjects"
);
while ($row = $result->fetch_assoc()) {
    $programMap[(int) $row["subject_id"]][] = [
        "course_id"  => (int) $row["course_id"],
        "year_level" => (string) ($row["year_level"] ?? ""),
        "semester"   => (string) ($row["semester"] ?? "")
    ];
}

// ---------- sidebar numbers ----------
$studentCount = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'student'")->fetch_assoc()["c"];
$pendingReports = (int) $conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'Pending'")->fetch_assoc()["c"];

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Subjects & Programs</title>

    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/subject_course.css?v=<?= filemtime(__DIR__ . '/../../asset/css/subject_course.css') ?>">
    <link rel="stylesheet" href="../../asset/css/cm_confirm.css?v=<?= filemtime(__DIR__ . '/../../asset/css/cm_confirm.css') ?>">

    <!-- layout for the "Programs of this subject" popup (reuses the existing modal look) -->
    <style>
        .program-modal { max-width: 640px; width: 92%; max-height: 90vh; overflow-y: auto; }
        .pm-help { margin: 0 0 14px; color: #718398; font-size: 12px; line-height: 1.5; }
        .pm-row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr);
                  gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #252c32; }
        .pm-row:last-child { border-bottom: none; }
        .pm-name { display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-size: 13px; }
        .pm-name input { flex-shrink: 0; }
        .pm-row select { min-width: 0; font-size: 12px; }
        .pm-row select:disabled { opacity: 0.4; cursor: not-allowed; }
        .pm-tag { color: #9a8a6a; font-size: 11px; }
        @media (max-width: 600px) { .pm-row { grid-template-columns: 1fr 1fr; } .pm-name { grid-column: 1 / -1; } }
    </style>
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "subjects";
$pageTitle       = "Subjects & Programs";
$pageSub         = "Manage programs, subjects, and which subjects each program offers.";
$sidebarStudents = $studentCount;
$sidebarPending  = $pendingReports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <section class="content">

            <!-- Summary cards -->
            <div class="stat-row">

                <div class="stat-card" data-jump="courses">
                    <div class="stat-icon">◇</div>
                    <div class="stat-info">
                        <div class="stat-label">Total Programs</div>
                        <div class="stat-number" id="statCourses">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="subjects">
                    <div class="stat-icon blue">≡</div>
                    <div class="stat-info">
                        <div class="stat-label">Total Subjects</div>
                        <div class="stat-number" id="statSubjects">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="Active">
                    <div class="stat-icon green">✓</div>
                    <div class="stat-info">
                        <div class="stat-label">Active</div>
                        <div class="stat-number" id="statActive">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

                <div class="stat-card" data-jump="Archived">
                    <div class="stat-icon red">▣</div>
                    <div class="stat-info">
                        <div class="stat-label">Archived</div>
                        <div class="stat-number" id="statArchived">0</div>
                    </div>
                    <div class="stat-arrow">→</div>
                </div>

            </div>


            <!-- Programs / Subjects toggle -->
            <div class="toggle">
                <button class="toggle-btn active" data-tab="courses">Programs</button>
                <button class="toggle-btn" data-tab="subjects">Subjects</button>
            </div>


            <!-- Search, status filter and add button -->
            <div class="toolbar">

                <div class="search-box">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search programs...">
                </div>

                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Archived">Archived</option>
                </select>

                <button class="btn-gold" id="addButton">+ Add Program</button>

            </div>


            <!-- Table -->
            <div class="panel">

                <div class="table-wrap">

                    <table class="data-table">

                        <thead id="tableHead"></thead>

                        <!-- rows are filled in by subject_course.js -->
                        <tbody id="tableBody"></tbody>

                    </table>

                </div>

                <div class="panel-footer">
                    <div class="showing-text" id="showingText"></div>
                    <div class="pagination" id="pagination"></div>
                </div>

            </div>

        </section>

    </main>


    <!-- =========================================
         ADD / EDIT POPUP
         ========================================= -->

    <div class="modal-overlay" id="formModal">

        <div class="modal">

            <div class="modal-label" id="modalLabel">PROGRAMS</div>
            <h2 class="modal-title" id="modalTitle">Add program</h2>

            <div class="form-group">
                <label for="formName" id="nameLabel">Program name</label>
                <input type="text" id="formName" class="form-input" maxlength="100" placeholder="BS Information Technology">
            </div>

            <div class="form-error" id="formError"></div>

            <div class="modal-buttons">
                <button class="btn-outline" id="cancelForm">Cancel</button>
                <button class="btn-gold" id="saveForm">Save</button>
            </div>

        </div>

    </div>


    <!-- =========================================
         PROGRAMS OF ONE SUBJECT POPUP
         (which programs offer this subject, and in which year / semester)
         ========================================= -->

    <div class="modal-overlay" id="programModal">

        <div class="modal program-modal">

            <div class="modal-label">SUBJECT PROGRAMS</div>
            <h2 class="modal-title" id="programTitle">Programs</h2>

            <p class="pm-help">
                Tick every program that offers this subject. Year level and semester are optional:
                leave them as "Not set" unless you have the verified curriculum.
                A subject that is not linked to any program cannot be chosen by students.
            </p>

            <div id="programRows"></div>

            <div class="form-error" id="programError"></div>

            <div class="modal-buttons">
                <button class="btn-outline" id="cancelProgram">Cancel</button>
                <button class="btn-gold" id="saveProgram">Save</button>
            </div>

        </div>

    </div>


    <div class="toast" id="toast"></div>

    <!-- data from the database, used by subject_course.js -->
    <script>
        var courses = <?= json_encode($courses, $jsonFlags) ?>;
        var subjects = <?= json_encode($subjects, $jsonFlags) ?>;
        var programMap = <?= json_encode($programMap, $jsonFlags) ?>;
        var YEAR_LEVELS = <?= json_encode(PROGRAM_YEAR_LEVELS, $jsonFlags) ?>;
        var SEMESTERS = <?= json_encode(PROGRAM_SEMESTERS, $jsonFlags) ?>;
        var csrfToken = <?= json_encode($csrf) ?>;
        var pendingReports = <?= $pendingReports ?>;
    </script>

    <!-- styled confirm dialog (must load BEFORE subject_course.js) -->
    <script src="../../asset/js/cm_confirm.js?v=<?= filemtime(__DIR__ . '/../../asset/js/cm_confirm.js') ?>"></script>
    <script src="../../asset/js/subject_course.js?v=<?= filemtime(__DIR__ . '/../../asset/js/subject_course.js') ?>"></script>

</body>
</html>