<?php

ob_start();
session_start();

include "../../config/config.php";

// error messages shown under each box (empty = no error)
$id_error       = "";
$password_error = "";

$username     = "";
$account_type = "student";

// Messages saved by the last failed login. They are shown ONE time only,
// so when the page is refreshed they are gone.
if (isset($_SESSION["login_flash"])) {

    $flash = $_SESSION["login_flash"];

    unset($_SESSION["login_flash"]);

    $id_error       = $flash["id_error"]       ?? "";
    $password_error = $flash["password_error"] ?? "";
    $username       = $flash["username"]       ?? "";
    $account_type   = $flash["account_type"]   ?? "student";
}

// student was kicked out because the account was banned
if (($_GET["banned"] ?? "") === "1" && $id_error === "") {
    $id_error = "Your account has been banned. Please contact the administrator.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form values
    $username = trim($_POST["student_id"] ?? "");
    $password = $_POST["password"] ?? "";

    // Get selected account type
    if (($_POST["account_type"] ?? "") == "admin") {
        $account_type = "admin";
    } else {
        $account_type = "student";
    }

    // Find the user
    $sql = "SELECT * FROM users WHERE student_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        // Get the role from database
        $user_role = strtolower(trim($user["role"]));

        // Check password.
        // Normal accounts have a hash. Some old accounts (admin1, admin2, admin3)
        // still have plain text, so we accept those once and upgrade them to a hash.
        $stored = $user["password"];

        if (password_get_info($stored)["algoName"] !== "unknown") {

            $password_ok = password_verify($password, $stored);

        } else {

            $password_ok = hash_equals($stored, $password);

            if ($password_ok) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upgrade->bind_param("si", $new_hash, $user["id"]);
                $upgrade->execute();
                $upgrade->close();
            }
        }

        if ($password_ok) {

            // Check if selected account type matches database role
            if ($user_role != $account_type) {

                // shown under the Student ID box
                $id_error = "This account is not a " . $account_type . " account.";

            } elseif (($user["status"] ?? "Active") === "Banned") {

                // shown under the Student ID box
                $id_error = "Your account has been banned. Please contact the administrator.";

            } else {

                // Save user information in session
                $_SESSION["user_id"]    = $user["id"];
                $_SESSION["student_id"] = $user["student_id"];
                $_SESSION["role"]       = $user_role;

                // The table has first_name and last_name (there is no full_name column)
                $_SESSION["firstname"] = trim($user["first_name"] ?? "");
                $_SESSION["lastname"]  = trim($user["last_name"] ?? "");

                // Redirect based on role
                if ($user_role == "admin") {

                    header("Location: /checkmate/view/admin/admin_home.php");
                    exit();

                } elseif ($user_role == "student") {

                    header("Location: /checkmate/view/student/student_home.php");
                    exit();

                }
            }

        } else {

            // shown under the Password box
            $password_error = "Incorrect password.";

        }

    } else {

        // The Student ID does not exist. Show the red message under the
        // Student ID box only. (The password is checked once the ID is correct.)
        $id_error = "Incorrect Student ID.";

    }

    $stmt->close();

    // Login failed: save the messages and reload the page with a normal GET request.
    // This way, refreshing the page does not re-send the form and the red messages disappear.
    if ($id_error !== "" || $password_error !== "") {

        $_SESSION["login_flash"] = [
            "id_error"       => $id_error,
            "password_error" => $password_error,
            "username"       => $username,
            "account_type"   => $account_type
        ];

        $conn->close();

        header("Location: login.php");
        exit();
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- =========================================
         BASIC PAGE SETTINGS
    ========================================== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CHECKMATE - Login</title>


    <!-- =========================================
         CONNECT CSS
    ========================================== -->
    <link rel="stylesheet" href="../../asset/css/login.css">

</head>


<body>


    <!-- =========================================
         BACKGROUND CHESS DECORATION
    ========================================== -->

    <div class="background-chess chess-one">♞</div>
    <div class="background-chess chess-two">♟</div>
    <div class="background-chess chess-three">♜</div>
    <div class="background-chess chess-four">♗</div>


    <!-- =========================================
         MAIN LOGIN PAGE
    ========================================== -->

    <main class="login-page">


        <!-- =====================================
             LEFT SIDE
        ====================================== -->

        <section class="left-side">

            <div class="left-content">


                <!-- Logo -->
                <div class="logo">

                    <span class="logo-piece">♞</span>

                    <span>CHECKMATE</span>

                </div>


                <!-- Small line -->
                <div class="gold-line"></div>


                <!-- Main text -->
                <div class="intro">

                    <p class="small-title">
                        PEER-TO-PEER COLLABORATION
                    </p>


                    <h1>
                        Find the right peer.
                        <span>Make the right move.</span>
                    </h1>


                    <p class="description">

                        Connect with fellow students,
                        exchange knowledge, and work
                        together to solve academic problems.

                    </p>

                </div>


                <!-- Chess information -->
                <div class="chess-message">

                    <div class="chess-symbol">
                        ♟
                    </div>

                    <div>

                        <p>
                            Every question is a move.
                        </p>

                        <span>
                            Your next move starts here.
                        </span>

                    </div>

                </div>


            </div>

        </section>



        <!-- =====================================
             RIGHT SIDE
        ====================================== -->

        <section class="right-side">


            <div class="login-container">


                <!-- =================================
                     FORM HEADER
                ================================== -->

                <div class="form-header">

                    <p class="welcome">
                        WELCOME BACK
                    </p>


                    <h2>
                        Sign in to CHECKMATE
                    </h2>


                    <p class="subtitle">
                        Enter your account details to continue.
                    </p>

                    <?php if (($_GET['reset'] ?? '') === 'success'): ?>
                        <p style="color:#6fbf8b;margin-top:10px">Password updated. Please sign in.</p>
                    <?php endif; ?>

                </div>



                <!-- =================================
                     ACCOUNT TYPE
                ================================== -->

                <div class="account-section">

                    <p class="section-label">
                        ACCOUNT TYPE
                    </p>


                    <div class="account-options">


                        <!-- Student -->
                        <button
                            type="button"
                            class="account-option <?php if ($account_type == 'student') { echo 'active'; } ?>"
                            data-type="student">

                            <span class="account-piece">
                                ♙
                            </span>

                            <span class="account-name">
                                Student
                            </span>

                        </button>


                        <!-- Admin -->
                        <button
                            type="button"
                            class="account-option <?php if ($account_type == 'admin') { echo 'active'; } ?>"
                            data-type="admin">

                            <span class="account-piece">
                                ♜
                            </span>

                            <span class="account-name">
                                Admin
                            </span>

                        </button>


                    </div>

                </div>



                <!-- =================================
                     LOGIN FORM
                ================================== -->

                <form id="loginForm" method="post" action="login.php" novalidate>

                    <!-- Hidden input: JavaScript updates this when the user clicks Student or Admin -->
                    <input
                        type="hidden"
                        id="accountType"
                        name="account_type"
                        value="<?php echo htmlspecialchars($account_type); ?>"
                    >


                    <!-- Username -->
                    <div class="form-group">

                        <label for="username">
                            Student ID
                        </label>


                        <input
                            type="text"
                            id="username"
                            name="student_id"
                            placeholder="Enter your Student ID"
                            autocomplete="student_id"
                            value="<?php echo htmlspecialchars($username); ?>"
                            class="<?php echo $id_error !== '' ? 'input-error' : ''; ?>"
                        >

                        <!-- red message under the Student ID box -->
                        <p
                            class="field-error <?php echo $id_error !== '' ? 'show' : ''; ?>"
                            id="idError"
                            role="alert">
                            <?php echo htmlspecialchars($id_error); ?>
                        </p>

                    </div>



                    <!-- Password -->
                    <div class="form-group">

                        <div class="password-top">

                            <label for="password">
                                Password
                            </label>


                            <a href="forgot_password.php">Forgot password?</a>
                        </div>


                        <div class="password-box">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                class="<?php echo $password_error !== '' ? 'input-error' : ''; ?>"
                            >


                            <!-- =================================
                                 PASSWORD EYE BUTTON
                            ================================== -->

                            <button
                                type="button"
                                id="passwordToggle"
                                class="password-toggle"
                                aria-label="Show password">


                                <!-- Eye slash -->
                                <svg
                                    id="eyeSlash"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round">

                                    <path d="M3 3L21 21"></path>

                                    <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4"></path>

                                    <path d="M9.9 4.3A10.6 10.6 0 0 1 12 4C17.2 4 21 9 22 12C21.6 13.2 20.7 14.7 19.3 16"></path>

                                    <path d="M6.1 6.1C4.2 7.4 2.8 9.3 2 12C3 15 6.8 20 12 20C13.7 20 15.2 19.5 16.5 18.8"></path>

                                </svg>


                                <!-- Normal eye -->
                                <svg
                                    id="eye"
                                    class="hidden"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round">

                                    <path d="M2 12C2 12 5.5 5 12 5C18.5 5 22 12 22 12C22 12 18.5 19 12 19C5.5 19 2 12 2 12Z"></path>

                                    <circle cx="12" cy="12" r="3"></circle>

                                </svg>


                            </button>

                        </div>

                        <!-- red message under the Password box -->
                        <p
                            class="field-error <?php echo $password_error !== '' ? 'show' : ''; ?>"
                            id="passwordError"
                            role="alert">
                            <?php echo htmlspecialchars($password_error); ?>
                        </p>

                    </div>



                    <!-- =================================
                         REMEMBER ME
                    ================================== -->

                    <div class="remember-row">

                        <label>

                            <input
                                type="checkbox"
                                id="rememberMe"
                            >

                            <span>
                                Remember me
                            </span>

                        </label>

                    </div>



                    <!-- =================================
                         LOGIN BUTTON
                    ================================== -->

                    <button
                        type="submit"
                        class="login-button">

                        <span>
                            Sign in
                        </span>

                        <span class="arrow">
                            →
                        </span>

                    </button>


                </form>



                <!-- =================================
                     REGISTER
                ================================== -->

                <div class="register">

                    <span>
                        Don't have an account?
                    </span>

                    <a href="/checkmate/authentication/register.php" id="registerLink">Create an account</a>

                </div>



                <!-- =================================
                     BOTTOM CHESS DECORATION
                ================================== -->

                <div class="bottom-chess">

                    <span>♟</span>

                    <div></div>

                    <span>♞</span>

                    <div></div>

                    <span>♟</span>

                </div>


                <!-- Footer -->

                <p class="footer-text">
                    CHECKMATE • Student Collaboration Platform
                </p>


            </div>

        </section>

    </main>



    <!-- =========================================
         TOAST MESSAGE (small notes like "Admin account selected")
    ========================================== -->

    <div id="toast" class="toast"></div>



    <!-- =========================================
         CONNECT JAVASCRIPT
    ========================================== -->

    <script src="../../asset/js/login.js"></script>


</body>

</html>