<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];
require_once 'db.php';

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$notes_per_page = 9;
$offset = ($page - 1) * $notes_per_page;

$sort = $_GET['sort'] ?? 'updated_at';
$allowedSorts = ['updated_at', 'title', 'category', 'is_pinned'];
if (!in_array($sort, $allowedSorts))
    $sort = 'updated_at';

$order = $_GET['order'] ?? 'DESC';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$params = [$user_id];
$where = "notes.user_id=?";
if ($category !== '') {
    $where .= " AND categories.name=?";
    $params[] = $category;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notes LEFT JOIN categories ON notes.category_id=categories.id WHERE $where");
$stmt->execute($params);
$total_notes = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_notes / $notes_per_page));

$params_notes = [$user_id];
if ($category !== '')
    $params_notes[] = $category;

$order_by = "notes.is_pinned DESC, ";
$order_by .= $sort === 'category' ? 'categories.name' : 'notes.' . $sort;
$stmt = $pdo->prepare("SELECT notes.*, categories.name AS category,
  (SELECT room_key FROM shared_notes WHERE note_id=notes.id AND owner_id=?) AS room_key,
  (SELECT readonly_link FROM shared_notes WHERE note_id=notes.id AND owner_id=?) AS readonly_link
  FROM notes
  LEFT JOIN categories ON notes.category_id=categories.id
  WHERE $where
  ORDER BY $order_by $order
  LIMIT $notes_per_page OFFSET $offset");
$stmt->execute(array_merge([$user_id, $user_id], $params_notes));
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=?");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

function nextOrder($order)
{
    return $order === 'ASC' ? 'DESC' : 'ASC';
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Moje notatki</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .note-card {
            min-height: 320px;
            max-height: 320px;
            height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: box-shadow .2s, transform .2s;
            overflow: hidden;
            position: relative;
        }
        .note-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
            transform: translateY(-3px) scale(1.05);
            border-color: #00bfff;
        }
        .note-content-preview {
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            max-height: 7.5em;
            line-height: 1.5em;
            word-break: break-word;
            margin-bottom: 6px;
        }
        .chip {
            display: inline-block;
            padding: 0.25em 0.75em;
            margin: 2px;
            background: #444;
            color: #fff;
            border-radius: 16px;
            cursor: pointer;
            transition: background .2s;
        }
        .chip.active, .chip:hover { background: #007bff; }
        .fade-out { animation: fadeOut 0.7s forwards; }
        @keyframes fadeOut { to { opacity: 0; height: 0; margin: 0; padding: 0; } }
        .loader {
            border: 4px solid #555;
            border-top: 4px solid #00bfff;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            animation: spin 1s linear infinite;
            margin: 40px auto;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .card-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 1.15em; }
        .pagination { margin-top: 32px; }
        .pagination .btn { min-width: 36px; }
        .sort-btn { border: none; background: transparent; font-size: 1.2em; cursor: pointer; color: #00bfff; }
        .sort-btn:focus { outline: none; box-shadow: none; }
        .sort-btn.active { color: #e2b714; }
        /* Pinezka w tile */
        .pin-btn-tile {
            position: absolute;
            top: 12px;
            right: 16px;
            z-index: 2;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-size: 1.5em;
            opacity: 0.15;
            transition: opacity .15s;
        }
        .note-card:hover .pin-btn-tile,
        .pin-btn-tile.pinned {
            opacity: 1;
        }
        .pin-btn-tile .fa-thumbtack {
            color: #e2b714;
            text-shadow: 0 2px 6px #000a;
        }
        .pin-btn-tile:not(.pinned) .fa-thumbtack {
            color: #bbb;
        }
        .badge-pinned { background:#e2b714; color:#232427; margin-right:6px; }
    </style>
</head>

<body class="bg-dark text-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <h5>Kategorie</h5>
                <div>
                    <span class="chip<?= $category == '' ? ' active' : '' ?>" onclick="window.location='?category=&page=1'">Wszystkie</span>
                    <?php foreach ($categories as $cat): ?>
                        <span class="chip<?= $cat['name'] == $category ? ' active' : '' ?>"
                            onclick="window.location='?category=<?= urlencode($cat['name']) ?>&page=1'"><?= htmlspecialchars($cat['name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-secondary mt-3 w-100" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="fa fa-plus"></i> Nowa kategoria</button>
            </div>
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Notatki</h5>
                    <form method="get" class="d-flex align-items-center" id="sortForm">
                        <label class="me-2">Sortuj według:</label>
                        <select name="sort" id="sort-notes" class="form-select w-auto me-2"
                            onchange="document.getElementById('sortForm').submit()">
                            <option value="updated_at" <?= ($sort == 'updated_at' ? 'selected' : '') ?>>Data edycji</option>
                            <option value="title" <?= ($sort == 'title' ? 'selected' : '') ?>>Tytuł</option>
                            <option value="category" <?= ($sort == 'category' ? 'selected' : '') ?>>Kategoria</option>
                        </select>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                        <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                        <button type="submit" name="order" value="<?= nextOrder($order) ?>"
                            class="sort-btn <?= $order === 'ASC' ? 'active' : '' ?>"
                            title="<?= $order === 'ASC' ? 'Rosnąco' : 'Malejąco' ?>" style="margin-left:3px;">
                            <?php if ($order === 'ASC'): ?>
                                <i class="fa fa-sort-amount-up"></i>
                            <?php else: ?>
                                <i class="fa fa-sort-amount-down"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                    <input class="form-control w-50" placeholder="Szukaj notatki..." id="searchInput">
                </div>
                <div id="notes-list" class="row">
                    <?php if (count($notes) == 0): ?>
                        <div class="col-12 text-center mt-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076549.png"
                                style="width:64px;opacity:.5;">
                            <div class="mt-2">Brak notatek. Dodaj pierwszą!</div>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($notes as $note): ?>
                        <div class="col-md-6 col-lg-4 mb-3 note-card-outer">
                            <div class="card note-card bg-dark border-secondary h-100">
                                <button class="pin-btn-tile<?= $note['is_pinned'] ? ' pinned' : '' ?>"
                                    title="<?= $note['is_pinned'] ? 'Odepnij' : 'Przypnij' ?>"
                                    onclick="togglePin(this,<?= $note['id'] ?>,<?= $note['is_pinned'] ?>)">
                                    <i class="fa fa-thumbtack"></i>
                                </button>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="view_note.php?note_id=<?= $note['id'] ?>" class="text-info text-decoration-none">
                                            <?= htmlspecialchars($note['title']) ?>
                                        </a>
                                    </h6>
                                    <div class="note-content-preview"><?= htmlspecialchars($note['content']) ?></div>
                                    <span class="badge bg-secondary"><?= $note['category'] ?: 'Brak kategorii' ?></span>
                                    <?php if ($note['is_pinned']): ?>
                                        <span class="badge badge-pinned"><i class="fa fa-thumbtack"></i> Przypięta</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><?= $note['updated_at'] ?></small>
                                    <div>
                                        <a href="edit_note.php?note_id=<?= $note['id'] ?>" class="btn btn-sm btn-primary me-2"><i class="fa fa-edit"></i></a>
                                        <button class="btn btn-sm btn-danger me-2"
                                            onclick="deleteNote(this,<?= $note['id'] ?>)"><i class="fa fa-trash"></i></button>
                                        <?php if ($note['room_key']): ?>
                                            <a href="share.php?note_id=<?= $note['id'] ?>&room_key=<?= $note['room_key'] ?>"
                                                class="btn btn-sm btn-warning"><i class="fa fa-bolt"></i></a>
                                        <?php else: ?>
                                            <span class="btn btn-sm btn-warning disabled" title="Dostępne po zapisaniu"><i class="fa fa-bolt"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex justify-content-center pagination">
                    <?php if ($page > 1): ?>
                        <a href="?category=<?= urlencode($category) ?>&page=<?= $page - 1 ?>&sort=<?= htmlspecialchars($sort) ?>&order=<?= htmlspecialchars($order) ?>"
                            class="btn btn-sm btn-secondary me-1">&laquo;</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1)
                        echo '<span class="btn btn-sm btn-outline-secondary me-1" disabled>...</span>';
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?category=<?= urlencode($category) ?>&page=<?= $i ?>&sort=<?= htmlspecialchars($sort) ?>&order=<?= htmlspecialchars($order) ?>"
                            class="btn btn-sm <?= $i == $page ? 'btn-info' : 'btn-outline-secondary' ?> me-1"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $total_pages)
                        echo '<span class="btn btn-sm btn-outline-secondary" disabled>...</span>';
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?category=<?= urlencode($category) ?>&page=<?= $page + 1 ?>&sort=<?= htmlspecialchars($sort) ?>&order=<?= htmlspecialchars($order) ?>"
                            class="btn btn-sm btn-secondary">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="add-category-form">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-tag"></i> Dodaj kategorię</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="category-name" name="name" class="form-control mb-2"
                        placeholder="Nazwa kategorii" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="submit"><i class="fa fa-plus"></i> Dodaj</button>
                </div>
            </form>
        </div>
    </div>
    <div id="loader" style="display:none;">
        <div class="loader"></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('input', function () {
            let q = this.value.trim().toLowerCase();
            document.querySelectorAll('#notes-list .note-card-outer').forEach(card => {
                let title = card.querySelector('.card-title').textContent.toLowerCase();
                let content = card.querySelector('.note-content-preview').textContent.toLowerCase();
                card.style.display = (title.includes(q) || content.includes(q)) ? '' : 'none';
            });
        });
        function deleteNote(btn, id) {
            const card = btn.closest('.note-card-outer');
            card.classList.add('fade-out');
            setTimeout(() => {
                card.style.display = 'none';
                fetch('api.php?action=delete_note', {
                    method: 'POST', body: new URLSearchParams({ id })
                }).then(r => r.json()).then(() => { location.reload(); });
            }, 700);
        }
        document.getElementById('add-category-form').onsubmit = function (e) {
            e.preventDefault();
            document.getElementById('loader').style.display = 'block';
            fetch('api.php?action=add_category', { method: 'POST', body: new URLSearchParams({ name: e.target.name.value }) })
                .then(r => r.json()).then(() => { location.reload(); });
        };
        function togglePin(btn, id, pinned) {
            btn.disabled = true;
            fetch('api.php?action=toggle_pin', {
                method: 'POST',
                body: new URLSearchParams({ id: id, pinned: pinned ? 0 : 1 })
            }).then(r => r.json()).then(() => { location.reload(); });
        }
    </script>
</body>

</html>