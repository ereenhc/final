<?php
require_once("connection.php");

$id = $_POST['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM chat_messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "ID yok."]);
}
