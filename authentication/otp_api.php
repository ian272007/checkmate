<?php
/*
 * Registration OTP API (JSON)
 *   action=send    -> validate form, email OTP (account is NOT created yet)
 *   action=resend  -> send a new OTP
 *   action=verify  -> check OTP, then create the account
 *
 * Pending registration is kept in the PHP session (password is already hashed),
 * so no extra database table is needed.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mail_config.php';

const OTP_TTL         = 600; // 10 minutes
const RESEND_COOLDOWN = 60;  // seconds
const MAX_ATTEMPTS    = 5;   // wrong tries per OTP
const MAX_SENDS       = 5;   // OTP emails per registration

function respond($ok, $message, $extra = [])
{
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

function generate_otp()
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function mask_email($email)
{
    list($name, $domain) = explode('@', $email, 2);
    $visible = substr($name, 0, 2);
    return $visible . str_repeat('*', max(strlen($name) - 2, 1)) . '@' . $domain;
}

function email_or_id_taken($conn, $student_id, $email)
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? OR email = ?");
    $stmt->bind_param("ss", $student_id, $email);
    $stmt->execute();
    $taken = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $taken;
}

function mail_error_message($detail)
{
    if (MAIL_DEBUG) {
        return "Could not send the OTP email. " . $detail;
    }
    return "Could not send the OTP email. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.');
}

$action = $_POST['action'] ?? '';

/* =========================================================
   SEND
   ========================================================= */
