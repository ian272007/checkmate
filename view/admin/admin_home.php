<?php
session_start();

// show times in the Philippines so the "x min ago" text is correct
date_default_timezone_set("Asia/Manila");

// if not logged in as admin, go to the login page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "admin") {
    header("Location: /checkmate/authentication/Login/login.php");
    exit();
}

// connect to the database (gives us the $conn variable)
require_once "../../config/config.php";


/* =========================================
   SMALL HELPER FUNCTIONS
   ========================================= */

// makes text safe to print inside HTML
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// runs a "SELECT COUNT(*)" query and returns the number
function getCount($conn, $sql)
{
    $result = $conn->query($sql);

    // if the query failed, just show 0 instead of crashing the page
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return (int)$row[0];
}

// turns a date into text like "15 min ago"
function timeAgo($datetime)
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60) {
        return "just now";
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . " min ago";
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        return $hours . ($hours == 1 ? " hr" : " hrs") . " ago";
    } else {
        $days = floor($seconds / 86400);
        return $days . ($days == 1 ? " day" : " days") . " ago";
    }
}


/* =========================================
   GET THE NUMBERS FROM THE DATABASE
   ========================================= */

// 1. registered students (only accounts with the student role)
$total_students = getCount($conn, "SELECT COUNT(*) FROM users WHERE role = 'student'");

// 2. open questions (questions that are still Active)
$open_questions = getCount($conn, "SELECT COUNT(*) FROM questions WHERE status = 'Active'");

// 3. collaborations (every collaboration request)
$total_collabs = getCount($conn, "SELECT COUNT(*) FROM collaborations");

// 4 and 5. reports
// first check if the reports table exists, so the page does not crash
$reports_table_ready = false;
$check = $conn->query("SHOW TABLES LIKE 'reports'");

if ($check && $check->num_rows > 0) {
    $reports_table_ready = true;
}

$pending_reports = 0;
$recent_reports = [];

if ($reports_table_ready) {

    // 4. pending reports
    $pending_reports = getCount($conn, "SELECT COUNT(*) FROM reports WHERE status = 'Pending'");

    // 5. the 3 newest reports
    $sql = "SELECT report_id, reason, status, create_at
            FROM reports
            ORDER BY create_at DESC
            LIMIT 3";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_reports[] = $row;
        }
    }
}

// we have everything we need, so close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Admin Dashboard</title>

    <!-- Bootstrap 5.3.3 (same as the student pages, so both look the same) -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- shared sidebar + top bar, then this page's own CSS
         (filemtime() changes the link whenever you edit a file, so the browser never shows an old copy) -->
    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/admin_home.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_home.css') ?>">

</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
$activePage      = "dashboard";
$pageTitle       = "Dashboard";
$pageSub         = "";
$sidebarStudents = $total_students;
$sidebarPending  = $pending_reports;
$showBell        = false;

require __DIR__ . "/partials/admin_shell.php";
?>

    <!-- WELCOME -->
    <section class="welcome-section">

        <div class="welcome-small">
            ♜ SYSTEM OVERVIEW
        </div>

        <h2>
            Welcome back, Administrator.
        </h2>

        <p>
            Manage students, questions, collaborations, and system records.
        </p>

    </section>

    <section class="dashboard-section">

        <!-- SYSTEM OVERVIEW -->
        <div class="quick-heading">

            <div class="heading-label">
                SYSTEM
            </div>

            <h2>
                Overview
            </h2>

        </div>

        <div class="stat-grid">

            <div class="stat-card">
                <div class="stat-label">Registered Students</div>
                <div class="stat-number"><?= $total_students ?></div>
                <div class="stat-description">Total student accounts</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Open Questions</div>
                <div class="stat-number"><?= $open_questions ?></div>
                <div class="stat-description">Questions waiting for answers</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Collaborations</div>
                <div class="stat-number"><?= $total_collabs ?></div>
                <div class="stat-description">Active collaboration requests</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Pending Reports</div>
                <div class="stat-number"><?= $pending_reports ?></div>
                <div class="stat-description">Reports requiring review</div>
            </div>

        </div>


        <!-- RECENT REPORTS (same width as the overview cards) -->
        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-label">
                        REPORTS
                    </div>

                    <h3>
                        Recent reports
                    </h3>

                </div>

                <a href="report.php" class="view-all">
                    View all
                    <span>→</span>
                </a>

            </div>

            <div class="report-list">

                <?php if (!$reports_table_ready): ?>

                    <div class="empty-note">
                        The reports table does not exist yet. Import reports.sql in phpMyAdmin.
                    </div>

                <?php elseif (count($recent_reports) == 0): ?>

                    <div class="empty-note">
                        No reports yet.
                    </div>

                <?php else: ?>

                    <?php foreach ($recent_reports as $report): ?>

                        <?php
                        // Pending reports get a "!" and the others get a check mark
                        if ($report["status"] == "Pending") {
                            $icon = "!";
                            $status_class = "new";
                        } else {
                            $icon = "✓";
                            $status_class = "done";
                        }
                        ?>

                        <div class="report-item">

                            <div class="collaborator-avatar">
                                <?= $icon ?>
                            </div>

                            <div class="report-info">

                                <div class="report-name">
                                    <?= e($report["reason"]) ?>
                                </div>

                                <div class="report-sub">
                                    Report #<?= str_pad($report["report_id"], 4, "0", STR_PAD_LEFT) ?>
                                    · <?= e(timeAgo($report["create_at"])) ?>
                                </div>

                            </div>

                            <span class="status <?= $status_class ?>">
                                <?= e($report["status"]) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


        <!-- QUICK ACCESS -->
        <div class="quick-heading quick-heading-spaced">

            <div class="heading-label">
                MANAGEMENT
            </div>

            <h2>
                Quick access
            </h2>

        </div>

        <div class="quick-grid">

            <a href="subject_course.php" class="quick-card">

                <div class="quick-card-icon">◇</div>

                <div class="quick-card-content">
                    <h4>Subjects &amp; Courses</h4>
                    <p>Manage academic records</p>
                </div>

                <div class="card-arrow">→</div>

            </a>

            <a href="question_type.php" class="quick-card">

                <div class="quick-card-icon">≡</div>

                <div class="quick-card-content">
                    <h4>Question Types</h4>
                    <p>Manage question categories</p>
                </div>

                <div class="card-arrow">→</div>

            </a>

            <a href="report.php" class="quick-card">

                <div class="quick-card-icon">▤</div>

                <div class="quick-card-content">
                    <h4>Reports</h4>
                    <p>Review reported content</p>
                </div>

                <div class="card-arrow">→</div>

            </a>

        </div>

    </section>

    <!-- FOOTER -->
    <footer class="chess-footer">

        <div class="footer-piece">
            ♛
        </div>

        <div class="footer-text">

            <strong>
                Keep the board organized.
            </strong>

            <span>
                Verify. Manage. Monitor.
            </span>

        </div>

        <div class="footer-piece">
            ♛
        </div>

    </footer>

</main>

</body>
</html>