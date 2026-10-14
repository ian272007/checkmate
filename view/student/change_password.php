<?php

session_start();

if (
    !isset($_SESSION["student_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: ../../authentication/Login/login.php");
    exit();
}

require_once "../../config/config.php";

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

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$firstInitial = substr(trim($user["first_name"] ?? ""), 0, 1);
$lastInitial  = substr(trim($user["last_name"] ?? ""), 0, 1);
$initials     = strtoupper($firstInitial . $lastInitial);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Change Password</title>

    <!-- Bootstrap 5.3.3 (same as the other student pages) -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <script src="../../asset/js/notify.js" defer></script>
    <script src="../../asset/js/user_menu.js" defer></script>

    <!-- SAME layout css as student_home.php, so the sidebar and topbar are identical -->
    <link
        rel="stylesheet"
        href="../../asset/css/student_home.css?v=<?= filemtime(__DIR__ . '/../../asset/css/student_home.css') ?>"
    >

    <!-- only the form + rules styles (all classes start with cp-) -->
    <link
        rel="stylesheet"
        href="../../asset/css/student_change_password.css?v=<?= filemtime(__DIR__ . '/../../asset/css/student_change_password.css') ?>"
    >

</head>

<body>


<!-- SIDEBAR (same as student_home.php) -->
<aside class="sidebar">

    <!-- LOGO -->
    <div class="logo-area">
        <div class="logo-piece">♞</div>

        <div class="logo-text">
            <h1>CHECKMATE</h1>
            <span>PEER COLLABORATION</span>
        </div>
    </div>

    <!-- NEW QUESTION -->
    <a href="questions.php" class="new-question-button">
        <span class="button-icon">+</span>
        <span>New Question</span>
    </a>

    <!-- WORKSPACE MENU (nothing is highlighted: Change Password is not in this menu) -->
    <div class="sidebar-section">

        <div class="section-title">WORKSPACE</div>

        <a href="student_home.php" class="menu-item">
            <span class="menu-icon">⌂</span>
            <span>Home</span>
        </a>

        <a href="questions.php" class="menu-item">
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

    <!-- STUDENT PROFILE -->
    <div class="sidebar-user">

        <div class="user-avatar">
            <?= e($initials) ?>
        </div>

        <div class="user-information">

            <strong>
                <?= e(trim(($user["first_name"] ?? "") . " " . ($user["last_name"] ?? "")) ?: "Student") ?>
            </strong>

            <span>
                <?= e($user["course"] ?? "") ?> <br>

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


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <!-- TOPBAR (same as student_home.php, only the last word changes) -->
        <header class="topbar">

            <div class="topbar-left">

                <span class="topbar-label">
                    STUDENT WORKSPACE
                </span>

                <span class="topbar-divider">
                    /
                </span>

                <span class="topbar-current">
                    Change Password
                </span>

            </div>

        </header>

        <!-- PAGE CONTENT -->
        <section class="cp-content">

            <p class="cp-intro">
                Update the password you use to sign in to CHECKMATE.
            </p>

            <div class="cp-layout">

                <!-- Form -->
                <div class="cp-panel">
                    <div class="cp-panel-body">

                        <div class="cp-label">ACCOUNT</div>
                        <h2 class="cp-title">Change password</h2>

                        <div class="cp-group">
                            <label for="currentPassword">Current password</label>
                            <div class="cp-input-wrap">
                                <input type="password" id="currentPassword" class="cp-input" autocomplete="current-password">
                                <button type="button" class="cp-toggle" data-target="currentPassword">Show</button>
                            </div>
                        </div>

                        <div class="cp-group">
                            <label for="newPassword">New password</label>
                            <div class="cp-input-wrap">
                                <input type="password" id="newPassword" class="cp-input" autocomplete="new-password">
                                <button type="button" class="cp-toggle" data-target="newPassword">Show</button>
                            </div>
                        </div>

                        <div class="cp-group">
                            <label for="confirmPassword">Confirm new password</label>
                            <div class="cp-input-wrap">
                                <input type="password" id="confirmPassword" class="cp-input" autocomplete="new-password">
                                <button type="button" class="cp-toggle" data-target="confirmPassword">Show</button>
                            </div>
                        </div>

                        <!-- error message shows here -->
                        <div class="cp-error" id="formError" role="alert"></div>

                        <div class="cp-buttons">
                            <a href="student_home.php" class="cp-btn-outline">Cancel</a>
                            <button type="button" class="cp-btn-gold" id="saveButton">Update Password</button>
                        </div>

                    </div>
                </div>


                <!-- Rules (turn green as the new password meets them) -->
                <div class="cp-panel">
                    <div class="cp-panel-body">

                        <div class="cp-label">REQUIREMENTS</div>
                        <h2 class="cp-title">Password rules</h2>

                        <ul class="cp-rules">
                            <li id="ruleLength"><span class="cp-mark">✓</span>At least 8 characters</li>
                            <li id="ruleUpper"><span class="cp-mark">✓</span>One uppercase letter</li>
                            <li id="ruleLower"><span class="cp-mark">✓</span>One lowercase letter</li>
                            <li id="ruleNumber"><span class="cp-mark">✓</span>One number</li>
                            <li id="ruleMatch"><span class="cp-mark">✓</span>Both new passwords match</li>
                        </ul>

                    </div>
                </div>

            </div>

        </section>

    </main>

    <div class="cp-toast" id="cpToast" role="status" aria-live="polite"></div>

    <script src="../../asset/js/student_change_password.js"></script>

</body>

</html>