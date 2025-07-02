<?php
require_once("connection.php");

// session_id alın
$sessionId = $_POST["session_id"] ?? null;
$userName = $_POST["user_name"] ?? null;
$message = $_POST["message"] ?? null;
$isMod = isset($_POST["is_mod"]) && $_POST["is_mod"] == 1 ? 1 : 0;

if (!$sessionId || !$userName || !$message) {
    echo json_encode([
        "success" => false,
        "message" => "Parametreler eksik!"
    ]);
    exit;
}

// Mesajı kaydet
$stmt = $conn->prepare("
    INSERT INTO chat_messages (session_id, user_name, message, is_mod, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("issi", $sessionId, $userName, $message, $isMod);
$stmt->execute();
$stmt->close();

echo json_encode(["success" => true]);