if ($action === 'send') {

    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $student_id       = trim($_POST['student_id'] ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $course           = trim($_POST['course'] ?? '');
    $year             = trim($_POST['year'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $student_id === '' || $email === '' ||
        $course === '' || $year === '' || $password === '' || $confirm_password === '') {
        respond(false, 'Please fill out all fields.');
    }

    if (!preg_match('/^\d{4}-\d{5}$/', $student_id)) {
        respond(false, 'Student ID must look like 2025-62390.');
    }

    if (!preg_match('/^[a-z0-9._-]+@ncst\.edu\.ph$/', $email)) {
        respond(false, 'Please use your NCST email.');
    }

    $allowed_courses = [
        'BS Information Technology',
        'BS Computer Science',
        'BS Information Systems',
        'BS Engineering'
    ];
    $allowed_years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    if (!in_array($course, $allowed_courses, true) || !in_array($year, $allowed_years, true)) {
        respond(false, 'Invalid course or year level.');
    }

    // Same password rules as Change Password (checked here too,
    // because JavaScript can be bypassed)
    if (strlen($password) < 8) {
        respond(false, 'Password must be at least 8 characters.');
    }

    if (!preg_match('/[A-Z]/', $password)) {
        respond(false, 'Password must have at least one uppercase letter.');
    }

    if (!preg_match('/[a-z]/', $password)) {
        respond(false, 'Password must have at least one lowercase letter.');
    }

    if (!preg_match('/[0-9]/', $password)) {
        respond(false, 'Password must have at least one number.');
    }

    if ($password !== $confirm_password) {
        respond(false, 'Passwords do not match.');
    }

    if (email_or_id_taken($conn, $student_id, $email)) {
        respond(false, 'Student ID or email is already registered.');
    }

    // Cooldown if the same email just requested a code
    $pending = $_SESSION['pending_reg'] ?? null;
    if ($pending && $pending['email'] === $email) {
        $wait = RESEND_COOLDOWN - (time() - $pending['last_sent']);
        if ($wait > 0) {
            respond(false, "Please wait $wait seconds before requesting another code.", ['wait' => $wait]);
        }
    }

    $otp = generate_otp();

    $_SESSION['pending_reg'] = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'student_id' => $student_id,
        'email'      => $email,
        'course'     => $course,
        'year'       => $year,
        'password'   => password_hash($password, PASSWORD_DEFAULT),
        'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
        'expires'    => time() + OTP_TTL,
        'attempts'   => 0,
        'sends'      => 1,
        'last_sent'  => time()
    ];

    list($sent, $err) = send_otp_email($email, $first_name, $otp, OTP_TTL / 60);

    if (!$sent) {
        unset($_SESSION['pending_reg']);
        respond(false, mail_error_message($err));
    }

    respond(true, 'OTP sent.', [
        'email'    => mask_email($email),
        'cooldown' => RESEND_COOLDOWN,
        'ttl'      => OTP_TTL
    ]);
}

/* =========================================================
   RESEND
   ========================================================= */
if ($action === 'resend') {

    $pending = $_SESSION['pending_reg'] ?? null;

    if (!$pending) {
        respond(false, 'Your session expired. Please fill out the form again.', ['restart' => true]);
    }

    $wait = RESEND_COOLDOWN - (time() - $pending['last_sent']);
    if ($wait > 0) {
        respond(false, "Please wait $wait seconds before requesting another code.", ['wait' => $wait]);
    }

    if ($pending['sends'] >= MAX_SENDS) {
        unset($_SESSION['pending_reg']);
        respond(false, 'Too many codes requested. Please start again later.', ['restart' => true]);
    }

    $otp = generate_otp();

    $_SESSION['pending_reg']['otp_hash']  = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['pending_reg']['expires']   = time() + OTP_TTL;
    $_SESSION['pending_reg']['attempts']  = 0;
    $_SESSION['pending_reg']['sends']     = $pending['sends'] + 1;
    $_SESSION['pending_reg']['last_sent'] = time();

    list($sent, $err) = send_otp_email($pending['email'], $pending['first_name'], $otp, OTP_TTL / 60);

    if (!$sent) {
        respond(false, mail_error_message($err));
    }

    respond(true, 'A new code was sent.', [
        'cooldown' => RESEND_COOLDOWN,
        'ttl'      => OTP_TTL
    ]);
}

/* =========================================================
   VERIFY
   ========================================================= */
if ($action === 'verify') {

    $pending = $_SESSION['pending_reg'] ?? null;

    if (!$pending) {
        respond(false, 'Your session expired. Please fill out the form again.', ['restart' => true]);
    }

    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) {
        respond(false, 'Enter the 6-digit code.');
    }

    if (time() > $pending['expires']) {
        respond(false, 'This code has expired. Please request a new one.', ['expired' => true]);
    }

    if ($pending['attempts'] >= MAX_ATTEMPTS) {
        respond(false, 'Too many wrong attempts. Please request a new code.', ['expired' => true]);
    }

    if (!password_verify($otp, $pending['otp_hash'])) {
        $_SESSION['pending_reg']['attempts']++;
        $left = MAX_ATTEMPTS - $_SESSION['pending_reg']['attempts'];

        if ($left <= 0) {
            respond(false, 'Too many wrong attempts. Please request a new code.', ['expired' => true]);
        }
        respond(false, "Incorrect code. $left attempt(s) left.");
    }

    // OTP is correct -> create the account
    if (email_or_id_taken($conn, $pending['student_id'], $pending['email'])) {
        unset($_SESSION['pending_reg']);
        respond(false, 'Student ID or email is already registered.', ['restart' => true]);
    }

    $role = 'student';

    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, student_id, email, course, year_level, password, role)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        respond(false, 'Database error. Please try again.');
    }

    $stmt->bind_param(
        "ssssssss",
        $pending['first_name'],
        $pending['last_name'],
        $pending['student_id'],
        $pending['email'],
        $pending['course'],
        $pending['year'],
        $pending['password'],
        $role
    );

    if (!$stmt->execute()) {
        $stmt->close();
        respond(false, 'Error creating account. Please try again.');
    }

    $stmt->close();
    unset($_SESSION['pending_reg']);

    respond(true, 'Account created.', ['redirect' => '/checkmate/authentication/Login/login.php']);
}

respond(false, 'Unknown action.');