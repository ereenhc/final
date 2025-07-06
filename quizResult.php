<?php
session_start();
require_once("connection.php");

$sessionCode = $_GET['code'] ?? $_SESSION['current_session_code'] ?? '';
if (!$sessionCode) die("Oturum kodu yok!");

$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$result = $stmt->get_result();
if (!($row = $result->fetch_assoc())) die("Geçersiz kod");
$session_id = $row['id'];
$stmt->close();

// Soruları çek
$quizQ = $conn->prepare("SELECT * FROM quiz WHERE session_id=? ORDER BY created_at ASC");
$quizQ->bind_param("i", $session_id);
$quizQ->execute();
$quizRes = $quizQ->get_result();
$questions = [];
while ($q = $quizRes->fetch_assoc()) $questions[] = $q;
$quizQ->close();

$qIndex = isset($_GET['q']) ? intval($_GET['q']) : 0;
if ($qIndex < 0 || $qIndex >= count($questions)) $qIndex = 0;
$quiz = $questions[$qIndex] ?? null;

if (!$quiz) die("<b>Hiç soru yok!</b>");

// Şıkları çek
$options = [];
if ($quiz['type'] == 'coktan') {
    $optQ = $conn->prepare("SELECT option_key, option_text FROM quiz_options WHERE quiz_id = ? ORDER BY option_key");
    $optQ->bind_param("i", $quiz['id']);
    $optQ->execute();
    $optRes = $optQ->get_result();
    while ($opt = $optRes->fetch_assoc()) {
        $options[$opt['option_key']] = $opt['option_text'];
    }
    $optQ->close();
} else {
    $options = ['dogru' => 'Doğru', 'yanlis' => 'Yanlış'];
}

// ✅ eski sorguya dön
$ansQ = $conn->prepare("
    SELECT username, answer
    FROM quiz_answers
    WHERE quiz_id = ?
");
$ansQ->bind_param("i", $quiz['id']);
$ansQ->execute();
$ansRes = $ansQ->get_result();
$answers = [];
while ($a = $ansRes->fetch_assoc()) $answers[] = $a;
$ansQ->close();

// Şık sayıları
$counts = array_fill_keys(array_keys($options), 0);
foreach ($answers as $a) {
    $key = $a['answer'];
    if (isset($counts[$key])) $counts[$key]++;
}

$total = count($answers);
$dogru_orani = 0;
if ($total > 0 && isset($counts[$quiz['correct_answer']])) {
    $dogru_orani = round(100 * $counts[$quiz['correct_answer']] / $total, 1);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Quiz Sonuçları</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f6f6f6;
        }
        .layout {
            display: flex;
            min-height: 100vh;
        }
       .sidebar {
    width: 300px;
    background-color: #3d83b8;
    color: #fff;
    padding: 20px;
    box-sizing: border-box;
}

.logo {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
}

.logo img {
    margin-right: 10px;
}

.mod-label {
    background: #fff;
    color: #3d83b8;
    padding: 3px 10px;
    border-radius: 5px;
    margin-left: 10px;
    font-size: 14px;
    font-weight: bold;
}

.nav-menu {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.nav-item {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.2);
    padding: 12px 16px;
    text-decoration: none;
    color: #fff;
    font-weight: bold;
    border-radius: 6px;
    transition: background 0.3s;
    font-size: 18px;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.4);
}

.nav-emoji {
    margin-right: 10px;
    font-size: 22px;
}

