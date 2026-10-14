<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

function respond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

// only logged-in students
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    respond(false, "Your session has expired. Please log in again.", 401);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request.", 405);
}

$current = (string)($_POST["current_password"] ?? "");
$new     = (string)($_POST["new_password"] ?? "");
$confirm = (string)($_POST["confirm_password"] ?? "");

if ($current === "" || $new === "" || $confirm === "") {
    respond(false, "Please fill in all three password boxes.", 422);
}

// same rules as the page (checked again here, because JavaScript can be bypassed)
if (
    strlen($new) < 8 ||
    !preg_match('/[A-Z]/', $new) ||
    !preg_match('/[a-z]/', $new) ||
    !preg_match('/[0-9]/', $new)
) {
    respond(false, "The new password does not meet all of the rules.", 422);
}

if ($new !== $confirm) {
    respond(false, "The new passwords do not match.", 422);
}

if ($new === $current) {
    respond(false, "The new password must be different from the current one.", 422);
}

require_once "../../config/config.php";

$user_id = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");

if (!$stmt) {
    respond(false, "Something went wrong. Please try again.", 500);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    respond(false, "Account not found.", 404);
}

if (!password_verify($current, $row["password"])) {
    respond(false, "Your current password is incorrect.", 403);
}

$hash = password_hash($new, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

if (!$stmt) {
    respond(false, "Something went wrong. Please try again.", 500);
}

$stmt->bind_param("si", $hash, $user_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    respond(false, "Could not update the password. Please try again.", 500);
}

respond(true, "Password updated.");