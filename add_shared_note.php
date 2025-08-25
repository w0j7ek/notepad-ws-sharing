<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("INSERT INTO notes (user_id, category_id, title, content) VALUES (?, NULL, '', '')");
$stmt->execute([$user_id]);
$note_id = $pdo->lastInsertId();

$room_key = bin2hex(random_bytes(12));
$readonly_link = bin2hex(random_bytes(18));
$stmt = $pdo->prepare("INSERT INTO shared_notes (note_id, room_key, owner_id, can_edit, readonly_link) VALUES (?, ?, ?, 1, ?)");
$stmt->execute([$note_id, $room_key, $user_id, $readonly_link]);

header("Location: share.php?note_id=$note_id&room_key=$room_key&readonly_link=$readonly_link");
exit;