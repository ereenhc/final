<?php
session_start();
require_once("connection.php");

$code = $_GET['code'] ?? '';
if (!$code) die("Oturum kodu eksik.");
$sessionCode = $code;

$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
if (!($row = $result->fetch_assoc())) die("Geçersiz kod");
$session_id = $row['id'];
$stmt->close();

$tokenName = "attendee_token_$session_id";
if (!isset($_COOKIE[$tokenName])) 
{
    $token = bin2hex(random_bytes(16));
    setcookie($tokenName, $token, time()+86400, "/");
} 
else 
{
    $token = $_COOKIE[$tokenName];
}

$quizStmt = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY created_at DESC");
$quizStmt->bind_param("i", $session_id);
$quizStmt->execute();
$quizRes = $quizStmt->get_result();
$quizzes = [];
while ($quiz = $quizRes->fetch_assoc()) $quizzes[] = $quiz;
$quizStmt->close();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Quiz</title>
    <style>
        body 
        { 
            font-family: Arial, sans-serif; 
            background: #f3f3f3; 
            padding: 40px;
        }
        .quiz-box 
        { 
            background: #fff; 
            border-radius: 8px; 
            padding: 35px; 
            max-width: 420px; 
            margin: 45px auto; 
            box-shadow:0 2px 12px #bbb; 
            margin-bottom: 35px;
        }
        h2 
        { 
            color: #2d4059; 
        }
        .answer-btn 
        {
             display:block;
             width:100%;
             margin-top:14px;
             padding:15px 0;
             background:#4285f4;
             color:#fff;
             border:none;
             border-radius:5px;
             font-size:18px;
             cursor:pointer;
             font-weight:bold;
             transition:.2s;
        }
        .answer-btn:disabled
        {
            background: #ccc; 
            cursor: default; 
        }
        .chosen 
        { 
            background: #4caf50 !important; 
        }
    </style>
</head>
<body>
    <?php if (empty($quizzes)): ?>
        <div class="quiz-box"><b>Bu oturumda hiç soru yok!</b></div>
    <?php else: ?>
        <?php foreach ($quizzes as $quiz): ?>
            <div class="quiz-box">
                <h2><?= htmlspecialchars($quiz['question']) ?></h2>
                <?php
                $stmt = $conn->prepare("SELECT * FROM quiz_answers WHERE quiz_id = ? AND attendee_token = ?");
                $stmt->bind_param("is", $quiz['id'], $token);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $options = [];
                if ($quiz['type'] == "coktan") 
                {
                    $optQ = $conn->prepare("SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY option_key");
                    $optQ->bind_param("i", $quiz['id']);
                    $optQ->execute();
                    $optRes = $optQ->get_result();
                    while ($opt = $optRes->fetch_assoc()) 
                    {
                        $options[$opt['option_key']] = $opt['option_text'];
                    }
                    $optQ->close();
                }
                ?>
                <?php if ($old): ?>
                    <div style="margin-top:24px;">
                        <b>Cevabınız:</b>
                        <?php
                        if ($quiz['type'] == "coktan") 
                        {
                            $cevapKey = $old['answer'];
                            $cevapMetin = isset($options[$cevapKey]) ? $cevapKey . ' - ' . $options[$cevapKey] : $cevapKey;
                            echo htmlspecialchars($cevapMetin);
                        } 
                        else 
                        {
                            echo ($old['answer'] == "dogru" ? "Doğru" : "Yanlış");
                        }
                        ?>
                        <br>
                        <span style="color:green;">(Yanıtınız kaydedildi, tekrar değiştiremezsiniz)</span>
                    </div>
                <?php else: ?>
                    <form onsubmit="return false;">
                        <?php if ($quiz['type'] == "coktan"): ?>
                            <?php foreach ($options as $key => $optText): ?>
                                <button type="button" class="answer-btn"
                                    onclick="submitQuiz(<?= $quiz['id'] ?>, '<?= htmlspecialchars($key) ?>', this)">
                                    <?= htmlspecialchars($key) . " - " . htmlspecialchars($optText) ?>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <button type="button" class="answer-btn" onclick="submitQuiz(<?= $quiz['id'] ?>, 'dogru', this)">Doğru</button>
                            <button type="button" class="answer-btn" onclick="submitQuiz(<?= $quiz['id'] ?>, 'yanlis', this)">Yanlış</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<script>
function submitQuiz(quiz_id, ans, btn) 
{
    let parent = btn.parentElement;
    parent.querySelectorAll('.answer-btn').forEach(b => b.disabled = true);

    fetch("submitQuizAnswer.php", 
    {
        method: "POST",
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: "quiz_id=" + quiz_id + "&answer=" + encodeURIComponent(ans)
    }).then(r => r.json())
    .then(d => 
    {
        if (d.success) 
        {
            alert("Cevabınız kaydedildi!");
            location.reload();
        } 
        else 
        {
            alert("Hata: " + (d.message || ''));
            parent.querySelectorAll('.answer-btn').forEach(b => b.disabled = false);
        }
    });
}

function checkSessionAlive() 
{
    fetch('isSessionAlive.php?code=<?= htmlspecialchars($sessionCode) ?>')
    .then(r => r.json())
    .then(data => 
    {
        if (!data.exists) 
        {
            document.body.innerHTML = `
                <div style="text-align:center;padding:120px;font-size:2rem;color:#c00;">
                  Oturum sonlandırıldı. Ana sayfaya yönlendiriliyorsunuz...
                </div>`;
            setTimeout(function() 
            {
                window.location.href = 'anasayfa.php';
            }, 3000);
        }
    });
}
setInterval(checkSessionAlive, 2500);
</script>
</body>
</html>