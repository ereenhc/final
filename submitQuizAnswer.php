<?php
session_start();
require_once("connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $quizId = $_POST["quiz_id"] ?? null;
    $selectedOption = $_POST["selected_option"] ?? null;
    $attendeeToken = $_POST["attendee_token"] ?? null;

    if (!$quizId || !$selectedOption || !$attendeeToken) {
        die("Eksik veri!");
    }

    $stmt = $conn->prepare("SELECT correct_answer FROM quiz WHERE id = ?");
    $stmt->bind_param("i", $quizId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $correctOption = $row["correct_answer"];
        $isCorrect = ($selectedOption === $correctOption) ? 1 : 0;
        $_SESSION["quiz_result"] = $isCorrect ? "dogru" : "yanlis";

        $stmtInsert = $conn->prepare("INSERT INTO quiz_answers (quiz_id, attendee_token, selected_option, is_correct, answered_at) VALUES (?, ?, ?, ?, NOW())");
        $stmtInsert->bind_param("issi", $quizId, $attendeeToken, $selectedOption, $isCorrect);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    $stmt->close();
    header("Location: answerQuiz.php");
    exit;
}
?>