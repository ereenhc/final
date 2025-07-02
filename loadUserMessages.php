<?php
require_once("connection.php");

// GET parametresiyle session_id gelmeli
$sessionId = $_GET["session_id"] ?? null;

if (!$sessionId) {
    exit("session_id eksik.");
}

// O oturumdaki mesajları çek
$stmt = $conn->prepare("
    SELECT user_name, message, is_mod, created_at
    FROM chat_messages
    WHERE session_id = ?
    ORDER BY created_at ASC
");
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$result = $stmt->get_result();

$html = "";

while ($row = $result->fetch_assoc()) {
    $user = htmlspecialchars($row["user_name"]);
    $message = htmlspecialchars($row["message"]);
    $time = date("H:i", strtotime($row["created_at"]));

    $class = $row["is_mod"] ? "style='color:#e53935; font-weight:bold;'" : "";
    $html .= "<div class='message'>
                <span $class>[$time] $user:</span> 
                <span style='margin-left:10px;'>$message</span>
              </div>";
}

echo $html;
