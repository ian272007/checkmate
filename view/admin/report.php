<?php
session_start();
require "../../config/config.php";   // your connection file

// make mysqli throw real errors and read text correctly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// only admins can open this page
// (CHANGE 'role' and the login path if yours are different)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

// token used to protect status changes and delete
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf"];

// folder (as a web address) where question images are saved
// (CHANGE this to your real upload folder)
$uploadUrl = "../../uploads/";

// ---------- reports ----------
require_once __DIR__ . "/report_data.php";
[$reports, $reasons] = loadReports($conn);

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

    <title>CHECKMATE - Reports</title>

   <link rel="stylesheet" href="../../asset/css/admin_home.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_home.css') ?>">
    <link rel="stylesheet" href="../../asset/css/report.css?v=<?= filemtime(__DIR__ . '/../../asset/css/report.css') ?>">
</head>

<body>

    <!-- =========================================
         SIDEBAR
         ========================================= -->

    <aside class="sidebar">

        <div class="logo">
            <span>♞</span>
            CHECKMATE
        </div>

        <div class="admin-label">
            ADMINISTRATION
        </div>

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
                        <span class="menu-count"><?= $studentCount ?></span>
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
                    <a href="report.php" class="active" data-page="reports">
                        <span class="menu-icon">▤</span>
                        <span>Reports</span>
                        <span class="menu-dot" id="menuDot"<?= $pendingReports > 0 ? "" : " style=\"display:none\"" ?>></span>
                    </a>
                </li>

            </ul>

        </div>

        <!-- ADMIN ACCOUNT (bottom of sidebar) -->
        <div class="sidebar-account">

            <div class="sidebar-account-user">

                <div class="sidebar-account-avatar">
                    A
                </div>

                <div class="sidebar-account-info">
                    <strong>Administrator</strong>
                    <span>CHECKMATE Admin</span>
                </div>

                <button class="account-menu-btn" id="accountMenuButton" title="Account options" aria-label="Account options">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

            </div>

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

        <header class="topbar">

            <div class="topbar-left">

                <div class="topbar-label">
                    ADMINISTRATION
                </div>

                <div class="topbar-title">
                    Reports
                </div>

                <div class="topbar-sub">
                    Review and manage reported students, flagged content and reports.
                </div>

            </div>

            <div class="topbar-right">

                <button class="notification" id="notificationButton">

                    ♧

                    <span class="notification-count" id="notificationCount"<?= $pendingReports > 0 ? "" : " style=\"display:none\"" ?>><?= $pendingReports ?></span>

                </button>

            </div>

        </header>


        <section class="content">

            <!-- Search, reason filter, status filter and export button -->
            <div class="toolbar">

                <div class="search-box">
                    <span class="search-icon">⌕</span>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Search reports, questions or students...">
                </div>

                <select id="reasonFilter" class="filter-select">
                    <option value="all">All Reasons</option>
                    <?php foreach ($reasons as $reason): ?>
                        <option value="<?= e($reason) ?>"><?= e($reason) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Review">Review</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Rejected">Rejected</option>
                </select>

                <button class="btn-gold" id="exportButton">⇩ Export</button>

            </div>


            <!-- Table -->
            <div class="panel">

                <div class="table-wrap">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reason</th>
                                <th>Question</th>
                                <th>Reported Student</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <!-- rows are filled in by report.js -->
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
         VIEW REPORT POPUP
         ========================================= -->

    <div class="modal-overlay" id="viewModal">

        <div class="modal">

            <div class="modal-label" id="viewLabel">REPORT</div>
            <h2 class="modal-title" id="viewTitle">Report reason</h2>

            <div class="detail-grid">

                <div class="detail-item">
                    <span>Reported student</span>
                    <strong id="viewStudent"></strong>
                </div>

                <div class="detail-item">
                    <span>Reported by</span>
                    <strong id="viewReporter"></strong>
                </div>

                <div class="detail-item">
                    <span>Date reported</span>
                    <strong id="viewDate"></strong>
                </div>

                <div class="detail-item">
                    <span>Question</span>
                    <strong id="viewQuestionId"></strong>
                </div>

            </div>

            <div class="detail-label">Reported question</div>
            <div class="detail-box" id="viewQuestion"></div>
            <div class="detail-meta" id="viewQuestionMeta"></div>
            <div id="viewAttachment"></div>

            <div class="detail-label">Reporter's details</div>
            <div class="detail-box" id="viewDetails"></div>

            <div class="form-group">
                <label for="viewStatus">Status</label>
                <select id="viewStatus" class="filter-select">
                    <option value="Pending">Pending</option>
                    <option value="Review">Review</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="modal-buttons">
                <button class="btn-outline" id="closeView">Close</button>
                <button class="btn-gold filled" id="saveView">Save Changes</button>
            </div>

        </div>

    </div>


    <div class="toast" id="toast"></div>

    <!-- data from the database, used by report.js -->
    <script>
        var reports = <?= json_encode($reports, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var csrfToken = <?= json_encode($csrf) ?>;
        var uploadUrl = <?= json_encode($uploadUrl) ?>;
    </script>

    <script src="../../asset/js/report.js?v=<?= filemtime(__DIR__ . '/../../asset/js/report.js') ?>"></script>

</body>
</html>