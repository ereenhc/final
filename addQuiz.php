<?php
require_once("connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') 
{
    $session_code = $_POST['session_code'] ?? '';
    $type         = $_POST['type'] ?? '';
    $question     = trim($_POST['question'] ?? '');
    $correct      = $_POST['correct'] ?? '';

    if ($session_code == '' || $question == '' || $type == '') {
        echo json_encode(['success' => false, 'message' => 'Eksik veri gönderildi.']);
        exit;
    }

    // Session ID bul
    $stmt = $conn->prepare("SELECT id FROM sessions WHERE session_code = ?");
    $stmt->bind_param("s", $session_code);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!($row = $res->fetch_assoc())) 
    {
        echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı']);
        exit;
    }
    $session_id = $row['id'];
    $stmt->close();

    // Resim upload işlemi
    $imagePath = null;

    if (!empty($_FILES['media']['name'])) 
    {
        $uploadDir = 'uploads/quiz_images/';
        if (!is_dir($uploadDir)) 
        {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadFile)) 
        {
            $imagePath = $fileName;
        }
    }

    // quiz tablosuna insert
    $stmt = $conn->prepare("
        INSERT INTO quiz 
        (session_id, question, type, correct_answer, image_path) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $session_id, $question, $type, $correct, $imagePath);
    $stmt->execute();
    $quiz_id = $stmt->insert_id;
    $stmt->close();

    if ($type == "coktan") 
    {
        for ($i = 1; $i <= 4; $i++) 
        {
            $field = 'option' . $i;
            if (isset($_POST[$field]) && trim($_POST[$field]) != "") 
            {
                $opt = trim($_POST[$field]);
                $stmt2 = $conn->prepare("
                    INSERT INTO quiz_options (quiz_id, option_key, option_text) 
                    VALUES (?, ?, ?)
                ");
                $stmt2->bind_param("iis", $quiz_id, $i, $opt);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }

    echo json_encode(['success' => true]);
    exit;
} 
else 
{
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}
?>
