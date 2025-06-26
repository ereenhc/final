<?php
require_once("connection.php");

$session_code = $_GET['session_code'] ?? '';
$is_mod = isset($_GET['mod']) ? true : false;

$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $session_code);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc())) {
    die("Oturum bulunamadı.");
}
$session_id = (int)$row['id'];

$stmt = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY quiz_created_at DESC");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$res = $stmt->get_result();

while ($quiz = $res->fetch_assoc()) {
    echo '<div class="quiz-item">';
    echo '<strong>' . htmlspecialchars($quiz['question']) . '</strong><br>';

    // Medya gösterimi
    if (!empty($quiz['media_path'])) {
        $mediaPath = htmlspecialchars($quiz['media_path']);
        $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm'])) {
            echo '<video controls width="100%" style="margin-top:10px;"><source src="' . $mediaPath . '" type="video/' . $ext . '"></video>';
        } else {
            echo '<img src="' . $mediaPath . '" alt="soru görseli" style="margin-top:10px; max-width:100%; border-radius:8px;">';
        }
    }

    if ($quiz['type'] === 'coktan') {
        $options = ['A', 'B', 'C', 'D'];
        foreach ($options as $opt) {
           $a = isset($_POST["A"]) ? $_POST["A"] : '';
            if (!empty($val)) {
                echo '<div class="option-btn">' . $opt . ') ' . htmlspecialchars($val) . '</div>';
            }
        }
    } else {
        echo '<div class="option-btn">Doğru</div>';
        echo '<div class="option-btn">Yanlış</div>';
    }

    echo '</div>';
}
?>
