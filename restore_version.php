<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$note_id = $_POST['note_id'] ?? null;
$version_id = $_POST['version_id'] ?? null;

if (!$note_id || !$version_id) {
    echo "Brak danych!";
    exit;
}

// Sprawdź czy użytkownik ma dostęp do notatki
$stmt = $pdo->prepare("SELECT * FROM notes WHERE id=? AND user_id=?");
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    echo "Brak notatki lub uprawnień!";
    exit;
}

// Pobierz wersję
$stmt = $pdo->prepare("SELECT * FROM note_versions WHERE id=? AND note_id=?");
$stmt->execute([$version_id, $note_id]);
$ver = $stmt->fetch();
if (!$ver) {
    echo "Brak wersji!";
    exit;
}

// Przywróć
$stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, category_id=? WHERE id=? AND user_id=?");
$stmt->execute([$ver['title'], $ver['content'], $ver['category_id'], $note_id, $user_id]);

header("Location: edit_note.php?note_id=$note_id&restored=1");
exit;