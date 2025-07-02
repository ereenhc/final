<?php
require_once("connection.php");

$sessionId = $_GET["session_id"] ?? null;

$users = [];

if ($sessionId) {
    $stmt = $conn->prepare("
        SELECT COALESCE(u.uye_adi, sa.attendee_name) as katilimci_adi
        FROM session_attendees sa
        LEFT JOIN uyeler u ON u.uye_id = sa.uye_id
        WHERE sa.session_id = ?
    ");
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if (!empty($row["katilimci_adi"])) {
            $users[] = $row["katilimci_adi"];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($users);
?>
