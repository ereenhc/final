<?php
require_once("connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Şifre sıfırlama işlemi
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];

    if ($new_password !== $confirm_password) {
        die("Şifreler uyuşmuyor.");
    }

    // Token geçerli mi?
    $stmt = $conn->prepare("SELECT uye_id FROM password_resets WHERE token = ? AND expires > ?");
    $now = date("U");
    $stmt->bind_param("si", $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Token geçersiz veya süresi dolmuş.");
    }

    $data = $result->fetch_assoc();
    $uye_id = $data['uye_id'];

    // Şifreyi güncelle
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE uyeler SET uye_sifre = ? WHERE uye_id = ?");
    $stmt->bind_param("si", $password_hash, $uye_id);
    $stmt->execute();

    // Tokeni sil
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    echo "Şifreniz başarıyla güncellendi.";
    exit;
}

// GET ile gelen token varsa forma geçilir
if (!isset($_GET['token'])) {
    die("Geçersiz istek.");
}

$token = $_GET['token'];

$stmt = $conn->prepare("SELECT uye_id, expires FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Token geçersiz veya süresi dolmuş.");
}

$data = $result->fetch_assoc();

if ($data['expires'] < date("U")) {
    die("Token süresi dolmuş.");
}

// Token geçerli, formu göster
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Şifre Sıfırlama</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        form {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <form action="reset_password.php" method="post">
        <h2>Yeni Şifre Belirle</h2>
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="password" name="new_password" placeholder="Yeni sifre" required>
        <input type="password" name="confirm_password" placeholder="Yeni sifre (tekrar)" required>
        <button type="submit">Şifreyi Sıfırla</button>
    </form>
</body>
</html>
