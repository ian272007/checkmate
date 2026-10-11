<?php

include "../config/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form values
    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");

    // Combine names for the existing database column
    $fullname = trim($first_name . " " . $last_name);

    $student_id = trim($_POST["student_id"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $year = trim($_POST["year"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // CHECK REQUIRED FIELDS
    if (
        $first_name === "" ||
        $last_name === "" ||
        $student_id === "" ||
        $email === "" ||
        $course === "" ||
        $year === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $message = "Please fill out all fields.";

    // CHECK PASSWORD
    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";

    // CHECK IF ACCOUNT ALREADY EXISTS
    } else {

        $check_sql = "
            SELECT id
            FROM users
            WHERE student_id = ? OR email = ?
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {

            $message = "Database error: " . $conn->error;

        } else {

            $check_stmt->bind_param(
                "ss",
                $student_id,
                $email
            );

            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {

                $message = "Student ID or email is already registered.";
                $check_stmt->close();

            } else {

                $check_stmt->close();

                // HASH PASSWORD
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // DEFAULT ROLE
                $role = "student";

                // INSERT USER
                // Keep the existing full_name database column.
               $sql = "
    INSERT INTO users ( first_name, last_name, student_id, email, course, year_level, password, role)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    $message = "Database error: " . $conn->error;

                } else {

                    // 8 values => 8 type characters
                  $stmt->bind_param(
                    "ssssssss",
                    $first_name,
                    $last_name,
                    $student_id,
                    $email,
                    $course,
                    $year,
                    $hashed_password,
                    $role
                    );

                    if ($stmt->execute()) {

                        $stmt->close();

                        header("Location: Login/login.php");
                        exit();

                    } else {

                        $message = "Error creating account: " . $stmt->error;
                        $stmt->close();
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Sign up - CheckMate</title>

    <link
        rel="stylesheet"
        href="/checkmate/asset/css/register.css"
    >
</head>

<body>

    <!-- LOGO -->
    <div class="logo">

        <svg viewBox="0 0 24 24">
            <path d="M7 21h12v-2H7v2zm1-3h10c0-3 1-5 1-8 0-4-3-7-7-7l-1-2-2 3C6 5 4 8 4 10l3 2 2-2 1 1c-2 2-2 4-2 7z"/>
        </svg>

        CHECKMATE

    </div>

    <div class="logo-line"></div>

    <!-- SIGN UP CARD -->
    <div class="card">

        <p class="small-title">
            CREATE YOUR ACCOUNT
        </p>

        <h1>
            Sign up to CheckMate
        </h1>

        <p class="subtitle">
            Join our community and start asking,
            answering, and learning together.
        </p>

        <form
            id="signupForm"
            method="POST"
            action="register.php"
            novalidate
        >

            <!-- FIRST NAME + LAST NAME -->
            <div class="row">

                <div class="field">

                    <label for="firstName">
                        First Name
                    </label>

                    <div class="input-box">

                        <input
                            type="text"
                            id="firstName"
                            name="first_name"
                            placeholder="Enter your first name"
                            autocomplete="given-name"
                        >

                    </div>

                </div>

                <div class="field">

                    <label for="lastName">
                        Last Name
                    </label>

                    <div class="input-box">

                        <input
                            type="text"
                            id="lastName"
                            name="last_name"
                            placeholder="Enter your last name"
                            autocomplete="family-name"
                        >

                    </div>

                </div>

            </div>

            <!-- STUDENT ID -->
            <div class="field">

                <label for="studentId">
                    Student ID
                </label>

                <div class="input-box">

                    <input
                        type="text"
                        id="studentId"
                        name="student_id"
                        maxlength="10"
                        placeholder="Enter your student ID"
                        autocomplete="off"
                    >

                </div>

            </div>

            <!-- EMAIL -->
            <div class="field">

                <label for="email">
                    Email Address
                </label>

                <div class="input-box">

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        autocomplete="email"
                    >

                </div>

            </div>

            <!-- COURSE + YEAR -->
            <div class="row">

                <div class="field">

                    <label for="course">
                        Course
                    </label>

                    <div class="input-box">

                        <select id="course" name="course">

                            <option value="">
                                Select your course
                            </option>

                            <option value="BS Information Technology">
                                BS Information Technology
                            </option>

                            <option value="BS Computer Science">
                                BS Computer Science
                            </option>

                            <option value="BS Information Systems">
                                BS Information Systems
                            </option>

                            <option value="BS Engineering">
                                BS Engineering
                            </option>

                        </select>

                    </div>

                </div>

                <div class="field">

                    <label for="year">
                        Year Level
                    </label>

                    <div class="input-box">

                        <select id="year" name="year">

                            <option value="">
                                Select your year level
                            </option>

                            <option value="1st Year">
                                1st Year
                            </option>

                            <option value="2nd Year">
                                2nd Year
                            </option>

                            <option value="3rd Year">
                                3rd Year
                            </option>

                            <option value="4th Year">
                                4th Year
                            </option>

                        </select>

                    </div>

                </div>

            </div>

            <!-- PASSWORD -->
            <div class="field">

                <label for="password">
                    Password
                </label>

                <div class="input-box">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="new-password"
                    >

                    <button
                        type="button"
                        class="eye-btn"
                        data-target="password"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>

            <!-- CONFIRM PASSWORD -->
            <div class="field">

                <label for="confirm">
                    Confirm Password
                </label>

                <div class="input-box">

                    <input
                        type="password"
                        id="confirm"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                    >

                    <button
                        type="button"
                        class="eye-btn"
                        data-target="confirm"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>

            <!-- TERMS -->
            <div class="terms">

                <input
                    type="checkbox"
                    id="agree"
                >

                <label for="agree">
                    I agree to the
                    <a href="#">Terms and Conditions</a>
                    and
                    <a href="#">Privacy Policy</a>
                </label>

            </div>

            <!-- ERROR MESSAGE -->
            <p class="error" id="errorMsg">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <!-- SUBMIT -->
            <button
                type="submit"
                class="create-btn"
            >
                Create Account
                <span class="arrow">&rarr;</span>
            </button>

        </form>

        <p class="signin-text">
            Already have an account?

            <a href="/checkmate/authentication/Login/login.php">
                Sign in
            </a>
        </p>

    </div>

    <script src="/checkmate/asset/js/register.js"></script>

</body>
</html>
