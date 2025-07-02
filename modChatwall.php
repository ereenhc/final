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
            width: 390px;
            background-color: rgb(61, 131, 184);
            border-right: 1px solid #ddd;
            padding: 30px 15px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .logo {
            display: flex;
            align-items: center;
            font-size: 30px;
            font-weight: bold;
            color: #f47c2c;
            margin-bottom: 60px;
        }
        .logo-icon {
            font-size: 35px;
            margin-right: 5px;
            line-height: 1;
        }
        .logo-button {
            display: inline-block;
            background-color: rgba(244, 124, 44, 0.82);
            color: whitesmoke;
            padding: 5px 10px;
            margin-left: 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
            font-size: 28px;
        }
        .logo-button:hover {
            background-color: rgb(0, 62, 71);
        }
        .mod-label {
            color: #14234B;
            font-weight: bold;
            font-size: 1em;
            margin-left: 16px;
            background: #d6e4ff;
            padding: 4px 14px;
            border-radius: 8px;
            letter-spacing: 1px;
        }
        .menu {
            width: 100%;
            border-collapse: collapse;
        }
        .menu td {
            padding: 10px;
        }
        .menu a {
            font-size: 30px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 3px solid #ccc;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .25);
            box-sizing: border-box;
            text-decoration: none;
            font-weight: bold;
            color: #007BFF;
            transition: background .2s, box-shadow .2s;
        }
        .menu a:hover {
            background: #e0e0e0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .35);
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
        /* İnce scrollbar */
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
        /* Firefox scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.3) transparent;
        }
        @media (max-width: 900px) {
            .sidebar {
                width: 160px;
                padding: 15px 7px;
            }
            .logo-button {
                font-size: 18px;
            }
            .mod-label {
                font-size: .92em;
                padding: 3px 8px;
                margin-left: 8px;
            }
            .menu a {
                font-size: 18px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" width="55px" height="55px" class="logo-icon" style="margin-left: 7px;" />
            <a href="anasayfa.php" class="logo-button">QuestionLive</a>
            <span class="mod-label">Mod</span>
        </div>
        <div class="menu">
            <table class="menu">
                <tr><td><a href="modChatwall.php">💬 Chat</a></td></tr>
                <tr><td><a href="modQuiz.php">❔ Quiz</a></td></tr>
                <tr><td><a href="createSession.php">🎓 Session</a></td></tr>
            </table>
        </div>
    </div>
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
