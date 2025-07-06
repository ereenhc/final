<?php
require_once("connection.php");

if (!isset($_GET["code"])) {
    echo "Session kodu bulunamadı.";
    exit;
}

$code = $_GET["code"];
$stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('GEÇERSİZ OTURUM KODU!!!'); window.location.href = 'anasayfa.php';</script>";
    exit;
}
$session = $result->fetch_assoc();
$sessionId = $session['id'];
$stmt->close();

$tokenName = "attendee_token_$sessionId";
if (!isset($_COOKIE[$tokenName])) {
    $token = bin2hex(random_bytes(16));
    setcookie($tokenName, $token, time() + 86400, "/");
    $stmt2 = $conn->prepare("INSERT IGNORE INTO session_attendees (session_id, attendee_token) VALUES (?, ?)");
    $stmt2->bind_param("is", $sessionId, $token);
    $stmt2->execute();
    $stmt2->close();
} else {
    $token = $_COOKIE[$tokenName];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    if (mb_strlen($username) < 2 || mb_strlen($username) > 32) {
        $error = "Adınız 2-32 karakter arası olmalı!";
    } elseif (preg_match('/mod|admin|yönetici|★/i', $username)) {
        $error = "Bu ismi kullanamazsınız!";
    } else {
        $stmtCheck = $conn->prepare("SELECT 1 FROM session_attendees WHERE session_id = ? AND username = ?");
        $stmtCheck->bind_param("is", $sessionId, $username);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $error = "Bu isim zaten kullanılıyor, başka bir isim seçin!";
        } else {
            $stmtUpdate = $conn->prepare("UPDATE session_attendees SET username=? WHERE session_id=? AND attendee_token=?");
            $stmtUpdate->bind_param("sis", $username, $sessionId, $token);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            header("Location: joinSession.php?code=" . urlencode($code));
            exit;
        }
        $stmtCheck->close();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Adı Seç</title>
    <style>
        body { font-family: Arial,sans-serif; background: #f3f3f3; padding: 40px; text-align: center; }
        .box { background: #fff; border-radius: 8px; max-width: 420px; margin: 60px auto; padding: 35px 35px 20px 35px; box-shadow:0 2px 12px #bbb;}
        input[type="text"] { font-size: 18px; padding: 10px; width: 80%; border-radius: 6px; border: 1px solid #aaa; margin-bottom: 20px;}
        button { background: #4285f4; color: #fff; padding: 12px 32px; font-size: 18px; border: none; border-radius: 7px; cursor: pointer;}
        button:hover { background: #3367d6; }
        .err { color: #c00; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Oturuma Katılmak İçin İsim Belirle</h2>
        <form method="post" autocomplete="off">
            <?php if (!empty($error)): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <input type="text" name="username" maxlength="32" placeholder="Adınız..." required>
            <br>
            <button type="submit">Devam Et</button>
        </form>
    </div>
</body>
</html>
