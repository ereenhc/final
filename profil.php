<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['uye_id'])) {
    header("Location: uyeGiris.php");
    exit;
}

$uye_id = $_SESSION['uye_id'];
$mesaj = "";
$mesaj_turu = "";

// Kullanıcı bilgilerini al
$sql = "SELECT uye_adi, uye_soyadi, uye_mail, uye_sifre FROM uyeler WHERE uye_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uye_id);
$stmt->execute();
$result = $stmt->get_result();
$uye = $result->fetch_assoc();

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mevcutSifre = $_POST['mevcut_sifre'] ?? '';
    $yeniSifre = $_POST['yeni_sifre'] ?? '';
    $sifreTekrar = $_POST['sifre_tekrar'] ?? '';
    $yeniAdi = trim($_POST['uye_adi'] ?? '');
    $yeniSoyadi = trim($_POST['uye_soyadi'] ?? '');
    $yeniMail = trim($_POST['uye_mail'] ?? '');

    // Ad Soyad Mail güncelleme doğrulama
    if (empty($yeniAdi) || empty($yeniSoyadi) || empty($yeniMail)) {
        $mesaj = "Ad, Soyad ve Mail boş bırakılamaz.";
        $mesaj_turu = "error";
    } elseif (!filter_var($yeniMail, FILTER_VALIDATE_EMAIL)) {
        $mesaj = "Geçerli bir e-posta giriniz.";
        $mesaj_turu = "error";
    } else {
        // Şifre değişikliği istenmişse kontrol et
        if ($mevcutSifre || $yeniSifre || $sifreTekrar) {
            if (!password_verify($mevcutSifre, $uye['uye_sifre'])) {
                $mesaj = "Mevcut şifre yanlış.";
                $mesaj_turu = "error";
            } elseif (strlen($yeniSifre) < 6) {
                $mesaj = "Yeni şifre en az 6 karakter olmalıdır.";
                $mesaj_turu = "error";
            } elseif ($yeniSifre !== $sifreTekrar) {
                $mesaj = "Yeni şifreler uyuşmuyor.";
                $mesaj_turu = "error";
            } elseif (password_verify($yeniSifre, $uye['uye_sifre'])) {
                $mesaj = "Yeni şifre, mevcut şifre ile aynı olamaz.";
                $mesaj_turu = "error";
            } else {
                $sifreHash = password_hash($yeniSifre, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE uyeler SET uye_sifre = ?, uye_adi = ?, uye_soyadi = ?, uye_mail = ? WHERE uye_id = ?");
                $update->bind_param("ssssi", $sifreHash, $yeniAdi, $yeniSoyadi, $yeniMail, $uye_id);
                if ($update->execute()) {
                    $mesaj = "Bilgiler ve şifre başarıyla güncellendi.";
                    $mesaj_turu = "success";
                    // Güncel bilgileri tekrar çek
                    $uye['uye_adi'] = $yeniAdi;
                    $uye['uye_soyadi'] = $yeniSoyadi;
                    $uye['uye_mail'] = $yeniMail;
                } else {
                    $mesaj = "Güncelleme sırasında hata oluştu.";
                    $mesaj_turu = "error";
                }
            }
        } else {
            // Şifre değişikliği yok, sadece isim mail güncelle
            $update = $conn->prepare("UPDATE uyeler SET uye_adi = ?, uye_soyadi = ?, uye_mail = ? WHERE uye_id = ?");
            $update->bind_param("sssi", $yeniAdi, $yeniSoyadi, $yeniMail, $uye_id);
            if ($update->execute()) {
                $mesaj = "Bilgiler başarıyla güncellendi.";
                $mesaj_turu = "success";
                $uye['uye_adi'] = $yeniAdi;
                $uye['uye_soyadi'] = $yeniSoyadi;
                $uye['uye_mail'] = $yeniMail;
            } else {
                $mesaj = "Bilgi güncellenirken hata oluştu.";
                $mesaj_turu = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Profil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background:rgb(0, 0, 0);
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .menu {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .menu input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #666;
            border-radius: 6px;
            background-color: #fff;
            color: #000;
        }

        .menu button {
            padding: 7px 14px;
            border: 1px solid #f47c2c;
            background-color: transparent;
            color: #f47c2c;
            border-radius: 6px;
            cursor: pointer;
        }

        .menu button:hover {
            background-color: #f47c2c;
            color: #000;
        }

        header {
            background: #2c3e50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 30px;
            font-weight: bold;
            color: #f47c2c;
        }

        .logo-icon {
            font-size: 36px;
            margin-right: 12px;
            line-height: 1;
        }

        .logo-button {
            display: inline-block;
            background-color: rgba(244, 124, 44, 0.82);
            color: whitesmoke;
            padding: 7.5px 20px;
            margin-left: 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .logo-button:hover {
            background-color: rgb(0, 62, 71);
        }

        header h1 {
            margin: 0;
            font-size: 22px;
        }

        header .logout {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 16px;
        }

        header .logout:hover {
            text-decoration: underline;
        }

        footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 15px;
            position: fixed;
            width: 100%;
            bottom: 0;
        }

        .container {
            max-width: 500px;
            background: white;
            margin: 50px auto 100px;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
        }

        form {
            margin-top: 30px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        .btn {
            background: #3498db;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background: #2980b9;
        }

        .login-box a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #f47c2c;
            text-decoration: none;
        }

        .login-box a:hover {
            text-decoration: underline;
        }

        .message {
            margin-top: 15px;
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
        }

        .message.success {
            color: green;
        }

        .message.error {
            color: red;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">
            <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" width="55px" height="55px" class="logo-icon" style="margin-left: 50px;" />
            <a href="anasayfa.php" class="logo-button">QuestionLive</a>
        </div>
        <h1 style="margin-right: 250px;">Hoş Geldiniz, <?= htmlspecialchars($uye['uye_adi'] . ' ' . $uye['uye_soyadi']) ?></h1>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
    </header>

    <div class="container">
        <h2>Profil Bilgilerim</h2>

        <form method="post" novalidate>
            <label for="uye_adi">Ad:</label>
            <input type="text" id="uye_adi" name="uye_adi" value="<?= htmlspecialchars($uye['uye_adi']) ?>" required>

            <label for="uye_soyadi">Soyad:</label>
            <input type="text" id="uye_soyadi" name="uye_soyadi" value="<?= htmlspecialchars($uye['uye_soyadi']) ?>" required>

            <label for="uye_mail">E-posta:</label>
            <input type="email" id="uye_mail" name="uye_mail" value="<?= htmlspecialchars($uye['uye_mail']) ?>" required>

            <h3>Şifre Güncelle (Opsiyonel)</h3>
            <input type="password" name="mevcut_sifre" placeholder="Mevcut şifreniz">
            <input type="password" name="yeni_sifre" placeholder="Yeni şifre (en az 6 karakter)">
            <input type="password" name="sifre_tekrar" placeholder="Yeni şifre (tekrar)">

            <button type="submit" class="btn">Güncelle</button>

            <div class="login-box">
                <a href="anasayfa.php" style="margin-top: -25px; margin-left: 350px;">Ana Sayfaya Dön</a>
            </div>

            <?php if ($mesaj): ?>
                <div class="message <?= $mesaj_turu ?>"><?= htmlspecialchars($mesaj) ?></div>
            <?php endif; ?>
        </form>
    </div>

    <footer>
        &copy; <?= date("Y") ?> Tüm Hakları Saklıdır. | Canlı Geri Bildirim Sistemi
    </footer>

</body>

</html>

