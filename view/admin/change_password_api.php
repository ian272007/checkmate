<?php
/*
 * Admin change password API (JSON)
 * Save as: view/admin/change_password_api.php
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';

function respond($ok, $message)
{
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

// only logged-in admins
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(false, 'Please log in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.');
}

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    respond(false, 'Please fill in all three password boxes.');
}

if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) ||
    !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    respond(false, 'The new password does not meet all of the rules.');
}

if ($new !== $confirm) {
    respond(false, 'The new passwords do not match.');
}

if ($new === $current) {
    respond(false, 'The new password must be different from the current one.');
}

// get the saved password of this admin
$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    respond(false, 'Account not found.');
}

$stored = $row['password'];

// the stored password can be a hash (normal) or old plain text
if (password_get_info($stored)['algoName'] !== 'unknown') {
    $currentOk = password_verify($current, $stored);
} else {
    $currentOk = hash_equals($stored, $current);
}

if (!$currentOk) {
    respond(false, 'Current password is incorrect.');
}

// save the new password as a hash
$newHash = password_hash($new, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $newHash, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    respond(false, 'Could not update the password. Please try again.');
}

$stmt->close();

respond(true, 'Password updated.');