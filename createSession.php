<?php
/*******************************
 *  createSession.php
 *******************************/
session_start();
require_once("connection.php");

/* Gelişmiş hata raporu — istemezsen kaldır */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['uye_id'])) {
    echo "<script>alert('Oturum Başlatmak için Önce Giriş Yapmalısınız!!!'); window.location.href='anasayfa.php';</script>";
    exit();
}

$createdBy      = $_SESSION['uye_id'];
$sessionCode    = null;
$sessionActive  = 0;   // 0 = beklemede, 1 = başlatılmış

/* —1— Kullanıcının son bitmemiş oturumunu bul */
$q = $conn->prepare("SELECT session_code, is_active FROM sessions WHERE created_by = ? AND is_finished = 0 ORDER BY id DESC LIMIT 1");
$q->bind_param("i", $createdBy);
$q->execute();
$last = $q->get_result()->fetch_assoc();
if ($last) {
    $sessionCode   = $last['session_code'];
    $sessionActive = (int) $last['is_active'];
}

/* —2— Henüz oturum yoksa ve oda türü seçildiyse yeni kayıt */
elseif (isset($_POST['room_type'])) {

    /* Rastgele 6 haneli kod üret */
    function genCode($len = 6)
    {
        $c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $o = '';
        for ($i = 0; $i < $len; $i++) {
            $o .= $c[rand(0, strlen($c) - 1)];
        }
        return $o;
    }

    $roomType    = $_POST['room_type'];      // chatwall | quiz | panic
    $sessionCode = genCode();

    // Oda tipine göre özellik bayrakları
    $chatwall = $roomType === 'chatwall' ? 1 : 0;
    $quiz     = $roomType === 'quiz'    ? 1 : 0;
    $panic    = $roomType === 'panic'   ? 1 : 0;

    $ins = $conn->prepare(
        "INSERT INTO sessions
             (session_code, created_by, chatwall, quiz, panic, room_type, is_active, is_finished)
         VALUES (?,?,?,?,?,?,0,0)"
    );
    /*                    s        i          i        i      i        s   */
    $ins->bind_param("siiiis", $sessionCode, $createdBy, $chatwall, $quiz, $panic, $roomType);
    $ins->execute();

    $sessionActive = 0;   // yeni oturum beklemede
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Oturum Oluştur</title>
<style>
/*** GENEL ***/
body{font-family:Arial,sans-serif;background:#000;margin:0;display:flex;flex-direction:row-reverse;color:#333;}
h1{font-size:26px;margin:0 0 10px}

/*** SIDEBAR ***/
.sidebar{width:300px;background:#ffdead;padding:30px 15px;box-shadow:2px 0 5px rgba(0,0,0,.05);height:100vh;}
.logo{display:flex;align-items:center;font-size:30px;font-weight:bold;color:#f47c2c;}
.logo-icon{font-size:35px;margin-right:5px;}
.logo-button{background:rgba(244,124,44,.82);color:#fff;padding:5px 10px;margin-left:10px;border-radius:5px;font-weight:bold;text-decoration:none;}
.logo-button:hover{background:#003e47;}
.menu{width:100%;border-collapse:collapse;margin-top:50px;}
.menu td{padding:10px;}
.menu a{display:block;padding:12px;border:3px solid #ccc;border-radius:10px;background:#fff;font-weight:bold;text-decoration:none;font-size:18px;}
.menu a:hover{background:#e0e0e0;}

/*** İÇERİK ***/
.main{flex-grow:1;padding:40px;margin:0 270px;}
.box{background:#eee9e9;border-left:8px solid #4285f4;padding:25px 30px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.05);}
.btn{display:inline-block;margin-top:25px;padding:10px 25px;background:#5cb85c;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer;}
.btn:hover{opacity:.9;}
.code-box{margin-top:30px;padding:20px;background:#f3f3f3;border:2px dashed #999;text-align:center;font-size:24px;font-weight:bold;border-radius:8px;}
.start-btn{background:#007bff;margin-top:15px;}
</style>
</head>
<body>

<!-- === SIDEBAR === -->
<div class="sidebar">
    <div class="logo">
        <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" width="55" height="55" class="logo-icon">
        <a href="anasayfa.php" class="logo-button">QuestionLive</a>
    </div>

    <table class="menu">
        <tr><td><a href="chatwall.php">💬 Chatwall</a></td></tr>
        <tr><td><a href="#">❔ Quiz</a></td></tr>
        <tr><td><a href="#">❕ Panic</a></td></tr>
        <tr><td><a href="createSession.php">🎓 Session</a></td></tr>
    </table>
</div>

<!-- === İÇERİK === -->
<div class="main">
<?php if ($sessionCode === null): ?>
    <!-- 1. ADIM – ODA TÜRÜ SEÇ -->
    <div class="box">
        <h1>Oturum oluşturmak istediğiniz <strong>oda türünü seçin</strong></h1>
        <form method="post">
            <label><input type="radio" name="room_type" value="chatwall" required> Chatwall</label><br><br>
            <label><input type="radio" name="room_type" value="quiz"> Quiz</label><br><br>
            <label><input type="radio" name="room_type" value="panic"> Panic-Buttons</label><br><br>
            <button type="submit" class="btn">Devam et</button>
        </form>
    </div>

<?php else: ?>
    <!-- 2. ADIM – KODU GÖSTER -->
    <div class="box">
        <h1>Oturumunuz hazır!</h1>
        <div class="code-box">Oda Kodu: <?= htmlspecialchars($sessionCode) ?></div>

        <?php if ($sessionActive == 0): ?>
            <!-- Konuşmacı bu butonla oturumu başlatır -->
            <form action="startSession.php" method="post">
                <input type="hidden" name="session_code" value="<?= htmlspecialchars($sessionCode) ?>">
                <button type="submit" class="btn start-btn">Oturumu Başlat</button>
            </form>
        <?php else: ?>
            <p style="margin-top:15px;color:#28a745;font-weight:bold;">Oturum başlatıldı – katılımcılar bağlanabilir.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
</body>
</html>
