<?php
session_start();
require_once("connection.php");

// --- Oturum kodu kontrolü ---
$code = $_GET['code'] ?? '';
if (!$code) {
    echo "Session kodu bulunamadı.";
    exit;
}

$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
if (!($row = $result->fetch_assoc())) {
    echo "Geçersiz kod";
    exit;
}
$session_id = $row['id'];
$stmt->close();

// SORULARI ÇEK
$quizQ = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY created_at ASC");
$quizQ->bind_param("i", $session_id);
$quizQ->execute();
$quizRes = $quizQ->get_result();
$questions = [];
while ($q = $quizRes->fetch_assoc()) $questions[] = $q;
$quizQ->close();

if (empty($questions)) {
    echo "<b>Bu oturumda hiç soru yok!</b>";
    exit;
}

$qIndex = isset($_GET['q']) ? intval($_GET['q']) : 0;
if ($qIndex < 0 || $qIndex >= count($questions)) $qIndex = 0;
$quiz = $questions[$qIndex];

$options = [];
if ($quiz['type'] === "coktan") {
    $optQ = $conn->prepare("SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY option_key");
    $optQ->bind_param("i", $quiz['id']);
    $optQ->execute();
    $optRes = $optQ->get_result();
    while ($opt = $optRes->fetch_assoc()) {
        $options[$opt['option_key']] = $opt['option_text'];
    }
    $optQ->close();
}

// Cevapları çek
$ansQ = $conn->prepare("SELECT * FROM quiz_answers WHERE quiz_id = ?");
$ansQ->bind_param("i", $quiz['id']);
$ansQ->execute();
$ansRes = $ansQ->get_result();
$answers = [];
while ($a = $ansRes->fetch_assoc()) $answers[] = $a;
$ansQ->close();

$user_answers = [];
foreach ($answers as $a) {
    $user_answers[$a['username']] = $a;
}

// Sıralama için tüm kullanıcıların puanlarını çek
$scores = [];
$usersQ = $conn->prepare("SELECT DISTINCT username FROM quiz_answers WHERE username IS NOT NULL");
$usersQ->execute();
$usersRes = $usersQ->get_result();
while ($u = $usersRes->fetch_assoc()) {
    $scores[$u['username']] = 0;
}
$usersQ->close();

foreach ($questions as $soru) {
    $q_id = $soru['id'];
    $correct = strtolower($soru['correct_answer']);
    $qAnsQ = $conn->prepare("SELECT username, answer FROM quiz_answers WHERE quiz_id=?");
    $qAnsQ->bind_param("i", $q_id);
    $qAnsQ->execute();
    $qAnsRes = $qAnsQ->get_result();
    while ($ra = $qAnsRes->fetch_assoc()) {
        $kullanici = $ra['username'];
        if (!isset($scores[$kullanici])) $scores[$kullanici] = 0;
        if (strtolower($ra['answer']) == $correct) {
            $scores[$kullanici] += 100;
        }
    }
    $qAnsQ->close();
}
arsort($scores);
$ranking = array_slice($scores, 0, 5, true);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Quiz İstatistikleri</title>
    <style>
        body { font-family: Arial,sans-serif; background:#f6f6f6; padding:40px;}
        .container { max-width:820px; background:white; border-radius:10px; margin:40px auto; padding:36px; box-shadow:0 4px 16px #bbb;}
        h2 { color: #234; margin-bottom:18px;}
        .question-nav { margin-bottom:18px;}
        .question-nav a { display:inline-block; background:#eee; color:#333; padding:8px 22px; border-radius:6px; margin-right:6px; text-decoration:none; font-weight: bold;}
        .question-nav a.active { background:#4285f4; color:white;}
        table { width:100%; border-collapse: collapse; margin-top:20px;}
        th,td { padding: 12px 10px; border-bottom:1px solid #ddd; text-align:left; font-size:17px;}
        th { background:#f3f7ff;}
        .dogru { color:green; font-weight:bold;}
        .yanlis { color:#c00; font-weight:bold;}
        .scoreboard { background:#e5f8f5; border-radius:9px; padding:10px 16px; margin-bottom:22px;}
        .scoreboard b { color:#4285f4; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Soru #<?= ($qIndex+1) ?> / <?= count($questions) ?></h2>
        <div class="question-nav">
            <?php foreach ($questions as $i=>$s): ?>
                <a href="?code=<?=urlencode($code)?>&q=<?=$i?>"<?=($i==$qIndex?' class="active"':'')?>><?=($i+1)?></a>
            <?php endforeach; ?>
        </div>
        <div style="font-size: 20px; margin-bottom:12px; color:#234;"><?=htmlspecialchars($quiz['question'])?></div>
        <?php if ($quiz['type'] == "coktan"): ?>
            <ul>
                <?php foreach ($options as $k=>$v): ?>
                    <li><b><?=htmlspecialchars($k)?> - <?=htmlspecialchars($v)?></b> <?=($k==$quiz['correct_answer'] ? '<span style="color:green;">(Doğru)</span>':'')?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div><b>Doğru/Yanlış tipi</b> – Doğru: <span style="color:green;">Doğru</span></div>
        <?php endif; ?>

        <table>
            <tr>
                <th>Kullanıcı</th>
                <th>Cevabı</th>
                <th>Doğru mu?</th>
                <th>Cevap Zamanı</th>
                <th>Puanı</th>
            </tr>
            <?php foreach ($user_answers as $kullanici=>$a): ?>
                <tr>
                    <td><?=htmlspecialchars($kullanici)?></td>
                    <td><?=htmlspecialchars($a['answer'])?></td>
                    <td>
                        <?php 
                        $dogru = strtolower($a['answer']) == strtolower($quiz['correct_answer']);
                        echo $dogru ? '<span class="dogru">✔</span>' : '<span class="yanlis">✗</span>';
                        ?>
                    </td>
                    <td><?=htmlspecialchars($a['answered_at'])?></td>
                    <td><?= $dogru ? '100' : '0' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div class="scoreboard">
            <b>Genel Sıralama:</b>
            <ol>
                <?php foreach ($ranking as $isim=>$puan): ?>
                    <li><?=htmlspecialchars($isim)?> – <b><?=$puan?></b> puan</li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</body>
</html>
