<?php
include 'connection.php';
session_start();

if (!isset($_SESSION['uye_id'])) 
{
    die("Giriş yapmalısınız.");
}

$createdBy = $_SESSION['uye_id'];
$session_id = $_GET['session_id'] ?? null;

if (!$session_id) 
{
    $stmt = $conn->prepare("SELECT session_code FROM sessions WHERE created_by = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $createdBy);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) 
    {
        $session_id = $row['session_code'];
    } 
    else 
    {
        die("Aktif oturum bulunamadı.");
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>ChatWall</title>
    <style>
        body 
        {
            font-family: Arial, sans-serif;
            background: #faebd7;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: row-reverse;
        }

        .sidebar 
        {
            width: 300px;
            background-color: rgb(61, 131, 184);
            border-right: 1px solid #ddd;
            padding: 30px 15px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            height: 100vh;
        }

        .logo 
        {
            display: flex;
            align-items: center;
            font-size: 30px;
            font-weight: bold;
            color: #f47c2c;
        }

        .logo-icon 
        {
            font-size: 35px;
            margin-right: 5px;
            line-height: 1;
        }

        .logo-button 
        {
            display: inline-block;
            background-color: rgba(244, 124, 44, 0.82);
            color: whitesmoke;
            padding: 5px 10px;
            margin-left: 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .logo-button:hover 
        {
            background-color: rgb(0, 62, 71);
        }

        .menu 
        {
            width: 100%;
            border-collapse: collapse;
        }

        .menu td 
        {
            padding: 10px;
        }

        .menu a 
        {
            display: block;
            width: 100%;
            padding: 12px;
            text-align: left;
            border: 3px solid #ccc;
            border-radius: 10px;
            text-decoration: none;
            background-color: #fff;
            font-weight: bold;
            box-sizing: border-box;
            margin-bottom: 3px;
        }

        .menu a:hover 
        {
            background-color: #e0e0e0;
        }

        .main-container 
        {
            flex-grow: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        h2 
        {
            text-align: center;
            margin-bottom: 20px;
        }

        #chat-container 
        {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        #chat-box 
        {
            width: 70%;
            height: 500px;
            border: 2px solid #ccc;
            overflow-y: scroll;
            padding: 20px;
            background-color: #ffdead;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .message 
        {
            margin-bottom: 10px;
        }

        #chat-form 
        {
            display: flex;
            justify-content: center;
            gap: 15px;
            width: 65%;
            margin-bottom: 100px;
        }

        #chat-form input 
        {
            padding: 10px;
            font-size: 16px;
            width: 30%;
        }

        #chat-form button 
        {
            padding: 10px 20px;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;

        }

        #chat-form button:hover 
        {
            background-color: #4cae4c;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">
            <img src="https://cdn.creazilla.com/emojis/49577/monkey-emoji-clipart-xl.png" width="55px" height="55px" class="logo-icon" style="margin-left: 7px; margin-bottom: 50px;" />
            <a href="anasayfa.php" class="logo-button" style="margin-bottom: 50px;">QuestionLive</a>
        </div>

        <div class="menu">
            <table class="menu">
                <tr>
                    <td><a href="chatwall.php">💬 Chatwall</a></td>
                </tr>
                <tr>
                    <td><a href="quiz.php">❔ Quiz</a></td>
                </tr>
                <tr>
                    <td><a href="panic.php">❕ Panic</a></td>
                </tr>
                <tr>
                    <td><a href="createSession.php">🎓 Session</a></td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>