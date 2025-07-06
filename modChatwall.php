<?php
session_start();
require_once("connection.php");

if (!isset($_SESSION['current_session_code'])) {
    echo "<script>
            alert('Oturum kodu belirtilmedi.');
            window.location.href = 'createSession.php';
          </script>";
    exit;
}

if (!isset($_SESSION['uye_adi'])) {
    echo "<script>
            alert('Giriş bilgisi eksik.');
            window.location.href = 'anasayfa.php';
          </script>";
    exit;
}

$modAd = $_SESSION['uye_adi'];
$sessionCode = $_SESSION['current_session_code'];

// Oturum ID çek
$stmt = $conn->prepare("SELECT id, chatwall FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if ($row['chatwall'] != 1) {
        echo "<script>
                alert('Bu özellik bu oturumda aktif değil.');
                window.location.href = 'createSession.php';
              </script>";
        exit;
    }
    $sessionId = $row['id'];
} else {
    echo "<script>
            alert('Geçersiz oturum kodu.');
            window.location.href = 'createSession.php';
          </script>";
    exit;
}

// ✅ KATILIMCI KAYIT
$uyeId = $_SESSION["uye_id"] ?? null;

// Cookie token kontrolü
$tokenName = "attendee_token_$sessionId";
$token = $_COOKIE[$tokenName] ?? null;

if (!$token) {
    $token = bin2hex(random_bytes(16));
    setcookie($tokenName, $token, time() + 86400, "/");
}

if ($uyeId !== null) {
    $stmt = $conn->prepare("
        SELECT id FROM session_attendees
        WHERE session_id = ? AND uye_id = ?
    ");
    $stmt->bind_param("ii", $sessionId, $uyeId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $stmt = $conn->prepare("
            INSERT INTO session_attendees (session_id, attendee_token, uye_id, joined_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("isi", $sessionId, $token, $uyeId);
        $stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>ChatWall (Mod)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #faebd7;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: row;
            min-height: 100vh;
        }
        .sidebar {
            width: 300px;
            background-color: #3d83b8;
            color: #fff;
            padding: 25px 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 40px;
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
        .main-container {
            flex-grow: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
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
            justify-content: space-between;
        }
        .delete-btn {
            background: #222a50;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            cursor: pointer;
            margin-left: 12px;
            font-size: 0.98em;
            transition: background 0.15s;
        }
        .delete-btn:hover {
            background: #f47c2c;
        }
        #chat-form {
            display: flex;
            justify-content: center;
            gap: 15px;
            width: 65%;
            margin-bottom: 100px;
        }
        #chat-form input {
            padding: 10px;
            font-size: 16px;
            width: 90%;
        }
        #chat-form button {
            padding: 10px 20px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        #chat-form button:hover {
            background-color: #4cae4c;
        }
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.3) transparent;
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
    </style>
</head>
<body>
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

    <!-- MAIN -->
    <div class="main-container">
        <h2>Chat - Oturum: <?php echo htmlspecialchars($sessionCode); ?></h2>

        <!-- Katılımcılar -->
        <div style="
            width: 70%;
            background: #eee;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        ">
            <h3 style="margin-top: 0;">Katılımcılar</h3>
            <div id="attendees-list"></div>
        </div>

        <div id="chat-container">
            <div id="chat-box"></div>
            <form id="chat-form" autocomplete="off">
                <input type="text" id="message" placeholder="Mesajınız" required>
                <button type="submit">Gönder</button>
            </form>
        </div>
    </div>

    <script>
        const sessionId = "<?php echo htmlspecialchars($sessionId); ?>";
        const userName = <?php echo json_encode($modAd); ?>;

        function loadAttendees() {
            fetch('getAttendees.php?session_id=' + sessionId)
                .then(r => r.json())
                .then(users => {
                    let container = document.querySelector("#attendees-list");
                    container.innerHTML = "";

                    if (users.length === 0) {
                        container.innerHTML = "<p>Henüz katılımcı yok.</p>";
                        return;
                    }

                    let ul = document.createElement("ul");
                    ul.style.listStyle = "none";
                    ul.style.padding = "0";
                    ul.style.margin = "0";

                    users.forEach(u => {
                        let li = document.createElement("li");
                        li.style.padding = "5px 0";
                        li.style.borderBottom = "1px solid #ccc";
                        li.textContent = u;
                        ul.appendChild(li);
                    });

                    container.appendChild(ul);
                });
        }

        loadAttendees();
        setInterval(loadAttendees, 3000);

        function loadMessages() {
            fetch('loadMessages.php?session_id=' + sessionId + '&mod=1')
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
            const msg = document.getElementById('message').value.trim();
            if (userName.toLowerCase().includes('mod') || userName.includes('★')) {
                alert('Kullanıcı adında MOD veya yıldız sembolü kullanamazsınız!');
                return;
            }
            fetch('sendMessage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'session_id=' + encodeURIComponent(sessionId) +
                      '&user_name=' + encodeURIComponent(userName) +
                      '&message=' + encodeURIComponent(msg) +
                      '&is_mod=1'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    document.getElementById('message').value = '';
                    loadMessages();
                } else {
                    alert(resp.message || "Bir hata oluştu.");
                }
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-btn')) {
                const msgId = e.target.getAttribute('data-id');
                if (confirm('Mesajı silmek istediğine emin misin?')) {
                    fetch('delete_message.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(msgId) +
                              '&session_id=' + encodeURIComponent(sessionId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadMessages();
                        } else {
                            alert(data.message || 'Silme başarısız.');
                        }
                    })
                    .catch(err => console.error(err));
                }
            }
        });
    </script>
</body>
</html>
