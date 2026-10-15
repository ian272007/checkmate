<?php

$db_server = "localhost";
$db_username = "root";
$db_password = "";
$db_db = "checkmate_db";

$conn = new mysqli("localhost", "root", "", "checkmate_db");

if ($conn->connect_error) {
    die("Failed connection: " . $conn->connect_error);
}


if (session_status() === PHP_SESSION_ACTIVE
    && ($_SESSION["role"] ?? "") === "student"
    && isset($_SESSION["user_id"])) {
 
    $banStmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $banStmt->bind_param("i", $_SESSION["user_id"]);
    $banStmt->execute();
    $banRow = $banStmt->get_result()->fetch_assoc();
    $banStmt->close();
 
    if ($banRow && $banRow["status"] === "Banned") {
        $_SESSION = [];
        session_destroy();
 
        if (strpos($_SERVER["SCRIPT_NAME"], "_api") !== false) {
            http_response_code(403);
            header("Content-Type: application/json");
            echo json_encode(["success" => false, "message" => "Your account has been banned."]);
        } else {
            header("Location: /checkmate/authentication/Login/login.php?banned=1");
        }
        exit;
    }
}
?>

