<?php
require_once("connection.php");

$sessionCode = $_GET['session_code'] ?? '';
$isMod = isset($_GET['mod']) ? true : false;

if (empty($sessionCode)) {
    exit("Oturum kodu belirtilmemiş.");
}

$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Geçersiz oturum kodu.");
}
$sessionId = $result->fetch_assoc()['id'];
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$result = $stmt->get_result();

while ($quiz = $result->fetch_assoc()) {
    echo "<div class='quiz-item'>";
    echo "<h3><strong>" . htmlspecialchars($quiz['question']) . "</strong></h3>";

    $quizId = $quiz['id'];
    $correct = strtolower(trim($quiz['correct_answer']));
    $type = strtolower($quiz['type']);

    if ($type === 'coktan') {
      
        $stmtOpt = $conn->prepare("SELECT option_key, option_text FROM quiz_options WHERE quiz_id = ?");
        $stmtOpt->bind_param("i", $quizId);
        $stmtOpt->execute();
        $optResult = $stmtOpt->get_result();

        while ($opt = $optResult->fetch_assoc()) {
            $key = strtolower(trim($opt['option_key']));
            $text = $opt['option_text'];

            
            $stmtCount = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM quiz_answers 
                WHERE quiz_id = ? AND LOWER(TRIM(selected_option)) = ?
            ");
            $stmtCount->bind_param("is", $quizId, $key);
            $stmtCount->execute();
            $countResult = $stmtCount->get_result()->fetch_assoc();
            $voteCount = $countResult['total'] ?? 0;
            $stmtCount->close();

            $dogru = ($key === $correct) ? " <span style='color: green;'>✅ <strong>Bu şık doğrudur</strong></span>" : "";
            echo "<p><strong>" . strtoupper($key) . ")</strong> " . htmlspecialchars($text) . "$dogru <span style='color: #555;'>($voteCount kişi)</span></p>";
        }

        $stmtOpt->close();
    } elseif ($type === 'dogruyanlis') {
        $label = $correct === "dogru" ? "✔️ Doğru" : "❌ Yanlış";

        $dogruSayisi = $conn->query("SELECT COUNT(*) AS c FROM quiz_answers WHERE quiz_id = $quizId AND LOWER(TRIM(selected_option)) = 'dogru'")->fetch_assoc()['c'];
        $yanlisSayisi = $conn->query("SELECT COUNT(*) AS c FROM quiz_answers WHERE quiz_id = $quizId AND LOWER(TRIM(selected_option)) = 'yanlis'")->fetch_assoc()['c'];

        echo "<p><strong>Doğru Cevap: </strong> <span style='color: green;'>$label</span></p>";
        echo "<p><strong>İstatistik:</strong> ✔️ $dogruSayisi kişi | ❌ $yanlisSayisi kişi</p>";
    }

    echo "</div>";
}
?>
