<?php
/* =========================================================
   CHECKMATE - SHARED ADMIN SIDEBAR + TOP BAR

   Every admin page includes this file right after <body>.
   Set these variables BEFORE including it:

     $activePage      "dashboard", "students", "questions", "collaborations",
                      "subjects", "types" or "reports"
     $pageTitle       text shown in the top bar (the page name)
     $pageSub         optional small line under the top bar (can be "")
     $sidebarStudents number shown next to "Students"
     $sidebarPending  number of pending reports (bell + Reports dot)
     $showBell        true/false (default true)

   It prints the <aside> sidebar, then opens <main class="main-content">
   and prints the top bar. The page closes </main> by itself.
   ========================================================= */

$activePage      = $activePage      ?? "";
$pageTitle       = $pageTitle       ?? "";
$pageSub         = $pageSub         ?? "";
$sidebarStudents = (int)($sidebarStudents ?? 0);
$sidebarPending  = (int)($sidebarPending  ?? 0);
$showBell        = $showBell        ?? true;

// returns " active" for the page that is open right now
$is = function ($key) use ($activePage) {
    return $activePage === $key ? " active" : "";
};

// ---------- name + initials of the logged-in admin ----------
// The page may already have closed its own database connection, so this
// file opens its own small connection just for the name.

$adminName     = "Administrator";
$adminInitials = "A";

$adminId = (int) ($_SESSION["user_id"] ?? 0);

if ($adminId > 0) {
    try {
        $shellConn = new mysqli("localhost", "root", "", "checkmate_db");

        $q = $shellConn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
        $q->bind_param("i", $adminId);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        $shellConn->close();

        if ($row) {
            $first = trim($row["first_name"] ?? "");
            $last  = trim($row["last_name"] ?? "");
            $full  = trim($first . " " . $last);

            if ($full !== "") {
                $adminName = $full;

                // first letter of first name + first letter of last name
                $adminInitials = mb_substr($first !== "" ? $first : $last, 0, 1, "UTF-8");

                if ($first !== "" && $last !== "") {
                    $adminInitials .= mb_substr($last, 0, 1, "UTF-8");
                }

                $adminInitials = mb_strtoupper($adminInitials, "UTF-8");
            }
        }
    } catch (Throwable $e) {
        // if anything fails, keep the default "Administrator" / "A"
    }
}
?>

<!-- =========================================
     SIDEBAR
     ========================================= -->

<aside class="sidebar">

    <!-- LOGO -->
    <div class="logo-area">
        <div class="logo-piece">♞</div>

        <div class="logo-text">
            <h1>CHECKMATE</h1>
            <span>ADMINISTRATION</span>
        </div>
    </div>

    <!-- OVERVIEW MENU -->
    <div class="sidebar-section">

        <div class="section-title">OVERVIEW</div>

        <a href="admin_home.php" class="menu-item<?= $is("dashboard") ?>" data-page="dashboard">
            <span class="menu-icon">⌂</span>
            <span>Dashboard</span>
        </a>

        <a href="student.php" class="menu-item<?= $is("students") ?>" data-page="students">
            <span class="menu-icon">♟</span>
            <span>Students</span>
            <span class="menu-count"><?= $sidebarStudents ?></span>
        </a>

        <a href="question_ad.php" class="menu-item<?= $is("questions") ?>" data-page="questions">
            <span class="menu-icon">?</span>
            <span>Questions</span>
        </a>

        <a href="collaboration.php" class="menu-item<?= $is("collaborations") ?>" data-page="collaborations">
            <span class="menu-icon">♞</span>
            <span>Collaborations</span>
        </a>

    </div>

    <!-- MANAGEMENT MENU -->
    <div class="sidebar-section">

        <div class="section-title">MANAGEMENT</div>

        <a href="subject_course.php" class="menu-item<?= $is("subjects") ?>" data-page="subjects">
            <span class="menu-icon">◇</span>
            <span>Subjects &amp; Courses</span>
        </a>

        <a href="question_type.php" class="menu-item<?= $is("types") ?>" data-page="types">
            <span class="menu-icon">≡</span>
            <span>Question Types</span>
        </a>

        <a href="report.php" class="menu-item<?= $is("reports") ?>" data-page="reports">
            <span class="menu-icon">▤</span>
            <span>Reports</span>
            <!-- the page JavaScript (report.js) shows/hides this dot live -->
            <span class="menu-dot" id="menuDot"<?= $sidebarPending > 0 ? "" : ' style="display:none"' ?>></span>
        </a>

    </div>

    <!-- ADMIN PROFILE (bottom of sidebar) -->
    <div class="sidebar-user">

        <div class="user-avatar"><?= htmlspecialchars($adminInitials, ENT_QUOTES, "UTF-8") ?></div>

        <div class="user-information">
            <strong><?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?></strong>
            <span>Admin</span>
        </div>

        <button
            class="user-more"
            type="button"
            id="accountMenuButton"
            title="Account options"
            aria-label="Account options"
            aria-haspopup="menu"
            aria-expanded="false"
        >⋮</button>

        <!-- menu that opens when the three dots are clicked -->
        <div class="cm-user-menu" id="accountMenu" role="menu">

            <a href="change_password.php" role="menuitem">
                <span class="cm-menu-icon">⚿</span>
                <span>Change Password</span>
            </a>

            <a href="../../authentication/Login/logout.php" role="menuitem" class="cm-menu-logout">
                <span class="cm-menu-icon">→</span>
                <span>Log out</span>
            </a>

        </div>

    </div>

</aside>


<!-- =========================================
     MAIN CONTENT (the page closes this with </main>)
     ========================================= -->

<main class="main-content">

    <!-- TOP BAR -->
    <header class="ad-topbar">

        <div class="ad-topbar-left">

            <div class="ad-crumbs">

                <span class="ad-crumb-label">
                    ADMINISTRATION
                </span>

                <span class="ad-crumb-divider">
                    /
                </span>

                <span class="ad-crumb-current">
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?>
                </span>

            </div>

            <?php if ($pageSub !== ""): ?>
                <div class="ad-topbar-sub">
                    <?= htmlspecialchars($pageSub, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

        </div>

        <?php if ($showBell): ?>
            <div class="ad-topbar-right">

                <!-- Notifications (the number is the pending reports) -->
                <button type="button" class="notification" id="notificationButton" aria-label="Notifications">
 

                    <span class="notification-count" id="notificationCount"<?= $sidebarPending > 0 ? "" : ' style="display:none"' ?>><?= $sidebarPending ?></span>

                </button>

            </div>
        <?php endif; ?>

    </header>

<script src="../../asset/js/admin_shell.js" defer></script>