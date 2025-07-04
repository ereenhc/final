<?php
require_once("connection.php");

$code = $_GET['session_code'] ?? '';

if (!$code) {
    echo json_encode(['alive' => false, 'message' => 'Kod yok']);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['alive' => false, 'message' => 'Session bulunamadı']);
    exit;
}

if ($row['status'] === 'closed') {
    echo json_encode(['alive' => false, 'message' => 'Oturum kapalı']);
} else {
    echo json_encode(['alive' => true]);
}
