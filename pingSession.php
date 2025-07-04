<?php
require_once("connection.php");

$sessionCode = $_GET['session_code'] ?? '';

if (!$sessionCode) {
    echo json_encode(['success' => false, 'message' => 'Kod eksik']);
    exit;
}

$stmt = $conn->prepare("SELECT status FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı']);
    exit;
}

$status = $row['status'];

echo json_encode(['success' => true, 'status' => $status]);
exit;
