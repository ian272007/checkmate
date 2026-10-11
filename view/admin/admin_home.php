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
// TODO: remove this check once the reports table is created for sure
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

    <<!-- filemtime() is the time the file was last saved, so the link changes whenever you edit the CSS -->
<link rel="stylesheet" href="../../asset/css/admin_home.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_home.css') ?>">
<link rel="stylesheet" href="../../asset/css/student.css?v=<?= filemtime(__DIR__ . '/../../asset/css/student.css') ?>">

</head>

<body>

    <!-- =========================================
         SIDEBAR
         ========================================= -->

    <aside class="sidebar">

        <!-- Logo -->
        <div class="logo">
            <span>♞</span>
            CHECKMATE
        </div>

        <!-- Account Type -->
        <div class="admin-label">
            ADMINISTRATION
        </div>


        <!-- =====================================
             OVERVIEW MENU
             ===================================== -->

        <div class="sidebar-section">

            <div class="sidebar-title">
                OVERVIEW
            </div>

           <ul class="sidebar-menu">

                <li>
                    <a href="admin_home.php" data-page="dashboard">
                        <span class="menu-icon">⌂</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="student.php" data-page="students">
                        <span class="menu-icon">♟</span>
                        <span>Students</span>
                        <!-- was a fixed 8, now it shows the real number -->
                        <span class="menu-count"><?= $total_students ?></span>
                    </a>
                </li>

                <li>
                    <a href="question_ad.php" data-page="questions">
                        <span class="menu-icon">?</span>
                        <span>Questions</span>
                    </a>
                </li>

                <li>
                    <a href="collaboration.php" data-page="collaborations">
                        <span class="menu-icon">♞</span>
                        <span>Collaborations</span>
                    </a>
                </li>

            </ul>

        </div>


        <!-- =====================================
             MANAGEMENT MENU
             ===================================== -->

        <div class="sidebar-section">

            <div class="sidebar-title">
                MANAGEMENT
            </div>

            <ul class="sidebar-menu">

                <li>
                    <a href="subject_course.php" data-page="subjects">
                        <span class="menu-icon">◇</span>
                        <span>Subjects & Courses</span>
                    </a>
                </li>

                <li>
                    <a href="question_type.php" data-page="types">
                        <span class="menu-icon">≡</span>
                        <span>Question Types</span>
                    </a>
                </li>

                <li>
                    <a href="report.php" data-page="reports">
                        <span class="menu-icon">▤</span>
                        <span>Reports</span>
                        <span class="menu-dot"></span>
                    </a>
                </li>

            </ul>

        </div>


        <!-- =====================================
             ADMIN ACCOUNT (bottom of sidebar)
             ===================================== -->

        <div class="sidebar-account">

            <div class="sidebar-account-user">

                <div class="sidebar-account-avatar">
                    A
                </div>

                <div class="sidebar-account-info">
                    <strong>Administrator</strong>
                    <span>CHECKMATE Admin</span>
                </div>

                <!-- three dots (vertical) on the right side of the profile -->
                <button class="account-menu-btn" id="accountMenuButton" title="Account options" aria-label="Account options">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

            </div>

            <!-- menu that opens when the 3 dots are clicked -->
            <div class="account-menu" id="accountMenu">
                <a href="change_password.html">
                    <span class="account-menu-icon">⚿</span>
                    <span>Change Password</span>
                </a>
                <a href="../../authentication/Login/logout.php" class="menu-logout">
                    <span class="account-menu-icon">⏻</span>
                    <span>Log Out</span>
                </a>
            </div>

        </div>

    </aside>


    <!-- =========================================
         MAIN CONTENT
         ========================================= -->

    <main class="main">


        <!-- =====================================
             TOP BAR
             ===================================== -->

        <header class="topbar">

            <div class="topbar-left">

                <div class="topbar-label">
                    ADMINISTRATION
                </div>

                <div class="topbar-title">
                    Dashboard
                </div>

            </div>


            <div class="topbar-right">

                <!-- Notifications (the number is the pending reports) -->
                <button class="notification" id="notificationButton">

                    ♧

                    <?php if ($pending_reports > 0): ?>
                        <span class="notification-count">
                            <?= $pending_reports ?>
                        </span>
                    <?php endif; ?>

                </button>

            </div>

        </header>



        <!-- =====================================
             PAGE CONTENT
             ===================================== -->

        <section class="content">


            <!-- =================================
                 WELCOME
                 ================================= -->

            <div class="welcome">

                <div class="welcome-label">
                    ♜ SYSTEM OVERVIEW
                </div>

                <h1 class="welcome-title">
                    Welcome back, Administrator.
                </h1>

                <p class="welcome-text">
                    Manage students, questions, collaborations, and system records.
                </p>

            </div>



            <!-- =================================
                 SYSTEM OVERVIEW
                 ================================= -->

            <div class="section-heading">

                <div class="section-label">
                    SYSTEM
                </div>

                <h2 class="section-title">
                    Overview
                </h2>

            </div>


            <div class="overview-grid">


                <!-- Registered Students -->
                <div class="overview-card">

                    <div class="overview-card-label">
                        Registered Students
                    </div>

                    <div class="overview-card-number">
                        <?= $total_students ?>
                    </div>

                    <div class="overview-card-description">
                        Total student accounts
                    </div>

                </div>


                <!-- Open Questions -->
                <div class="overview-card">

                    <div class="overview-card-label">
                        Open Questions
                    </div>

                    <div class="overview-card-number">
                        <?= $open_questions ?>
                    </div>

                    <div class="overview-card-description">
                        Questions waiting for answers
                    </div>

                </div>


                <!-- Collaborations -->
                <div class="overview-card">

                    <div class="overview-card-label">
                        Collaborations
                    </div>

                    <div class="overview-card-number">
                        <?= $total_collabs ?>
                    </div>

                    <div class="overview-card-description">
                        Active collaboration requests
                    </div>

                </div>


                <!-- Reports -->
                <div class="overview-card">

                    <div class="overview-card-label">
                        Pending Reports
                    </div>

                    <div class="overview-card-number">
                        <?= $pending_reports ?>
                    </div>

                    <div class="overview-card-description">
                        Reports requiring review
                    </div>

                </div>

            </div>



            <!-- =================================
                 RECENT REPORTS
                 (panel-grid centers the panel)
                 ================================= -->

            <div class="panel-grid">

                <div class="panel">

                    <div class="panel-header">

                        <div>

                            <div class="panel-label">
                                REPORTS
                            </div>

                            <h2 class="panel-heading">
                                Recent reports
                            </h2>

                        </div>

                        <a href="#" class="panel-link" id="viewReports">
                            View all →
                        </a>

                    </div>


                    <?php if (!$reports_table_ready): ?>

                        <!-- the reports table is not in the database yet -->
                        <div class="list-row">
                            <div class="list-info">
                                <div class="list-sub">
                                    The reports table does not exist yet. Import reports.sql in phpMyAdmin.
                                </div>
                            </div>
                        </div>

                    <?php elseif (count($recent_reports) == 0): ?>

                        <!-- table exists but nobody reported anything -->
                        <div class="list-row">
                            <div class="list-info">
                                <div class="list-sub">
                                    No reports yet.
                                </div>
                            </div>
                        </div>

                    <?php else: ?>

                        <?php foreach ($recent_reports as $report): ?>

                            <?php
                            // Pending reports get a "!" and the Resolved ones get a check mark
                            if ($report["status"] == "Pending") {
                                $icon = "!";
                                $status_class = "new";
                            } else {
                                $icon = "✓";
                                $status_class = "done";
                            }
                            ?>

                            <div class="list-row">

                                <div class="list-avatar">
                                    <?= $icon ?>
                                </div>

                                <div class="list-info">

                                    <div class="list-name">
                                        <?= e($report["reason"]) ?>
                                    </div>

                                    <div class="list-sub">
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



            <!-- =================================
                 QUICK ACCESS
                 ================================= -->
         

            <div class="quick-access">

                <div class="section-heading">

                    <div class="section-label">
                        MANAGEMENT
                    </div>

                    <h2 class="section-title">
                        Quick access
                    </h2>

                </div>



                <div class="quick-grid">


                    <!-- Subjects -->

                    <div class="quick-card" data-action="subjects">

                        <div class="quick-icon">
                            ◇
                        </div>

                        <div class="quick-info">

                            <div class="quick-title">
                                Subjects & Courses
                            </div>

                            <div class="quick-description">
                                Manage academic records
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </div>



                    <!-- Question Types -->

                    <div class="quick-card" data-action="types">

                        <div class="quick-icon">
                            ≡
                        </div>

                        <div class="quick-info">

                            <div class="quick-title">
                                Question Types
                            </div>

                            <div class="quick-description">
                                Manage question categories
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </div>



                    <!-- Reports -->

                    <div class="quick-card" data-action="reports">

                        <div class="quick-icon">
                            ▤
                        </div>

                        <div class="quick-info">

                            <div class="quick-title">
                                Reports
                            </div>

                            <div class="quick-description">
                                Review reported content
                            </div>

                        </div>

                        <div class="quick-arrow">
                            →
                        </div>

                    </div>

                </div>

            </div>



            <!-- =================================
                 FOOTER
                 ================================= -->

            <footer class="footer">

                <div class="footer-left">

                    <div class="footer-chess">
                        ♛
                    </div>

                    <div>

                        <div class="footer-title">
                            CHECKMATE ADMIN
                        </div>

                        <div class="footer-subtitle">
                            Keep the board organized.
                        </div>

                    </div>

                </div>


                <div class="footer-links">
                    <span>Verify.</span>
                    <span>Manage.</span>
                    <span>Monitor.</span>
                </div>

            </footer>

        </section>

    </main>



    <!-- =========================================
         TOAST MESSAGE
         ========================================= -->

    <div class="toast" id="toast"></div>


    <!-- JavaScript -->
    <script src="../../asset/js/admin_home.js"></script>

</body>
</html>