<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$note_id = $_GET['note_id'] ?? null;
if (!$note_id) {
    echo "Brak notatki!";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE id=? AND user_id=?");
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    echo "Brak notatki lub brak uprawnień!";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=?");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $category_id = $_POST['category_id'] ?? null;

    $stmt_ver = $pdo->prepare("SELECT MAX(version_number) FROM note_versions WHERE note_id=?");
    $stmt_ver->execute([$note_id]);
    $max_ver = $stmt_ver->fetchColumn() ?: 0;
    $stmt_ver = $pdo->prepare("INSERT INTO note_versions (note_id, version_number, title, content, category_id) VALUES (?, ?, ?, ?, ?)");
    $stmt_ver->execute([$note_id, $max_ver + 1, $note['title'], $note['content'], $note['category_id']]);


    $stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, category_id=? WHERE id=? AND user_id=?");
    $stmt->execute([$title, $content, $category_id, $note_id, $user_id]);
    header("Location: edit_note.php?note_id=$note_id&success=1");
    exit;
}
$show_share = isset($_GET['success']) && $_GET['success'] == 1;

$stmt = $pdo->prepare("SELECT room_key FROM shared_notes WHERE note_id=? AND owner_id=?");
$stmt->execute([$note_id, $user_id]);
$room_key = $stmt->fetchColumn();


$stmt_versions = $pdo->prepare("SELECT * FROM note_versions WHERE note_id=? ORDER BY version_number DESC");
$stmt_versions->execute([$note_id]);
$versions = $stmt_versions->fetchAll(PDO::FETCH_ASSOC);

$restored = false;
if (isset($_GET['restored'])) $restored = true;
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Edycja notatki</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .card-form { max-width: 900px; margin: 40px auto; }
        .form-label { font-weight: bold; }
        textarea.form-control { min-height: 320px; font-size: 1.13em; resize: vertical; }
        .history-box { background:#23272b; border-radius:7px; padding:10px 16px; margin-bottom:18px; }
        .history-item { font-size:0.98em; margin-bottom:6px; }
        .badge-ver { background:#e2b714; color:#232427; }
    </style>
</head>

<body class="bg-dark text-light">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card card-form bg-dark border-secondary mt-5">
            <div class="card-header"><i class="fa fa-edit"></i> Edycja notatki</div>
            <div class="card-body">
                <?php if ($show_share): ?>
                    <div class="alert alert-info">
                        <b><i class="fa fa-save"></i> Zapisano zmiany!</b>
                        <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                        <?php if ($room_key): ?>
                            <a href="share.php?note_id=<?= $note_id ?>&room_key=<?= $room_key ?>" class="btn btn-warning"><i class="fa fa-bolt"></i> Udostępnij</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($restored): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-history"></i> Przywrócono wersję notatki!
                    </div>
                <?php endif; ?>
                <div class="history-box mb-4">
                    <strong><i class="fa fa-history"></i> Historia wersji:</strong><br>
                    <?php if (count($versions) == 0): ?>
                        <span class="text-muted">Brak wcześniejszych wersji.</span>
                    <?php else: ?>
                        <?php foreach ($versions as $ver): ?>
                            <div class="history-item">
                                <span class="badge badge-ver">v<?= $ver['version_number'] ?></span>
                                <?= htmlspecialchars($ver['created_at']) ?>
                                <form method="post" action="restore_version.php" style="display:inline;">
                                    <input type="hidden" name="note_id" value="<?= $note_id ?>">
                                    <input type="hidden" name="version_id" value="<?= $ver['id'] ?>">
                                    <button type="submit" class="btn btn-outline-info btn-sm ms-2"><i class="fa fa-undo"></i> Przywróć</button>
                                </form>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#verModal<?= $ver['id'] ?>">Podgląd</button>
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
                                        <form method="post" action="restore_version.php" style="display:inline;">
                                            <input type="hidden" name="note_id" value="<?= $note_id ?>">
                                            <input type="hidden" name="version_id" value="<?= $ver['id'] ?>">
                                            <button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> Przywróć tę wersję</button>
                                        </form>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="post" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tytuł</label>
                            <input type="text" name="title" class="form-control"
                                value="<?= htmlspecialchars($note['title']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategoria</label>
                            <select name="category_id" class="form-select">
                                <option value="">Brak kategorii</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($note['category_id'] == $cat['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treść</label>
                        <textarea name="content" class="form-control" rows="12"
                            required><?= htmlspecialchars($note['content']) ?></textarea>
                        <div class="card mt-3 bg-secondary text-light">
                          <div class="card-body">
                            <h6><i class="fa fa-lightbulb"></i> Jak używać Markdown?</h6>
                            <ul style="font-size:0.98em;">
                              <li><b># Nagłówek</b> – <code># Tytuł</code></li>
                              <li><b>Pogrubienie</b> – <code>**pogrubiony tekst**</code></li>
                              <li><b>Kursywa</b> – <code>*kursywa*</code></li>
                              <li><b>Lista</b> – <code>- element 1</code><br><code>- element 2</code></li>
                              <li><b>Link</b> – <code>[opis](https://adres.pl)</code></li>
                              <li><b>Kod</b> – <code>`kod`</code></li>
                              <li><b>Cytat</b> – <code>> cytat</code></li>
                            </ul>
                            <a href="https://commonmark.org/help/" target="_blank" class="btn btn-info btn-sm mt-2"><i class="fa fa-book"></i> Pełna instrukcja Markdown</a>
                          </div>
                        </div>
                    </div>
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Zapisz zmiany</button>
                    <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>