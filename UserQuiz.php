<?php
session_start();
require_once("connection.php");

// --- Oturum kodu al ---
$code = $_GET['code'] ?? '';
if (!$code) die("Oturum kodu eksik.");

// Session ID ve status çek
$stmt = $conn->prepare("SELECT id, status FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) die("Geçersiz oturum kodu.");

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
        // Token yok → yeni token üret
        $token = bin2hex(random_bytes(16));
        setcookie($tokenName, $token, time() + 86400, "/");
        $stmt = $conn->prepare("INSERT INTO session_attendees (session_id, attendee_token, attendee_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $session_id, $token, $attendeeName);
        $stmt->execute();
        $stmt->close();
    } else {
        $token = $_COOKIE[$tokenName];
        // Token varsa attendee_name boş mu kontrol et
        $stmt = $conn->prepare("SELECT attendee_name FROM session_attendees WHERE session_id = ? AND attendee_token = ?");
        $stmt->bind_param("is", $session_id, $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) {
            if (empty($row['attendee_name'])) {
                // attendee_name boş → güncelle
                $stmt = $conn->prepare("UPDATE session_attendees SET attendee_name = ? WHERE session_id = ? AND attendee_token = ?");
                $stmt->bind_param("sis", $attendeeName, $session_id, $token);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // Token DB'de yoksa → insert et
            $stmt = $conn->prepare("INSERT INTO session_attendees (session_id, attendee_token, attendee_name) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $session_id, $token, $attendeeName);
            $stmt->execute();
            $stmt->close();
        }
    }
} else {
    // LOGIN yoksa → Anonim kullanıcılar
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

// Katılımcı sayısını al
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM session_attendees WHERE session_id = ?");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$attendeeCount = $row['cnt'] ?? 0;

// ---------- ✅ Quizleri getir ----------
$quizStmt = $conn->prepare("SELECT * FROM quiz WHERE session_id = ? ORDER BY created_at DESC");
$quizStmt->bind_param("i", $session_id);
$quizStmt->execute();
$quizRes = $quizStmt->get_result();

$quizzes = [];
while ($quiz = $quizRes->fetch_assoc()) {
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
            background: #faebd7;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 340px;
            background: linear-gradient(140deg, #3482c8 80%, #57e8ec 120%);
            box-shadow: 2px 0 7px 0 rgba(49,66,120,.05);
            min-height: 100vh;
            color: #fff;
            padding: 40px 24px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 28px;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 60px;
            color: #fff;
            gap: 12px;
        }
        .sidebar-logo img {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            background: #fff;
        }
        .menu {
            width: 100%;
            margin-top: 20px;
            flex-direction: column;
        }
        .menu a {
            font-size: 25px;
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 11px;
            text-decoration: none;
            font-weight: bold;
            color: #fff;
            margin-bottom: 15px;
            transition: background .22s;
            border: 2.5px solid #e5e6ea;
        }
        .menu a.active,
        .menu a:hover {
            background: #fff;
            color: #276bb7;
            border-color: #9fd7fa;
        }
        @media (max-width: 900px) {
            .sidebar {
                width: 100px;
                padding: 14px 7px;
            }
            .sidebar-logo {
                font-size: 14px;
            }
            .menu a {
                font-size: 15px;
                padding: 7px;
            }
        }
        .main-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 50px 0 0 0;
            min-height: 100vh;
        }
        .session-info {
            background: #fff;
            border-radius: 13px;
            box-shadow: 0 2px 6px #bbb4;
            padding: 20px 45px;
            margin-bottom: 34px;
            font-size: 19px;
            color: #22344a;
            font-weight: 500;
            display: flex;
            gap: 44px;
            align-items: center;
        }
        .quiz-box {
            background: #fff;
            border-radius: 8px;
            padding: 35px;
            max-width: 420px;
            margin: 0 auto 35px auto;
            box-shadow: 0 2px 12px #bbb;
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
        .answer-btn.disabled {
            background: #ccc !important;
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
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" />
            QuestionLive
        </div>
        <div class="menu">
            <a href="userChatwall.php?code=<?php echo urlencode($code); ?>">💬 Chat</a>
            <a href="userQuiz.php?code=<?php echo urlencode($code); ?>" class="active">❔ Quiz</a>
            <a href="joinSession.php?code=<?php echo urlencode($code); ?>">🎓 Session</a>
        </div>
    </div>
    <div class="main-container">
        <div class="session-info">
            <div><b>Oturum Kodu:</b> <?php echo htmlspecialchars($code); ?></div>
            <div><b>Katılımcı Sayısı:</b> <?php echo $attendeeCount; ?></div>
            <div><b>Kullanıcı Adı:</b> <?php echo htmlspecialchars($attendeeName); ?></div>
        </div>
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
                    <div>
                        <?php foreach ($quiz['options'] as $key => $text) : ?>
                            <button 
                                type="button"
                                class="answer-btn"
                                onclick="submitAnswer(<?php echo (int)$quiz['id']; ?>, '<?php echo htmlspecialchars($key); ?>', this)">
                                <?php echo htmlspecialchars($text); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<script>
function submitAnswer(quizId, answerKey, clickedBtn) {
    // Tüm butonları disable et
    let buttons = clickedBtn.parentElement.querySelectorAll('.answer-btn');
    buttons.forEach(b => {
        b.disabled = true;
        b.classList.add('disabled');
    });
    // Cevabı gönder
    fetch("answerQuiz.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: "quiz_id=" + quizId + "&answer=" + encodeURIComponent(answerKey)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Cevabınız kaydedildi!");
        } else {
            alert("Hata: " + (data.message || "Bilinmeyen hata"));
        }
    });
}

// ➤ Oturum sonlandı mı kontrolü
const sessionCode = "<?php echo htmlspecialchars($code); ?>";
const sessionId = "<?php echo htmlspecialchars($session_id); ?>";
const tokenName = "attendee_token_" + sessionId;
function checkSessionAlive() {
    fetch('isQuizSessionAlive.php?session_code=' + encodeURIComponent(sessionCode))
        .then(response => response.json())
        .then(data => {
            if (!data.alive) {
                document.body.innerHTML = `
                    <div style="
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        text-align: center;
                    ">
                        <h2 style="color: #c00; font-size: 28px; margin-bottom: 20px;">
                            Oturum sonlandırıldı
                        </h2>
                        <p style="color: #555; font-size: 20px;">
                            Ana sayfaya yönlendiriliyorsunuz...
                        </p>
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = 'anasayfa.php';
                }, 3000);
            }
        });
}
setInterval(checkSessionAlive, 3000);

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
            body: 'session_id=' + encodeURIComponent(sessionId) +
                  '&token=' + encodeURIComponent(tokenValue || "")
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
