<?php
session_start();

$mesaj = "";
if (isset($_SESSION["quiz_result"])) {
    if ($_SESSION["quiz_result"] === "dogru") {
        $mesaj = "<div class='result success'>✅ Doğru yaptınız!</div>";
    } elseif ($_SESSION["quiz_result"] === "yanlis") {
        $mesaj = "<div class='result error'>❌ Yanlış cevap verdiniz.</div>";
    }
    unset($_SESSION["quiz_result"]);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Cevap Sonucu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #faebd7;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
        }

        .result-box {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .result {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .back-button {
            text-decoration: none;
            padding: 10px 20px;
            background: rgb(61, 131, 184);
            color: white;
            border-radius: 6px;
            font-weight: bold;
        }

        .back-button:hover {
            background: rgb(41, 111, 160);
        }
    </style>
</head>
<body>
    <div class="result-box">
        <?php echo $mesaj; ?>
        <a class="back-button" href="UserQuiz.php">Geri Dön</a>
    </div>
</body>
</html>