@media (max-width: 900px) {
    .sidebar {
        width: 180px;
        padding: 15px 8px;
    }
    .nav-item {
        font-size: 14px;
        padding: 10px 12px;
    }
    .logo img {
        width: 40px;
        height: 40px;
    }
    .logo span {
        font-size: 16px;
    }
}

        .main {
            flex: 1;
            padding: 40px;
            box-sizing: border-box;
        }
        .container {
            max-width: 820px;
            background: white;
            border-radius: 10px;
            margin: 0 auto;
            padding: 36px;
            box-shadow: 0 4px 16px #bbb;
        }
        h2 {
            color: #234;
            margin-bottom: 18px;
        }
        .question-nav { margin-bottom:18px;}
        .question-nav a {
            display:inline-block;
            background:#eee;
            color:#333;
            padding:8px 22px;
            border-radius:6px;
            margin-right:6px;
            text-decoration:none;
            font-weight: bold;
        }
        .question-nav a.active {
            background:#4285f4;
            color:white;
        }
        table {
            width:100%;
            border-collapse: collapse;
            margin-top:20px;
        }
        th,td {
            padding: 10px 10px;
            border-bottom:1px solid #ddd;
            text-align:left;
        }
        th { background:#f3f7ff; }
        .dogru { color:green; font-weight:bold; }
        .yanlis { color:#c00; font-weight:bold; }
        .result-summary {
            margin: 18px 0 12px 0;
            font-size: 19px;
            color: #222;
        }
        .pie-wrap { display:flex; align-items:center; gap:38px; }
        .pie-wrap > div { flex:1; }
    </style>
</head>
<body>
<div class="layout">
    <!-- SIDEBAR -->
   <div class="sidebar">
    <div class="logo">
        <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" width="60" height="60" class="logo-icon" style="margin-left: 7px;" />
        <span style="font-size:20px;font-weight:500;margin-left:10px;letter-spacing:0.5px;color:#fff;">QuestionLive</span>
        <span class="mod-label">Mod</span>
    </div>
    <nav class="nav-menu">
        <a href="modChatwall.php" class="nav-item"><span class="nav-emoji">💬</span> Chat</a>
        <a href="modQuiz.php" class="nav-item"><span class="nav-emoji">❔</span> Quiz</a>
        <a href="createSession.php" class="nav-item"><span class="nav-emoji">🎓</span> Session</a>
        <?php if(!empty($sessionCode)): ?>
            <a href="quizResult.php?code=<?=urlencode($sessionCode)?>" class="nav-item"><span class="nav-emoji">🏆</span> Quiz Result</a>
        <?php endif; ?>
    </nav>
</div>


    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="container">
            <h2>Soru #<?= ($qIndex+1) ?> / <?= count($questions) ?></h2>
            <div class="question-nav">
                <?php foreach ($questions as $i=>$s): ?>
                    <a href="?code=<?=urlencode($sessionCode)?>&q=<?=$i?>"<?=($i==$qIndex?' class="active"':'')?>><?=($i+1)?></a>
                <?php endforeach; ?>
            </div>

            <div style="font-size: 20px; margin-bottom:12px; color:#234;">
                <?=htmlspecialchars($quiz['question'])?>
            </div>
            <?php if ($quiz['type'] == 'coktan'): ?>
                <ul>
                    <?php foreach ($options as $k=>$v): ?>
                        <li>
                            <b><?=htmlspecialchars($k)?> - <?=htmlspecialchars($v)?></b>
                            <?php if ($k==$quiz['correct_answer']): ?>
                                <span style="color:green;">(Doğru)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <ul>
                    <?php foreach ($options as $k=>$v): ?>
                        <li>
                            <b><?=htmlspecialchars($v)?></b>
                            <?php if ($k==$quiz['correct_answer']): ?>
                                <span style="color:green;">(Doğru)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="result-summary">
                <b>Doğru seçenek işaretlenme oranı:</b> <span style="color:#339933;"><?= $dogru_orani ?>%</span> 
                (<?= $counts[$quiz['correct_answer']] ?? 0 ?> / <?= $total ?> kişi)
            </div>
            <div class="pie-wrap">
                <div><canvas id="pieChart" width="240" height="240"></canvas></div>
                <div>
                    <table>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Seçilen Şık</th>
                            <th>Doğru mu?</th>
                        </tr>
                        <?php foreach ($answers as $a): 
                            $isDogru = ($a['answer'] == $quiz['correct_answer']);
                            $selectedText = $options[$a['answer']] 
                                ?? (($a['answer'] == '1') ? 'Doğru' : (($a['answer'] == '0') ? 'Yanlış' : $a['answer']));

                            $kullanici = $a['username'] ?: "Anonim Kullanıcı";
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($kullanici) ?></td>
                                <td><?= htmlspecialchars($selectedText) ?></td>
                                <td><?= $isDogru ? '<span class="dogru">✔</span>' : '<span class="yanlis">✗</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const labels = <?= json_encode(array_values($options)) ?>;
    const data = <?= json_encode(array_values($counts)) ?>;
    const bgColors = ["#4caf50","#f44336","#2196f3","#ff9800","#9c27b0","#607d8b","#ffd600"];
    new Chart(document.getElementById('pieChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: bgColors,
            }]
        },
        options: {
            plugins: {
                legend: { display: true, position: "bottom" }
            }
        }
    });
</script>
</body>
</html>
