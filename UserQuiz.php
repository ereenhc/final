<?php
session_start();
require_once("connection.php");

// --- Oturum kodu al ---
$code = $_GET['code'] ?? '';

if (!$code) {
    die("Oturum kodu eksik.");
}

// Session ID ve status çek
$stmt = $conn->prepare("SELECT id, status FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Geçersiz oturum kodu.");
}

$session_id = $row['id'];
$session_status = $row['status'];
$tokenName = "attendee_token_$session_id";
$attendeeName = null;
$token = null;

// ---------- ✅ Oturum kapalı mı kontrol et ----------
if ($session_status === 'closed') {
    setcookie($tokenName, "", time() - 3600, "/");
    header("Location: oturumSonlandi.php");
    exit;
}

// ---------- ✅ LOGIN OLANI KONTROL ET ----------
if (isset($_SESSION['uye_adi']) && $_SESSION['uye_adi'] !== '') {
    $attendeeName = $_SESSION['uye_adi'];

    if (!isset($_COOKIE[$tokenName])) {
        $token = bin2hex(random_bytes(16));
        setcookie($tokenName, $token, time() + 86400, "/");

        $stmt = $conn->prepare("INSERT INTO session_attendees (session_id, attendee_token, attendee_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $session_id, $token, $attendeeName);
        $stmt->execute();
        $stmt->close();
    } else {
        $token = $_COOKIE[$tokenName];
    }
} else {
    if (isset($_COOKIE[$tokenName])) {
        $token = $_COOKIE[$tokenName];

        $stmt = $conn->prepare("SELECT attendee_name FROM session_attendees WHERE session_id = ? AND attendee_token = ?");
        $stmt->bind_param("is", $session_id, $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $attendeeName = $row['attendee_name'] ?? null;
    }

    if (!$token || !$attendeeName) {
        if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['attendee_name'])) {
            $attendeeName = trim($_POST['attendee_name']);
            if ($attendeeName == "") die("Adınızı boş bırakmayınız.");

            $token = bin2hex(random_bytes(16));
            setcookie($tokenName, $token, time() + 86400, "/");

            $stmt = $conn->prepare("INSERT INTO session_attendees (session_id, attendee_token, attendee_name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $session_id, $token, $attendeeName);
            $stmt->execute();
            $stmt->close();
        } else {
            ?>
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <title>Katılım</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background: #f3f3f3;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .box {
                        background: #fff;
                        padding: 40px;
                        border-radius: 8px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                        text-align: center;
                    }
                    input[type="text"] {
                        width: 100%;
                        padding: 10px;
                        margin-top: 15px;
                        font-size: 16px;
                        border: 1px solid #bbb;
                        border-radius: 4px;
                    }
                    button {
                        margin-top: 20px;
                        background: #4285f4;
                        color: #fff;
                        border: none;
                        padding: 12px 25px;
                        font-size: 16px;
                        border-radius: 4px;
                        cursor: pointer;
                    }
                    button:hover {
                        background: #2a64c5;
                    }
                </style>
            </head>
            <body>
                <form method="post" class="box">
                    <h2>Adınızı Giriniz</h2>
                    <input type="text" name="attendee_name" placeholder="Adınız" required>
                    <button type="submit">Katıl</button>
                </form>
            </body>
            </html>
            <?php
            exit;
        }
    }
}

// ---------- ✅ Quizleri getir ----------
$quizStmt = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY created_at DESC");
$quizStmt->bind_param("i", $session_id);
$quizStmt->execute();
$quizRes = $quizStmt->get_result();

$quizzes = [];

while ($quiz = $quizRes->fetch_assoc()) 
{
    // Şıkları quiz_options tablosundan çek
    $stmt = $conn->prepare("
        SELECT option_key, option_text
        FROM quiz_options
        WHERE quiz_id = ?
        ORDER BY option_key
    ");
    $stmt->bind_param("i", $quiz['id']);
    $stmt->execute();
    $res = $stmt->get_result();

    $options = [];
    while ($row = $res->fetch_assoc()) {
        $options[$row['option_key']] = $row['option_text'];
    }
    $stmt->close();

    $quiz['options'] = $options;
    $quizzes[] = $quiz;
}
$quizStmt->close();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Quiz</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            padding: 40px;
        }
        .quiz-box {
            background: #fff;
            border-radius: 8px;
            padding: 35px;
            max-width: 420px;
            margin: 45px auto;
            box-shadow: 0 2px 12px #bbb;
            margin-bottom: 35px;
        }
        h2 {
            color: #2d4059;
        }
        .answer-btn {
            display: block;
            width: 100%;
            margin-top: 14px;
            padding: 15px 0;
            background: #4285f4;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            font-weight: bold;
            transition: .2s;
        }
        .answer-btn:disabled {
            background: #ccc;
            cursor: default;
        }
        .logout-btn {
            background: crimson;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .quiz-image {
            max-width: 100%;
            margin: 15px 0;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div style="text-align:right; max-width: 420px; margin: 0 auto;">
    <button id="leave-session-btn" class="logout-btn">Oturumdan Ayrıl</button>
</div>

<?php if (empty($quizzes)) : ?>
    <p style="text-align:center; color:#555;">Bu oturumda henüz quiz eklenmemiş.</p>
<?php else : ?>
    <?php foreach ($quizzes as $quiz) : ?>
        <div class="quiz-box">
            <h2><?php echo htmlspecialchars($quiz['question']); ?></h2>
            <?php if (!empty(trim($quiz['image_path'] ?? ''))) : ?>
                <img src="uploads/quiz_images/<?php echo htmlspecialchars($quiz['image_path']); ?>" class="quiz-image">
            <?php endif; ?>
            <form method="post" action="answerQuiz.php">
                <input type="hidden" name="quiz_id" value="<?php echo (int)$quiz['id']; ?>">
                <?php
                $hasOption = false;
                foreach ($quiz['options'] as $key => $text) :
                    if (trim($text) !== "") :
                        $hasOption = true;
                ?>
                    <button type="submit" name="answer" value="<?php echo $key; ?>" class="answer-btn">
                        <?php echo htmlspecialchars($text); ?>
                    </button>
                <?php endif; endforeach; ?>

                <?php if (!$hasOption) : ?>
                    <p style="color:#555; margin-top:10px;">Bu soruya ait seçenek bulunamadı.</p>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
const sessionCode = "<?php echo htmlspecialchars($code); ?>";
const sessionId = "<?php echo htmlspecialchars($session_id); ?>";
const tokenName = "attendee_token_" + sessionId;

// Oturum sonlandırma kontrolü (ping)
setInterval(() => {
    fetch('pingSession.php?session_code=' + encodeURIComponent(sessionCode))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status === 'closed') {
                alert("Bu oturum sonlandırıldı.");
                window.location.href = 'endSession.php';
            }
        });
}, 3000);

document.getElementById('leave-session-btn').addEventListener('click', function() {
    if (confirm('Oturumdan ayrılmak istediğinize emin misiniz?')) {
        let tokenValue = null;
        document.cookie.split(";").forEach(c => {
            const [name, value] = c.trim().split("=");
            if (name === tokenName) tokenValue = value;
        });
        fetch('leaveSession.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'session_id=' + encodeURIComponent(sessionId) + '&token=' + encodeURIComponent(tokenValue || "")
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.cookie = `${tokenName}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`;
                window.location.href = 'anasayfa.php';
            } else alert("Hata: " + data.message);
        });
    }
});
</script>

</body>
</html>
