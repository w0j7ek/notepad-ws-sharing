<?php
require_once 'db.php';
require __DIR__ . '/vendor/autoload.php';

use Parsedown;

$readonly_link = $_GET['readonly'] ?? null;
if (!$readonly_link) {
    echo "Brak linku!";
    exit;
}

$stmt = $pdo->prepare(
    "SELECT notes.*, categories.name AS category 
     FROM shared_notes 
     JOIN notes ON shared_notes.note_id=notes.id 
     LEFT JOIN categories ON notes.category_id=categories.id 
     WHERE shared_notes.readonly_link=?"
);
$stmt->execute([$readonly_link]);
$note = $stmt->fetch();

if (!$note) {
    echo "Brak notatki!";
    exit;
}

$Parsedown = new Parsedown();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Udostępniona notatka</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .card-view { max-width:900px; margin:40px auto; }
        .note-title { font-size:1.5em; font-weight:bold; }
        .note-category { font-size:1.1em; }
        .note-content { font-size:1.15em; margin-top:18px; }
        .card-footer { font-size:0.95em; color:#bbb; }
        /* Markdown base styles */
        .note-content h1, .note-content h2, .note-content h3, .note-content h4 { color: #e2b714; }
        .note-content ul, .note-content ol { margin-left: 1.2em; }
        .note-content code, .note-content pre { background: #181a1b; color: #e2b714; border-radius: 5px; padding: 3px 6px; }
        .note-content blockquote { border-left: 3px solid #00bfff; padding-left: 12px; color: #bbb; }
        .note-content a { color: #00bfff; }
    </style>
</head>
<body class="bg-dark text-light">
<div class="container">
    <div class="card card-view bg-dark border-secondary mt-5">
        <div class="card-header">
            <i class="fa fa-eye"></i> Publiczna notatka
        </div>
        <div class="card-body">
            <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
            <span class="badge bg-secondary note-category"><?= $note['category'] ?: 'Brak kategorii' ?></span>
            <div class="note-content"><?= $Parsedown->text($note['content']) ?></div>
        </div>
        <div class="card-footer">
            <span><i class="fa fa-clock"></i> Ostatnia edycja: <?= $note['updated_at'] ?></span>
            <a href="export_pdf.php?note_id=<?= $note['id'] ?>" class="btn btn-outline-info ms-2"><i class="fa fa-file-pdf"></i> Eksportuj do PDF</a>
        </div>
    </div>
</div>
</body>
</html>