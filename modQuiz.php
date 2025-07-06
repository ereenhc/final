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

$sessionCode = $_SESSION['current_session_code'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Quiz (Mod)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #faebd7;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 300px;
            background-color: rgb(61, 131, 184);
            padding: 25px 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            color: #fff;
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
        .main-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 40px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        #add-question-btn {
            background: #5cb85c;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 22px;
            padding: 18px 38px;
            cursor: pointer;
            margin-top: 30px;
        }
        #add-question-btn:hover {
            background: #409a40;
        }
        #question-form,
        #select-type {
            display: none;
            margin: 24px 0;
            background: #f2f6fa;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(61, 131, 184, 0.06);
        }
        #question-form input[type="text"],
        #question-form textarea {
            width: 98%;
            padding: 10px;
            margin-bottom: 14px;
            font-size: 16px;
            border-radius: 6px;
            border: 1px solid #bbb;
        }
        .options-row {
            display: flex;
            gap: 8px;
        }
        .options-row input[type="text"] {
            width: 85%;
        }
        .answer-radio {
            margin-right: 6px;
        }
        .submit-btn {
            background: #ff8500;
            color: #fff;
            font-weight: bold;
            padding: 12px 38px;
            border-radius: 7px;
            font-size: 19px;
            border: none;
            cursor: pointer;
        }
        .submit-btn:hover {
            background: #e06309;
        }
        #quiz-list {
            margin-top: 32px;
            width: 100%;
            max-width: 600px;
        }
        .quiz-item {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .10);
            padding: 22px 18px;
        }
        .option-btn {
            background: #4285f4;
            color: #fff;
            font-size: 17px;
            padding: 10px 20px;
            margin: 5px 7px 0 0;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            min-width: 100px;
        }
        .option-btn .count {
            font-size: 15px;
            background: #fff;
            color: #4285f4;
            border-radius: 20px;
            padding: 2px 10px;
            margin-left: 8px;
        }
        .option-btn.selected {
            background: #333;
        }
        #attendee-list {
            margin-top: 40px;
            width: 100%;
            max-width: 600px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }
        #attendee-list h3 {
            margin-top: 0;
            color: #2d4059;
        }
        #attendee-ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #attendee-ul li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 16px;
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
        <h2>Quiz - Oturum: <?php echo htmlspecialchars($sessionCode); ?></h2>
        <button id="add-question-btn">Soru Ekle</button>
        <div id="select-type">
            <label>
                <input type="radio" name="question-type" value="coktan" checked> Çoktan Seçmeli
            </label>
            <label style="margin-left: 32px;">
                <input type="radio" name="question-type" value="dogruyanlis"> Doğru / Yanlış
            </label>
            <br>
            <button id="continue-btn" class="submit-btn" style="margin-top:25px; margin-left:70px">Devam Et</button>
        </div>
        <form id="question-form" enctype="multipart/form-data">
            <div id="question-area"></div>
            <button type="submit" class="submit-btn" style="margin-left:40px">Gönder</button>
        </form>
        <div id="quiz-list"></div>
        <div id="attendee-list">
            <h3>Oturuma Katılanlar</h3>
            <ul id="attendee-ul"></ul>
        </div>
    </div>

<script>
    const addBtn = document.getElementById('add-question-btn');
    const selectType = document.getElementById('select-type');
    const questionForm = document.getElementById('question-form');
    const questionArea = document.getElementById('question-area');
    let currentType = 'coktan';

    addBtn.onclick = function () {
        addBtn.style.display = "none";
        selectType.style.display = "block";
    };

    document.getElementById('continue-btn').onclick = function () {
        selectType.style.display = "none";
        questionForm.style.display = "block";
        currentType = document.querySelector('input[name="question-type"]:checked').value;
        showQuestionForm(currentType);
    };

    function showQuestionForm(type) {
        let html = `<textarea id="soru" placeholder="Soru yazınız..." required></textarea>`;
        if (type === 'coktan') {
            html += `
                <div class="options-row"><input type="text" id="optA" placeholder="A şıkkı" required> <input type="radio" name="correct" value="A" class="answer-radio" checked>Doğru</div>
                <div class="options-row"><input type="text" id="optB" placeholder="B şıkkı" required> <input type="radio" name="correct" value="B" class="answer-radio">Doğru</div>
                <div class="options-row"><input type="text" id="optC" placeholder="C şıkkı"> <input type="radio" name="correct" value="C" class="answer-radio">Doğru</div>
                <div class="options-row"><input type="text" id="optD" placeholder="D şıkkı"> <input type="radio" name="correct" value="D" class="answer-radio">Doğru</div>`;
        } else {
            html += `
                <div class="options-row" style="margin-top:15px;">
                    <label><input type="radio" name="correct" value="dogru" checked> Doğru</label>
                    <label style="margin-left:40px;"><input type="radio" name="correct" value="yanlis"> Yanlış</label>
                </div>`;
        }
        html += `<div style="margin-top: 20px;">
            <label>Resim / Video:</label><br>
            <input type="file" id="media" accept="image/*,video/*">
        </div>`;
        questionArea.innerHTML = html;
    }

    questionForm.onsubmit = function (e) {
        e.preventDefault();
        let data = new FormData();
        data.append('session_code', "<?php echo htmlspecialchars($sessionCode); ?>");
        data.append('type', currentType);
        data.append('question', document.getElementById('soru').value);
        data.append('correct', document.querySelector('input[name="correct"]:checked').value);

        let mediaFile = document.getElementById('media')?.files[0];
        if (mediaFile) {
            data.append('media', mediaFile);
        }

        if (currentType === 'coktan') {
            data.append('option1', document.getElementById('optA').value);
            data.append('option2', document.getElementById('optB').value);
            data.append('option3', document.getElementById('optC').value);
            data.append('option4', document.getElementById('optD').value);
        }

        fetch('addQuiz.php', {
            method: 'POST',
            body: data
        }).then(res => res.json())
        .then(resp => {
            if (resp.success) {
                questionForm.style.display = "none";
                addBtn.style.display = "block";
                loadQuizList();
            } else {
                alert("Soru eklenemedi: " + (resp.message || ""));
            }
        });
    };

    function loadQuizList() {
        fetch('loadQuizList.php?session_code=<?php echo htmlspecialchars($sessionCode); ?>&mod=1')
            .then(res => res.text())
            .then(html => {
                document.getElementById('quiz-list').innerHTML = html;
            });
    }

    function loadAttendees() {
        fetch('getQuizAttendees.php?session_code=<?php echo htmlspecialchars($sessionCode); ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let html = "";
                    if (data.attendees.length > 0) {
                        data.attendees.forEach(a => {
                            let name = a.attendee_name;
                            if (!name || name.trim() === "") {
                                name = "Anonim Kullanıcı";
                            }
                            html += `<li><strong>${name}</strong> <span style="color:#777;font-size:0.9em;">(${a.joined_at})</span></li>`;
                        });
                    } else {
                        html = "<li>Henüz kimse katılmadı.</li>";
                    }
                    document.getElementById("attendee-ul").innerHTML = html;
                }
            });
    }

    loadQuizList();
    loadAttendees();

    setInterval(loadQuizList, 3000);
    setInterval(loadAttendees, 3000);

    setInterval(() => {
        fetch('pingSession.php?session_code=<?php echo htmlspecialchars($sessionCode); ?>');
    }, 3000);
</script>
</body>
</html>
