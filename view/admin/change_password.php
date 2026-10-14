<?php
session_start();

// only admins can open this page (this check was missing before)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../authentication/Login/login.php");
    exit;
}

// connect to the database (gives us the $conn variable)
require_once "../../config/config.php";

// numbers for the sidebar (before they were a fixed 8 and 3)
$studentCount = 0;
$pendingReports = 0;

$result = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'");
if ($result) {
    $studentCount = (int) $result->fetch_row()[0];
}

$check = $conn->query("SHOW TABLES LIKE 'reports'");
if ($check && $check->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'");
    if ($result) {
        $pendingReports = (int) $result->fetch_row()[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Change Password</title>

    <link rel="stylesheet" href="../../asset/css/admin_layout.css?v=<?= filemtime(__DIR__ . '/../../asset/css/admin_layout.css') ?>">
    <link rel="stylesheet" href="../../asset/css/change_password.css?v=<?= filemtime(__DIR__ . '/../../asset/css/change_password.css') ?>">
</head>

<body>

<?php
// shared sidebar + top bar (partials/admin_shell.php)
// no item is highlighted because Change Password is not in the menu
$activePage      = "";
$pageTitle       = "Change Password";
$pageSub         = "Update the password you use to sign in to CHECKMATE.";
$sidebarStudents = $studentCount;
$sidebarPending  = $pendingReports;

require __DIR__ . "/partials/admin_shell.php";
?>

        <!-- PAGE CONTENT -->
        <section class="content">

            <div class="password-layout">

                <!-- Form -->
                <div class="panel">
                    <div class="panel-body">

                        <div class="panel-label">ACCOUNT</div>
                        <h2 class="panel-title">Change password</h2>

                        <div class="form-group">
                            <label for="currentPassword">Current password</label>
                            <div class="input-wrap">
                                <input type="password" id="currentPassword" class="form-input" autocomplete="current-password">
                                <button type="button" class="toggle-btn" data-target="currentPassword">Show</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="newPassword">New password</label>
                            <div class="input-wrap">
                                <input type="password" id="newPassword" class="form-input" autocomplete="new-password">
                                <button type="button" class="toggle-btn" data-target="newPassword">Show</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword">Confirm new password</label>
                            <div class="input-wrap">
                                <input type="password" id="confirmPassword" class="form-input" autocomplete="new-password">
                                <button type="button" class="toggle-btn" data-target="confirmPassword">Show</button>
                            </div>
                        </div>

                        <!-- error message shows here -->
                        <div class="form-error" id="formError"></div>

                        <div class="form-buttons">
                            <a href="admin_home.php" class="btn-outline">Cancel</a>
                            <button class="btn-gold" id="saveButton">Update Password</button>
                        </div>

                    </div>
                </div>


                <!-- Rules (turn green as the new password meets them) -->
                <div class="panel">
                    <div class="panel-body">

                        <div class="panel-label">REQUIREMENTS</div>
                        <h2 class="panel-title">Password rules</h2>

                        <ul class="rule-list">
                            <li id="ruleLength"><span class="rule-mark">✓</span>At least 8 characters</li>
                            <li id="ruleUpper"><span class="rule-mark">✓</span>One uppercase letter</li>
                            <li id="ruleLower"><span class="rule-mark">✓</span>One lowercase letter</li>
                            <li id="ruleNumber"><span class="rule-mark">✓</span>One number</li>
                            <li id="ruleMatch"><span class="rule-mark">✓</span>Both new passwords match</li>
                        </ul>

                    </div>
                </div>

            </div>

        </section>

    </main>


    <div class="toast" id="toast"></div>

    <!-- was "scr=" before, so this file never loaded -->
    <script src="../../asset/js/change_password.js"></script>

</body>
</html>  