<?php
session_start();
require_once("connection.php");

// Oturum kodunu GET'ten veya SESSION'dan al (kendi sistemine göre ayarla)
$sessionCode = $_GET['code'] ?? $_SESSION['current_session_code'] ?? null;
if (!$sessionCode) {
    echo "<script>alert('Oturum kodu eksik.');window.location.href='anasayfa.php';</script>";
    exit;
}

// Kullanıcı adı session'da tutuluyorsa onu çek
$userName = $_SESSION['uye_adi'] ?? $_SESSION['username'] ?? 'Bilinmeyen';

// Oturumda kaç kişi var? (attendees)
$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc())) {
    echo "<script>alert('Geçersiz oturum kodu.');window.location.href='anasayfa.php';</script>";
    exit;
}
$sessionId = $row['id'];
$stmt->close();

// Kaç kişi katılmış?
$stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM session_attendees WHERE session_id = ?");
$stmt2->bind_param("i", $sessionId);
$stmt2->execute();
$res2 = $stmt2->get_result();
$attendeeCount = ($row2 = $res2->fetch_assoc()) ? $row2['cnt'] : 0;
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Oturuma Katıl</title>
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" />
            QuestionLive
        </div>
        <div class="menu">
            <a href="userChatwall.php?code=<?=urlencode($sessionCode)?>">💬 Chat</a>
            <a href="userQuiz.php?code=<?=urlencode($sessionCode)?>">❔ Quiz</a>
            <a href="joinSession.php?code=<?=urlencode($sessionCode)?>" class="active">🎓 Session</a>
        </div>
    </div>
    <div class="main-container">
        <div class="session-info">
            <div><b>Oturum Kodu:</b> <?php echo htmlspecialchars($sessionCode); ?></div>
            <div><b>Katılımcı Sayısı:</b> <?php echo $attendeeCount; ?></div>
            <div><b>Kullanıcı Adı:</b> <?php echo htmlspecialchars($userName); ?></div>
        </div>
        <!-- Diğer içerik buraya gelecek -->
    </div>
</body>
</html>
<?php $conn->close(); ?>
