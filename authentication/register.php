<?php
// Registration is now handled by otp_api.php (OTP email verification).
// This page only shows the form + the OTP step.
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign up - CheckMate</title>
    <link rel="stylesheet" href="/checkmate/asset/css/register.css">
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

    <div class="card">

        <!-- ================= STEP 1: SIGN UP FORM ================= -->
        <div id="signupView">

            <p class="small-title">CREATE YOUR ACCOUNT</p>

            <h1>Sign up to CheckMate</h1>

            <p class="subtitle">
                Join our community and start asking,
                answering, and learning together.
            </p>

            <form id="signupForm" method="POST" action="#" novalidate>

                <!-- FIRST NAME + LAST NAME -->
                <div class="row">
                    <div class="field">
                        <label for="firstName">First Name</label>
                        <div class="input-box">
                            <input type="text" id="firstName" name="first_name"
                                   placeholder="Enter your first name" autocomplete="given-name">
                        </div>
                    </div>

                    <div class="field">
                        <label for="lastName">Last Name</label>
                        <div class="input-box">
                            <input type="text" id="lastName" name="last_name"
                                   placeholder="Enter your last name" autocomplete="family-name">
                        </div>
                    </div>
                </div>

                <!-- STUDENT ID -->
                <div class="field">
                    <label for="studentId">Student ID</label>
                    <div class="input-box">
                        <input type="text" id="studentId" name="student_id" maxlength="10"
                               placeholder="Enter your student ID" autocomplete="off">
                    </div>
                </div>

                <!-- EMAIL -->
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="input-box">
                        <input type="email" id="email" name="email"
                               placeholder="Enter your NCST email address" autocomplete="email">
                    </div>
                </div>

                <!-- COURSE + YEAR -->
                <div class="row">
                    <div class="field">
                        <label for="course">Course</label>
                        <div class="input-box">
                            <select id="course" name="course">
                                <option value="">Select your course</option>
                                <option value="BS Information Technology">BS Information Technology</option>
                                <option value="BS Computer Science">BS Computer Science</option>
                                <option value="BS Information Systems">BS Information Systems</option>
                                <option value="BS Engineering">BS Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="year">Year Level</label>
                        <div class="input-box">
                            <select id="year" name="year">
                                <option value="">Select your year level</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-box">
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" autocomplete="new-password">
                        <!-- eye icon is added by register.js -->
                        <button type="button" class="eye-btn" data-target="password"
                                aria-label="Show password"></button>
                    </div>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div class="field">
                    <label for="confirm">Confirm Password</label>
                    <div class="input-box">
                        <input type="password" id="confirm" name="confirm_password"
                               placeholder="Confirm your password" autocomplete="new-password">
                        <!-- eye icon is added by register.js -->
                        <button type="button" class="eye-btn" data-target="confirm"
                                aria-label="Show password"></button>
                    </div>
                </div>

                <!-- TERMS -->
                <div class="terms">
                    <input type="checkbox" id="agree">
                    <label for="agree">
                        I agree to the
                        <a href="#">Terms and Conditions</a>
                        and
                        <a href="#">Privacy Policy</a>
                    </label>
                </div>

                <!-- ERROR MESSAGE -->
                <p class="error" id="errorMsg"></p>

                <!-- SUBMIT -->
                <button type="submit" class="create-btn" id="createBtn">
                    Create Account
                    <span class="arrow">&rarr;</span>
                </button>

            </form>

            <p class="signin-text">
                Already have an account?
                <a href="/checkmate/authentication/Login/login.php">Sign in</a>
            </p>

        </div>

        <!-- ================= STEP 2: OTP VERIFICATION ================= -->
        <div id="otpView" class="hidden">

            <p class="small-title">VERIFY YOUR EMAIL</p>

            <h1>Enter your code</h1>

            <p class="subtitle otp-subtitle">
                We sent a 6-digit code to
                <strong id="otpEmail"></strong>.
                Check your NCST inbox (and spam folder).
            </p>

            <div class="field">
                <div class="input-box otp-box">
                    <input type="text" id="otpInput" inputmode="numeric"
                           maxlength="6" placeholder="000000"
                           autocomplete="one-time-code">
                </div>
            </div>

            <p class="otp-timer" id="otpTimer"></p>

            <p class="error" id="otpError"></p>
            <p class="success" id="otpSuccess"></p>

            <button type="button" class="create-btn" id="verifyBtn">
                Verify &amp; Create Account
                <span class="arrow">&rarr;</span>
            </button>

            <p class="signin-text">
                Didn't get the code?
                <button type="button" class="link-btn" id="resendBtn" disabled>Resend code</button>
            </p>

            <p class="signin-text">
                <button type="button" class="link-btn" id="backBtn">&larr; Back to sign up</button>
            </p>

        </div>

    </div>

    <script src="/checkmate/asset/js/register.js"></script>

</body>
</html>