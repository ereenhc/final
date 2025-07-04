<?php
require_once("connection.php");

$session_code = $_GET["session_code"] ?? null;

$response = [
    'success' => false,
    'attendees' => []
];

if ($session_code) {
    // session_id bul
    $stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
    $stmt->bind_param("s", $session_code);
    $stmt->execute();
    $res = $stmt->get_result();
    $sessionRow = $res->fetch_assoc();
    $stmt->close();

    if ($sessionRow) {
        $sessionId = $sessionRow["id"];

        // katılımcıları çek
        $stmt = $conn->prepare("
            SELECT 
                sa.attendee_token,
                COALESCE(u.uye_adi, sa.attendee_name) AS katilimci_adi,
                sa.joined_at
            FROM session_attendees sa
            LEFT JOIN uyeler u ON u.uye_id = sa.uye_id
            WHERE sa.session_id = ?
        ");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $res = $stmt->get_result();

        $attendees = [];

        while ($row = $res->fetch_assoc()) {
            $attendees[] = [
                'attendee_token' => $row["attendee_token"],
                'attendee_name'  => $row["katilimci_adi"],
                'joined_at'      => $row["joined_at"]
            ];
        }

        $stmt->close();

        $response['success'] = true;
        $response['attendees'] = $attendees;
    } else {
        $response['message'] = 'Oturum bulunamadı.';
    }
} else {
    $response['message'] = 'session_code parametresi eksik.';
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
