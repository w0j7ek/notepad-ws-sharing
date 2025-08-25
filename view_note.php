<?php
session_start();
require_once 'db.php';
require __DIR__ . '/vendor/autoload.php';

use Stichoza\GoogleTranslate\GoogleTranslate;
use Parsedown;

if (!isset($_SESSION['user_id'])) { header('Location: auth.php'); exit; }
$user_id = $_SESSION['user_id'];

$note_id = $_GET['note_id'] ?? null;
if (!$note_id) { echo "Brak notatki!"; exit; }

// Przypinanie/odpinanie
if (isset($_POST['toggle_pin'])) {
    $stmt = $pdo->prepare("UPDATE notes SET is_pinned = NOT is_pinned WHERE id=? AND user_id=?");
    $stmt->execute([$note_id, $user_id]);
}

// Przywracanie wersji
if (isset($_POST['restore_version_id'])) {
    $version_id = intval($_POST['restore_version_id']);
    $stmt = $pdo->prepare("SELECT * FROM note_versions WHERE id=? AND note_id=?");
    $stmt->execute([$version_id, $note_id]);
    $ver = $stmt->fetch();
    if ($ver) {
        $stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, category_id=? WHERE id=? AND user_id=?");
        $stmt->execute([$ver['title'], $ver['content'], $ver['category_id'], $note_id, $user_id]);
        header("Location: view_note.php?note_id=$note_id&restored=1");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT notes.*, categories.name AS category FROM notes LEFT JOIN categories ON notes.category_id=categories.id WHERE notes.id=? AND notes.user_id=?");
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) { echo "Brak notatki lub brak uprawnień!"; exit; }

$is_pinned = $note['is_pinned'] ?? 0;

// Pobierz wersje
$stmt = $pdo->prepare("SELECT * FROM note_versions WHERE note_id=? ORDER BY version_number DESC");
$stmt->execute([$note_id]);
$versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- reszta Twojego kodu z tłumaczeniem i Markdown ---
$stmt = $pdo->prepare("SELECT id, readonly_link FROM shared_notes WHERE note_id=? AND owner_id=?");
$stmt->execute([$note_id, $user_id]);
$shared = $stmt->fetch();
$readonly_link = $shared ? $shared['readonly_link'] : null;

if (!$readonly_link && $shared) {
    $readonly_link = bin2hex(random_bytes(18));
    $stmt = $pdo->prepare("UPDATE shared_notes SET readonly_link=? WHERE id=?");
    $stmt->execute([$readonly_link, $shared['id']]);
}
$readonly_share_link = $readonly_link ? "http://localhost/view_shared_note.php?readonly=$readonly_link" : null;

$lang_map = [
    'Polish' => 'pl',
    'English' => 'en',
    'Spanish' => 'es',
    'German' => 'de',
    'French' => 'fr',
    'Italian' => 'it'
];
$detected_lang = null;
$detected_lang_name = 'Nieznany';
if (!empty($note['content'])) {
    try {
        $tr = new GoogleTranslate();
        $tr->setSource();
        $tr->setTarget('en');
        $tr->translate($note['content']);
        $detected_lang = $tr->getLastDetectedSource();
        $detected_lang_name = array_search($detected_lang, $lang_map);
        if (!$detected_lang_name) $detected_lang_name = strtoupper($detected_lang);
    } catch (Exception $e) {
        $detected_lang_name = 'Nieznany';
    }
}
$translation = null;
if (
    isset($_GET['translate']) &&
    isset($_GET['target_lang']) &&
    !empty($note['content'])
) {
    $src = null;
    if (isset($_GET['manual_source']) && isset($_GET['source_lang']) && $_GET['source_lang'] !== '') {
        $src = $lang_map[$_GET['source_lang']] ?? $_GET['source_lang'];
    }
    $tgt = $lang_map[$_GET['target_lang']] ?? $_GET['target_lang'];
    try {
        $tr = new GoogleTranslate($tgt);
        $tr->setSource($src);
        $translation = $tr->translate($note['content']);
    } catch (Exception $e) {
        $translation = 'Błąd tłumaczenia: ' . $e->getMessage();
    }
}
$Parsedown = new Parsedown();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Podgląd notatki</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .card-view { max-width:700px; margin:30px auto; }
        .note-title { font-size:1.45em; font-weight:bold; }
        .note-category { font-size:1em; margin-left:12px; vertical-align:middle;}
        .note-content { font-size:1.1em; margin-top:14px; margin-bottom:14px; }
        .card-footer { font-size:0.92em; color:#bbb; }
        .share-row { margin-bottom: 14px; }
        .copy-link-btn { cursor: pointer; }
        .spinner { width: 18px; height: 18px; border: 3px solid #eee; border-top: 3px solid #00bfff; border-radius: 50%; animation: spin 1s linear infinite; display:inline-block; }
        @keyframes spin { 100% { transform: rotate(360deg);} }
        .show-on-manual { display: none; }
        .pin-btn { font-size:1.1em; }
        .card-header, .card-footer { padding: 0.5rem 1rem; }
        .card-body { padding: 1rem 1rem 0.7rem 1rem; }
        .share-row input { font-size: 0.97em; }
        .card-translate, .card-link, .card-versions { max-width:700px; margin: 0 auto 20px auto; }
        .card-link .form-control { max-width:350px; }
        @media (max-width: 600px) {
            .card-view, .card-translate, .card-link, .card-versions { max-width:100%; margin:10px; }
        }
    </style>
</head>
<body class="bg-dark text-light">
<?php include 'navbar.php'; ?>
<div class="container">
    <!-- LINK UDOSTĘPNIANIA W OSOBNEJ KARCIE -->
    <?php if ($readonly_share_link): ?>
    <div class="card card-link bg-secondary text-light mt-5">
        <div class="card-header"><i class="fa fa-link"></i> Publiczny link (tylko odczyt)</div>
        <div class="card-body d-flex align-items-center">
            <input type="text" class="form-control me-2" readonly value="<?= htmlspecialchars($readonly_share_link) ?>">
            <span class="btn btn-info copy-link-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($readonly_share_link) ?>')"><i class="fa fa-copy"></i></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- NOTATKA -->
    <div class="card card-view bg-dark border-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fa fa-eye"></i> Podgląd notatki</span>
            <form method="post" style="display:inline;">
                <button type="submit" name="toggle_pin" class="btn btn-outline-warning pin-btn" title="<?= $is_pinned ? 'Odepnij' : 'Przypnij' ?>">
                    <i class="fa fa-thumbtack" style="<?= $is_pinned ? 'color:#e2b714;' : '' ?>"></i>
                    <?= $is_pinned ? 'Przypięta' : 'Przypnij' ?>
                </button>
            </form>
        </div>
        <div class="card-body">
            <!-- Tytuł i kategoria w jednym wierszu -->
            <div class="d-flex align-items-center mb-2">
                <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
                <span class="badge bg-secondary note-category"><?= $note['category'] ?: 'Brak kategorii' ?></span>
            </div>
            <div class="note-content"><?= $Parsedown->text($note['content']) ?></div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span><i class="fa fa-clock"></i> Ostatnia edycja: <?= $note['updated_at'] ?></span>
            <div>
                <a href="export_pdf.php?note_id=<?= $note_id ?>" class="btn btn-outline-info ms-2"><i class="fa fa-file-pdf"></i> PDF</a>
                <a href="index.php" class="btn btn-link text-light ms-2"><i class="fa fa-arrow-left"></i> Powrót</a>
            </div>
        </div>
    </div>

    <!-- WERSJE W OSOBNEJ KARCIE -->
    <div class="card card-versions bg-secondary text-light mt-3">
        <div class="card-header"><i class="fa fa-history"></i> Historia wersji</div>
        <div class="card-body">
            <?php if (count($versions) === 0): ?>
                <div class="text-muted">Brak wcześniejszych wersji.</div>
            <?php else: ?>
                <ul class="version-list">
                    <?php foreach ($versions as $ver): ?>
                        <li>
                            <form method="post" style="display:inline;">
                                <span class="badge bg-info me-2">Wersja <?= $ver['version_number'] ?></span>
                                <span><?= htmlspecialchars($ver['created_at']) ?></span>
                                <button type="submit" name="restore_version_id" value="<?= $ver['id'] ?>"
                                    class="btn btn-sm btn-outline-warning ms-2"
                                    onclick="return confirm('Czy na pewno przywrócić tę wersję?');">
                                    <i class="fa fa-undo"></i> Przywróć
                                </button>
                                <!-- MODAL BUTTON -->
                                <button type="button" class="btn btn-sm btn-outline-info ms-1" data-bs-toggle="modal" data-bs-target="#verModal<?= $ver['id'] ?>">
                                    <i class="fa fa-eye"></i> Podgląd
                                </button>
                            </form>
                            <!-- MODAL -->
                            <div class="modal fade" id="verModal<?= $ver['id'] ?>" tabindex="-1">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content bg-dark text-light">
                                  <div class="modal-header">
                                    <h5 class="modal-title">Podgląd wersji v<?= $ver['version_number'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                  </div>
                                  <div class="modal-body">
                                    <b>Tytuł:</b> <?= htmlspecialchars($ver['title']) ?><br>
                                    <b>Kategoria:</b> <?= htmlspecialchars($ver['category_id']) ?><br>
                                    <b>Treść:</b>
                                    <pre><?= htmlspecialchars($ver['content']) ?></pre>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="restore_version_id" value="<?= $ver['id'] ?>">
                                        <button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> Przywróć tę wersję</button>
                                    </form>
                                  </div>
                                </div>
                              </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (isset($_GET['restored']) && $_GET['restored'] == 1): ?>
                <div class="alert alert-success mt-2"><i class="fa fa-check"></i> Wersja została przywrócona!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TŁUMACZENIE W OSOBNEJ KARCIE -->
    <div class="card card-translate bg-secondary text-light mt-3 mb-3">
        <div class="card-header"><i class="fa fa-language"></i> Tłumaczenie notatki</div>
        <div class="card-body">
            <form method="get" action="" class="d-inline" id="translateForm" autocomplete="off">
                <input type="hidden" name="note_id" value="<?= htmlspecialchars($note_id) ?>">
                <input type="hidden" name="translate" value="1">
                <span id="detectedLangBox">
                    <label class="form-label">Tłumacz z:</label>
                    <b>[Wykryty: <?= htmlspecialchars($detected_lang_name) ?>]</b>
                    <button type="button" class="btn btn-link text-info p-0 ms-1" id="wrongLangBtn" style="font-size:0.95em;">Zły język?</button>
                </span>
                <span class="show-on-manual" id="manualLangSelect">
                    <select name="source_lang" class="form-select d-inline-block" style="max-width:140px;">
                        <option value="">Auto</option>
                        <option value="Polish"<?= (($_GET['source_lang'] ?? '')=='Polish'?' selected':'') ?>>Polski</option>
                        <option value="English"<?= (($_GET['source_lang'] ?? '')=='English'?' selected':'') ?>>Angielski</option>
                        <option value="Spanish"<?= (($_GET['source_lang'] ?? '')=='Spanish'?' selected':'') ?>>Hiszpański</option>
                        <option value="German"<?= (($_GET['source_lang'] ?? '')=='German'?' selected':'') ?>>Niemiecki</option>
                        <option value="French"<?= (($_GET['source_lang'] ?? '')=='French'?' selected':'') ?>>Francuski</option>
                        <option value="Italian"<?= (($_GET['source_lang'] ?? '')=='Italian'?' selected':'') ?>>Włoski</option>
                    </select>
                    <input type="hidden" name="manual_source" value="1">
                    <button type="button" class="btn btn-link text-danger p-0 ms-1" id="cancelManualBtn" style="font-size:0.95em;">Anuluj</button>
                </span>
                <label class="form-label ms-2">na:</label>
                <select name="target_lang" class="form-select" style="max-width:140px;display:inline-block;">
                    <option value="English"<?= (($_GET['target_lang'] ?? '')=='English'?' selected':'') ?>>Angielski</option>
                    <option value="Polish"<?= (($_GET['target_lang'] ?? '')=='Polish'?' selected':'') ?>>Polski</option>
                    <option value="Spanish"<?= (($_GET['target_lang'] ?? '')=='Spanish'?' selected':'') ?>>Hiszpański</option>
                    <option value="German"<?= (($_GET['target_lang'] ?? '')=='German'?' selected':'') ?>>Niemiecki</option>
                    <option value="French"<?= (($_GET['target_lang'] ?? '')=='French'?' selected':'') ?>>Francuski</option>
                    <option value="Italian"<?= (($_GET['target_lang'] ?? '')=='Italian'?' selected':'') ?>>Włoski</option>
                </select>
                <button type="submit" class="btn btn-warning ms-2" id="translateBtn">
                    <i class="fa fa-globe"></i> Przetłumacz
                    <span id="spinner" style="display:none; margin-left:6px;">
                        <span class="spinner"></span>
                    </span>
                </button>
            </form>
            <div class="mt-2 text-muted" style="font-size:0.98em;"><i class="fa fa-language"></i> Wykryty język: <b><?= htmlspecialchars($detected_lang_name) ?></b> (<?= htmlspecialchars($detected_lang) ?>)</div>
            <?php if ($translation !== null): ?>
            <div id="translation" class="mt-3">
                <div class="card bg-info text-dark">
                    <div class="card-body" id="translation-text"><?= nl2br(htmlspecialchars($translation)) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var wrongLangBtn = document.getElementById('wrongLangBtn');
    var detectedLangBox = document.getElementById('detectedLangBox');
    var manualLangSelect = document.getElementById('manualLangSelect');
    var cancelManualBtn = document.getElementById('cancelManualBtn');
    var translateForm = document.getElementById('translateForm');
    var translateBtn = document.getElementById('translateBtn');
    var spinner = document.getElementById('spinner');

    if (wrongLangBtn && detectedLangBox && manualLangSelect) {
        wrongLangBtn.onclick = function() {
            detectedLangBox.style.display = 'none';
            manualLangSelect.style.display = 'inline';
        };
    }

    if (cancelManualBtn && manualLangSelect && detectedLangBox) {
        cancelManualBtn.onclick = function() {
            manualLangSelect.style.display = 'none';
            detectedLangBox.style.display = '';
        };
    }

    if (translateForm && translateBtn && spinner) {
        translateForm.onsubmit = function() {
            translateBtn.disabled = true;
            spinner.style.display = '';
        };
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>