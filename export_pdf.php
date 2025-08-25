<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
require_once 'db.php';

use Mpdf\Mpdf;
use Parsedown;

// Ścieżka do katalogu tymczasowego
$tempDir = __DIR__ . '/tmp';

// Sprawdź czy user zalogowany
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Brak dostępu!');
}
$user_id = $_SESSION['user_id'];

// Pobierz note_id z GET
$note_id = $_GET['note_id'] ?? null;
if (!$note_id) {
    die('Brak identyfikatora notatki!');
}

// Pobierz notatkę z bazy
$stmt = $pdo->prepare("SELECT notes.*, categories.name AS category FROM notes LEFT JOIN categories ON notes.category_id=categories.id WHERE notes.id=? AND notes.user_id=?");
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    die('Nie znaleziono notatki lub brak uprawnień!');
}

// Przetwórz Markdown na HTML
$Parsedown = new Parsedown();
$html = $Parsedown->text($note['content']);

// Przygotuj PDF
$mpdf = new Mpdf([
    'tempDir' => $tempDir,
]);

$html_final = '
<h2>' . htmlspecialchars($note['title']) . '</h2>
<p><b>Kategoria:</b> ' . htmlspecialchars($note['category'] ?: 'Brak kategorii') . '</p>
<hr>
' . $html;

// Generuj PDF
$mpdf->WriteHTML($html_final);
$filename = 'notatka_' . $note_id . '.pdf';
$mpdf->Output($filename, 'D'); // pobierz PDF

exit;
?>