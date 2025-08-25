<?php
require __DIR__ . '/vendor/autoload.php';

use Stichoza\GoogleTranslate\GoogleTranslate;

header('Content-Type: application/json');

$text = $_POST['text'] ?? '';
$source_lang = $_POST['source_lang'] ?? '';
$target_lang = $_POST['target_lang'] ?? '';

if (!$text || !$target_lang) {
    echo json_encode(['error' => 'Brak tekstu lub docelowego języka.']);
    exit;
}

$lang_map = [
    'Polish' => 'pl',
    'English' => 'en',
    'Spanish' => 'es',
    'German' => 'de',
    'French' => 'fr',
    'Italian' => 'it'
];

$src = $lang_map[$source_lang] ?? null;
$tgt = $lang_map[$target_lang] ?? null;

try {
    $tr = new GoogleTranslate($tgt);
    if ($src) {
        $tr->setSource($src);
    }
    $translation = $tr->translate($text);
    echo json_encode(['translation' => $translation]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Błąd tłumaczenia', 'details' => $e->getMessage()]);
}