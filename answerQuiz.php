<?php
session_start();
require_once("connection.php");

header("Content-Type: application/json");

$response = [
    "success" => false,
    "message" => ""
];

// Kontroller
if (!isset($_POST['quiz_id'], $_POST['answer'])) {
    $response["message"] = "Eksik parametre.";
    echo json_encode($response);
    exit;
}

$quiz_id = intval($_POST['quiz_id']);
$answer = trim($_POST['answer'] ?? '');

if ($quiz_id <= 0 || $answer === '') {
    $response["message"] = "Geçersiz parametre.";
    echo json_encode($response);
    exit;
}

// Quiz var mı kontrol et
$stmt = $conn->prepare("SELECT id, type, correct_answer FROM quiz WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$res = $stmt->get_result();
$quiz = $res->fetch_assoc();
$stmt->close();

if (!$quiz) {
    $response["message"] = "Quiz bulunamadı.";
    echo json_encode($response);
    exit;
}

// Session ID bul
$stmt = $conn->prepare("SELECT session_id FROM quiz WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    $response["message"] = "Oturum bulunamadı.";
    echo json_encode($response);
    exit;
}

$session_id = $row['session_id'];
$tokenName = "attendee_token_" . $session_id;
$token = $_COOKIE[$tokenName] ?? null;

if (!$token) {
    $response["message"] = "Kullanıcı doğrulanamadı.";
    echo json_encode($response);
    exit;
}

// Daha önce cevap verilmiş mi?
$stmt = $conn->prepare("SELECT id FROM quiz_answers WHERE quiz_id = ? AND attendee_token = ?");
$stmt->bind_param("is", $quiz_id, $token);
$stmt->execute();
$res = $stmt->get_result();
$old = $res->fetch_assoc();
$stmt->close();

if ($old) {
    $response["message"] = "Bu soruya daha önce cevap verdiniz.";
    echo json_encode($response);
    exit;
}

// Cevap kaydet
$stmt = $conn->prepare("
    INSERT INTO quiz_answers (quiz_id, attendee_token, answer)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $quiz_id, $token, $answer);
$stmt->execute();
$stmt->close();

// Doğru mu yanlış mı kontrol et
$isCorrect = false;

if ($quiz['type'] === "dogruyanlis") {
    if ($quiz['correct_answer'] === $answer) {
        $isCorrect = true;
    }
} elseif ($quiz['type'] === "coktan") {
    if ($quiz['correct_answer'] === $answer) {
        $isCorrect = true;
    }
}

$response["success"] = true;
$response["result"] = $isCorrect ? "dogru" : "yanlis";

echo json_encode($response);
exit;
?>
