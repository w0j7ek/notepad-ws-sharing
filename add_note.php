<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$errors = [];
$title = '';
$content = '';
$category_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = $_POST['category_id'] ?? '';

    if ($title === '') {
        $errors[] = "Tytuł jest wymagany.";
    }
    if ($content === '') {
        $errors[] = "Treść jest wymagana.";
    }

    $category_id_db = ($category_id === '' ? null : $category_id);

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, category_id, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $category_id_db, $title, $content]);
        $note_id = $pdo->lastInsertId();
        $room_key = bin2hex(random_bytes(12));
        $readonly_link = bin2hex(random_bytes(18));
        $stmt = $pdo->prepare("INSERT INTO shared_notes (note_id, room_key, owner_id, can_edit, readonly_link) VALUES (?, ?, ?, 1, ?)");
        $stmt->execute([$note_id, $room_key, $user_id, $readonly_link]);
        header("Location: add_note.php?success=1&note_id=$note_id&room_key=$room_key&readonly_link=$readonly_link");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=?");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$show_share = isset($_GET['success']) && $_GET['success'] == 1;
$note_id = $_GET['note_id'] ?? null;
$room_key = $_GET['room_key'] ?? null;
$readonly_link = $_GET['readonly_link'] ?? null;
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Dodaj notatkę</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .card-form { max-width: 900px; margin: 40px auto; }
        .form-control:invalid { border-color: #ff7043; }
        .form-label { font-weight: bold; }
        textarea.form-control { min-height: 320px; font-size: 1.13em; resize: vertical; }
    </style>
</head>

<body class="bg-dark text-light">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card card-form bg-dark border-secondary mt-5">
            <div class="card-header"><i class="fa fa-plus"></i> Dodaj nową notatkę</div>
            <div class="card-body">
                <?php if ($show_share): ?>
                    <div class="alert alert-success">
                        <b><i class="fa fa-check-circle"></i> Notatka dodana!</b>
                        <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                        <a href="share.php?note_id=<?= $note_id ?>&room_key=<?= $room_key ?>" class="btn btn-warning"><i
                                class="fa fa-bolt"></i> Udostępnij</a>
                        <a href="view_shared_note.php?readonly=<?= $readonly_link ?>" class="btn btn-info"><i class="fa fa-eye"></i> Udostępnij jako tylko do odczytu</a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tytuł</label>
                            <input type="text" name="title" class="form-control" placeholder="Tytuł" required
                                value="<?= htmlspecialchars($title) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategoria</label>
                            <select name="category_id" class="form-select">
                                <option value="">Brak kategorii</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $category_id ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treść</label>
                        <textarea name="content" class="form-control" rows="12" placeholder="Treść"
                            required><?= htmlspecialchars($content) ?></textarea>
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
                    <button class="btn btn-success"><i class="fa fa-save"></i> Zapisz notatkę</button>
                    <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                </form>
            </div>
        </div>
    </div>
</body>

</html>