<?php
session_start();
require_once("connection.php");

$code = $_GET['code'] ?? null;
if (!$code) {
    die("Oturum kodu eksik.");
}
$sessionCode = $code;

// Oturum id'sini bul
$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Geçersiz oturum kodu.");
}
$row = $result->fetch_assoc();
$sessionId = $row['id'];
$stmt->close();

// Kullanıcı adını session'da sakla
$attendeeName = $_SESSION["attendee_name"] ?? null;

// Eğer POST ile isim seçildiyse session'a kaydet
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["user_name"])) {
    $attendeeName = $_POST["user_name"];
    $_SESSION["attendee_name"] = $attendeeName;
}
// Boşsa anonim ata
if (!$attendeeName) {
    $attendeeName = "Anonim";
}

// Katılımcı sayısı çek
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM session_attendees WHERE session_id = ?");
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
$attendeeCount = $row['cnt'] ?? 0;

// Cookie token kontrolü
$tokenName = "attendee_token_$sessionId";
$token = $_COOKIE[$tokenName] ?? null;
if (!$token) {
    $token = bin2hex(random_bytes(16));
    setcookie($tokenName, $token, time() + 86400, "/");
    // Yeni kayıt
    $stmt2 = $conn->prepare("
        INSERT IGNORE INTO session_attendees (session_id, attendee_token, attendee_name, joined_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt2->bind_param("iss", $sessionId, $token, $attendeeName);
    $stmt2->execute();
    $stmt2->close();
} else {
    // Token varsa attendee_name güncelle
    $stmt2 = $conn->prepare("
        UPDATE session_attendees
        SET attendee_name = ?
        WHERE session_id = ? AND attendee_token = ?
    ");
    $stmt2->bind_param("sis", $attendeeName, $sessionId, $token);
    $stmt2->execute();
    $stmt2->close();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Chatwall</title>
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
            padding: 50px 0 0 0;
            display: flex;
            flex-direction: column;
            align-items: center;
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
        h2 { color: #2d4059; }
        #leave-session-btn {
            background-color: #d9534f;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 16px;
        }
        #leave-session-btn:hover {
            background-color: #c9302c;
        }
        #username-select-form {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }
        #username-input {
            padding: 10px;
            font-size: 16px;
            width: 200px;
        }
        #set-username-btn {
            padding: 10px 25px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }
        #set-username-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        #username-locked-msg {
            display: none;
            margin-bottom: 22px;
            color: #333;
            font-weight: bold;
        }
        #chat-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        #chat-box {
            width: 70%;
            height: 500px;
            border: 2px solid #ccc;
            overflow-y: scroll;
            padding: 20px;
            background-color: rgb(255, 252, 240);
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .message {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        #chat-form {
            display: none;
            justify-content: center;
            gap: 15px;
            width: 65%;
            margin-bottom: 100px;
        }
        #chat-form input, #chat-form button {
            padding: 10px;
            font-size: 16px;
        }
        #chat-form input {
            width: 70%;
        }
        #chat-form button {
            width: 25%;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        #chat-form button:hover {
            background-color: #4cae4c;
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
            <a href="userChatwall.php?code=<?php echo urlencode($sessionCode); ?>" class="active">💬 Chat</a>
            <a href="userQuiz.php?code=<?php echo urlencode($sessionCode); ?>">❔ Quiz</a>
            <a href="joinSession.php?code=<?php echo urlencode($sessionCode); ?>">🎓 Session</a>
        </div>
    </div>
    <div class="main-container">
        <div class="session-info">
            <div><b>Oturum Kodu:</b> <?php echo htmlspecialchars($sessionCode); ?></div>
            <div><b>Katılımcı Sayısı:</b> <?php echo $attendeeCount; ?></div>
            <div><b>Kullanıcı Adı:</b> <?php echo htmlspecialchars($attendeeName); ?></div>
        </div>
        <h2>Chatwall</h2>
        <button id="leave-session-btn">Oturumdan Ayrıl</button>
        <form id="username-select-form" autocomplete="off">
            <input type="text" id="username-input" maxlength="32" placeholder="Adınızı girin..." required>
            <button type="submit" id="set-username-btn">Seç</button>
        </form>
        <div id="username-locked-msg"></div>
        <div id="chat-container">
            <div id="chat-box"></div>
            <form id="chat-form" autocomplete="off">
                <input type="text" id="message" maxlength="255" placeholder="Mesajınız" required>
                <button type="submit">Gönder</button>
            </form>
        </div>
    </div>
    <script>
        const sessionId = "<?php echo htmlspecialchars($sessionId); ?>";
        const sessionCode = "<?php echo htmlspecialchars($sessionCode); ?>";
        const usernameKey = "cw_username_" + sessionId;
        const leaveBtn = document.getElementById('leave-session-btn');
        leaveBtn.addEventListener('click', function() {
            if (confirm('Oturumdan ayrılmak istediğinize emin misiniz?')) {
                const tokenName = "attendee_token_" + sessionId;
                let tokenValue = null;
                document.cookie.split(";").forEach(c => {
                    const [name, value] = c.trim().split("=");
                    if (name === tokenName) {
                        tokenValue = value;
                    }
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
                        localStorage.removeItem(usernameKey);
                        window.location.href = 'anasayfa.php';
                    } else {
                        alert("Çıkış yapılamadı: " + (data.message || ""));
                    }
                });
            }
        });
        window.addEventListener('DOMContentLoaded', function() {
            let uname = localStorage.getItem(usernameKey);
            if (uname) lockUsername(uname);
        });
        document.getElementById('username-select-form').addEventListener('submit', function(e) {
            e.preventDefault();
            let uname = document.getElementById('username-input').value.trim();
            if (!uname || /mod|admin|yönetici|★/i.test(uname)) {
                alert("Bu ismi kullanamazsınız!");
                return;
            }
            if (uname.length < 2 || uname.length > 32) {
                alert("Adınız 2-32 karakter arası olmalı!");
                return;
            }
            localStorage.setItem(usernameKey, uname);
            fetch(location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'user_name=' + encodeURIComponent(uname)
            }).then(() => {
                lockUsername(uname);
                location.reload();
            });
        });
        function lockUsername(uname) {
            document.getElementById('username-select-form').style.display = "none";
            document.getElementById('username-locked-msg').style.display = "block";
            document.getElementById('username-locked-msg').innerText = "Kullanıcı adınız: " + uname;
            document.getElementById('chat-form').style.display = "flex";
        }
        function loadMessages() {
            fetch('loadUserMessages.php?session_id=' + sessionId)
                .then(res => res.text())
                .then(data => {
                    const box = document.getElementById('chat-box');
                    box.innerHTML = data;
                    box.scrollTop = box.scrollHeight;
                });
        }
        loadMessages();
        setInterval(loadMessages, 3000);
        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            let uname = localStorage.getItem(usernameKey);
            if (!uname) {
                alert("Önce adınızı seçmelisiniz!");
                return;
            }
            let msg = document.getElementById('message').value.trim();
            if (!msg) return;
            fetch('sendUserMessage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'session_id=' + encodeURIComponent(sessionId) +
                      '&user_name=' + encodeURIComponent(uname) +
                      '&message=' + encodeURIComponent(msg)
            }).then(() => {
                document.getElementById('message').value = '';
                loadMessages();
            });
        });
        function checkSessionAlive() {
            fetch('isSessionAlive.php?code=' + encodeURIComponent(sessionCode))
            .then(r => r.json())
            .then(data => {
                if (!data.exists) {
                  document.body.innerHTML = `
    <div style="
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        flex-direction: column;
        background: #faebd7;
        text-align: center;
        padding: 20px;
        box-sizing: border-box;
    ">
        <div style="
            max-width: 600px;
            margin: 0 auto;
            padding-left: 40px;
            padding-right: 40px;
        ">
            <h2 style="color: #c00; font-size: 32px; margin-bottom: 20px;">
                Oturum Sonlandırıldı
            </h2>
            <p style="color: #333; font-size: 20px;">
                Ana sayfaya yönlendiriliyorsunuz...
            </p>
        </div>
    </div>
`;
                    setTimeout(() => {
                        window.location.href = 'anasayfa.php';
                    }, 3000);
                }
            });
        }
        setInterval(checkSessionAlive, 2500);
    </script>
</body>
</html>
