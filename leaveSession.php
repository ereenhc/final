<?php
require_once("connection.php");

$sessionId = $_POST["session_id"] ?? null;
$token = $_POST["token"] ?? null;

if (!$sessionId || !$token) {
    echo json_encode([
        "success" => false,
        "message" => "session_id veya token eksik."
    ]);
    exit;
}

// Kayıt var mı?
$stmt = $conn->prepare("
    SELECT id FROM session_attendees
    WHERE session_id = ? AND attendee_token = ?
");
$stmt->bind_param("is", $sessionId, $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Katılımcı bulunamadı."
    ]);
    exit;
}

// Sil
$stmt = $conn->prepare("
    DELETE FROM session_attendees
    WHERE session_id = ? AND attendee_token = ?
");
$stmt->bind_param("is", $sessionId, $token);
$stmt->execute();

echo json_encode(["success" => true]);
