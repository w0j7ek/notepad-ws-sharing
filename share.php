<?php
session_start();
require_once 'db.php';

$note_id = $_GET['note_id'] ?? null;
$room_key = $_GET['room_key'] ?? null;
if (!$note_id || !$room_key) {
    echo "Brak notatki!";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE id=?");
$stmt->execute([$note_id]);
$note = $stmt->fetch();
if (!$note) {
    echo "Brak notatki!";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM shared_notes WHERE note_id=? AND room_key=?");
$stmt->execute([$note_id, $room_key]);
$shared = $stmt->fetch();
if (!$shared) {
    echo "Brak pokoju udostępniania!";
    exit;
}

$owner_id = $shared['owner_id'];
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $owner_id;
$can_edit = $shared['can_edit'] ? true : false;

$share_link = "http://localhost/share.php?note_id={$note_id}&room_key={$room_key}";
$readonly_link = $shared['readonly_link'];
$readonly_share_link = "http://localhost/view_shared_note.php?readonly={$readonly_link}";

$categories = [];
if ($is_owner) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=?");
    $stmt->execute([$owner_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function random_guest_name()
{
    $adjectives = [
        'Swift', 'Quiet', 'Wise', 'Bright', 'Happy', 'Cheerful', 'Fast', 'Clever', 'Kind', 'Brave', 'Funny', 'Lucky', 'Gentle', 'Smart', 'Curious'
    ];
    $animals = [
        'Squirrel', 'Fox', 'Wolf', 'Deer', 'Hare', 'Lynx', 'Doe', 'Boar', 'Eagle', 'Falcon', 'Beaver', 'Otter', 'Rabbit', 'Owl', 'Badger'
    ];
    $adj = $adjectives[array_rand($adjectives)];
    $animal = $animals[array_rand($animals)];
    return "Guest $adj $animal";
}
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_name'])) {
        $user_name = $_SESSION['user_name'];
    } else {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        $user_name = $row ? $row['username'] : "Użytkownik";
        $_SESSION['user_name'] = $user_name;
    }
    $user_id = $_SESSION['user_id'];
    $user_type = "user";
} else {
    if (!isset($_SESSION['guest_name']))
        $_SESSION['guest_name'] = random_guest_name();
    $user_name = $_SESSION['guest_name'];
    $user_id = null;
    $user_type = "guest";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_edit_perm'])) {
    $perm = isset($_POST['edit_perm']) ? intval($_POST['edit_perm']) : 0;
    if ($is_owner) {
        $stmt = $pdo->prepare("UPDATE shared_notes SET can_edit=? WHERE note_id=? AND room_key=?");
        $stmt->execute([$perm, $note_id, $room_key]);
        $can_edit = $perm ? true : false;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Live notatka</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="favicon.ico">
    <style>
        .card-live {
            max-width: 900px;
            margin: 30px auto;
        }
        .badge-owner { background: #007bff; }
        .badge-edit { background: #ff9800; }
        .fade-alert { transition: opacity .7s; }
        .loader {
            border: 4px solid #555;
            border-top: 4px solid #00bfff;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .copy-link-btn { cursor: pointer; }
        textarea.form-control { min-height: 320px; font-size: 1.13em; resize: vertical; }
        .who-viewing { background: #222; border-radius: 8px; padding: 10px 14px; margin-bottom: 18px; }
        .who-user { display: inline-block; margin-right: 7px; font-size: 1em; }
        .who-user .fa-user { margin-right: 3px; }
        .who-user .fa-user-secret { margin-right: 3px; }
        .who-user.you { font-weight: bold; color: #00bfff; }
    </style>
</head>

<body class="bg-dark text-light">
    <?php include 'navbar.php'; ?>
    <div class="container">
        <div class="card card-live bg-dark border-secondary mt-2">
            <div class="card-header"><i class="fa fa-bolt"></i> Udostępniona notatka</div>
            <div class="card-body">
                <div class="who-viewing mb-3" id="who-viewing">
                    <b><i class="fa fa-eye"></i> Kto przegląda tę notatkę:</b>
                    <span id="who-users-list"></span>
                </div>
                <div id="edit-alert"></div>
                <div id="save-alert"></div>
                <?php if ($is_owner): ?>
                    <form class="mb-3" id="edit-perm-form">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="edit_perm" value="1" id="edit_perm"
                                <?= $can_edit ? 'checked' : '' ?>>
                            <label class="form-check-label" for="edit_perm">
                                Pozwól innym na edycję tej notatki
                            </label>
                            <span class="badge badge-owner ms-2">Właściciel</span>
                        </div>
                        <button class="btn btn-secondary mt-2" type="button" id="perm-btn"><i class="fa fa-user-shield"></i>
                            Ustaw uprawnienia</button>
                    </form>
                    <div class="mb-2 d-flex align-items-center">
                        <strong class="me-2">Link do udostępnienia:</strong>
                        <input type="text" class="form-control me-2" readonly value="<?= htmlspecialchars($share_link) ?>"
                            id="share-link-input" style="max-width:350px;">
                        <span class="btn btn-info copy-link-btn" onclick="copyShareLink()"><i class="fa fa-copy"></i></span>
                    </div>
                    <div class="mb-2 d-flex align-items-center">
                        <strong class="me-2">Publiczny link (tylko odczyt):</strong>
                        <input type="text" class="form-control me-2" readonly value="<?= htmlspecialchars($readonly_share_link) ?>"
                            id="readonly-link-input" style="max-width:350px;">
                        <span class="btn btn-outline-info copy-link-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($readonly_share_link) ?>')"><i class="fa fa-copy"></i></span>
                    </div>
                <?php endif; ?>
                <form id="live-note-form">
                    <div class="mb-3">
                        <label class="form-label">Tytuł</label>
                        <input type="text" id="note-title" class="form-control"
                            value="<?= htmlspecialchars($note['title']) ?>" <?= (!$can_edit && !$is_owner) ? 'readonly' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Treść</label>
                        <textarea id="note-content" class="form-control" rows="12" <?= (!$can_edit && !$is_owner) ? 'readonly' : '' ?>><?= htmlspecialchars($note['content']) ?></textarea>
                        <div class="form-text text-muted mt-1">Wpisujesz <b>zwykły tekst</b> – bez Markdown, tekst zostanie wyświetlony tak jak go wpiszesz.</div>
                    </div>
                    <?php if ($is_owner): ?>
                        <div class="mb-3">
                            <label class="form-label">Kategoria</label>
                            <select id="note-category" class="form-select">
                                <option value="">Brak kategorii</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($note['category_id'] == $cat['id'] ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" id="note-id" value="<?= htmlspecialchars($note_id) ?>">
                    <input type="hidden" id="room-key" value="<?= htmlspecialchars($room_key) ?>">
                    <?php if ($is_owner): ?>
                        <button type="button" id="save-btn" class="btn btn-success"><i class="fa fa-save"></i> Zapisz
                            notatkę do bazy</button>
                    <?php endif; ?>
                </form>
                <div id="loader" style="display:none;">
                    <div class="loader"></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const roomKey = <?= json_encode($room_key) ?>;
        const noteId = <?= json_encode($note_id) ?>;
        let canEdit = <?= json_encode($can_edit) ?>;
        const isOwner = <?= json_encode($is_owner) ?>;
        const userName = <?= json_encode($user_name) ?>;
        const userId = <?= json_encode($user_id) ?>;
        const userType = <?= json_encode($user_type) ?>;

        const ws = new WebSocket("ws://localhost:8080?room_key=" + roomKey);

        function showEditAlert() {
            const alertBox = document.getElementById('edit-alert');
            if (!isOwner) {
                if (canEdit) {
                    alertBox.innerHTML = '<div class="alert alert-success fade-alert" role="alert">Edycja jest <b>włączona</b>.</div>';
                } else {
                    alertBox.innerHTML = '<div class="alert alert-warning fade-alert" role="alert">Edycja jest <b>wyłączona przez właściciela</b>.</div>';
                }
            }
        }
        showEditAlert();

        function showSaveAlert(success) {
            const alertBox = document.getElementById('save-alert');
            if (success) {
                alertBox.innerHTML = '<div class="alert alert-success fade-alert" role="alert">Notatka została zapisana!</div>';
            } else {
                alertBox.innerHTML = '<div class="alert alert-danger fade-alert" role="alert">Błąd podczas zapisywania notatki!</div>';
            }
            setTimeout(() => { alertBox.innerHTML = ""; }, 1500);
        }

        let blockUpdate = false;

        ws.onopen = function () {
            ws.send(JSON.stringify({
                type: "join",
                room_key: roomKey,
                user_id: userId,
                user_name: userName,
                user_type: userType
            }));
        };

        ws.onmessage = function (e) {
            const data = JSON.parse(e.data);
            if (data.type === 'note_update' && data.note_id == noteId) {
                blockUpdate = true;
                document.getElementById('note-title').value = data.title;
                document.getElementById('note-content').value = data.content;
                if (isOwner && data.category_id !== undefined && document.getElementById('note-category')) {
                    document.getElementById('note-category').value = data.category_id ? data.category_id : "";
                }
                blockUpdate = false;
            }
            if (data.type === 'edit_permission_changed') {
                canEdit = data.can_edit ? true : false;
                document.getElementById('note-title').readOnly = !canEdit && !isOwner;
                document.getElementById('note-content').readOnly = !canEdit && !isOwner;
                if (isOwner && document.getElementById('note-category'))
                    document.getElementById('note-category').disabled = !canEdit && !isOwner;
                showEditAlert();
            }
            if (data.type === 'users_list') {
                showUsersList(data.users);
            }
        };

        function sendUpdate() {
            if (blockUpdate) return;
            if (!canEdit && !isOwner) return;
            let payload = {
                type: "note_update",
                room_key: roomKey,
                note_id: noteId,
                title: document.getElementById('note-title').value,
                content: document.getElementById('note-content').value
            };
            if (isOwner && document.getElementById('note-category')) {
                payload.category_id = document.getElementById('note-category').value;
            }
            ws.send(JSON.stringify(payload));
        }

        function showUsersList(users) {
            const el = document.getElementById('who-users-list');
            el.innerHTML = '';
            users.forEach(u => {
                let icon = u.user_type === 'user' ? '<i class="fa fa-user"></i>' : '<i class="fa fa-user-secret"></i>';
                let cls = 'who-user';
                if ((u.user_id && userId && u.user_id == userId) || (u.user_type === 'guest' && u.user_name === userName)) cls += ' you';
                el.innerHTML += `<span class="${cls}">${icon}${u.user_name}</span>`;
            });
        }

        if (canEdit || isOwner) {
            document.getElementById('note-title').addEventListener('input', sendUpdate);
            document.getElementById('note-content').addEventListener('input', sendUpdate);
            if (isOwner && document.getElementById('note-category')) {
                document.getElementById('note-category').addEventListener('change', sendUpdate);
            }
        }
        if (isOwner) {
            document.getElementById('save-btn').onclick = function () {
                document.getElementById('loader').style.display = 'block';
                fetch('api.php?action=update_note_live', {
                    method: 'POST',
                    body: new URLSearchParams({
                        note_id: noteId,
                        room_key: roomKey,
                        title: document.getElementById('note-title').value,
                        content: document.getElementById('note-content').value,
                        category_id: document.getElementById('note-category') ? document.getElementById('note-category').value : ""
                    })
                }).then(r => r.json())
                    .then(resp => {
                        document.getElementById('loader').style.display = 'none';
                        if (resp && resp.ok) {
                            showSaveAlert(true);
                        } else {
                            showSaveAlert(false);
                        }
                    }).catch(() => {
                        document.getElementById('loader').style.display = 'none';
                        showSaveAlert(false);
                    });
            };
            document.getElementById('perm-btn').onclick = function () {
                const perm = document.getElementById('edit_perm').checked ? 1 : 0;
                fetch('share.php?note_id=' + noteId + '&room_key=' + roomKey, {
                    method: 'POST',
                    body: new URLSearchParams({
                        set_edit_perm: 1,
                        edit_perm: perm
                    })
                }).then(() => {
                    ws.send(JSON.stringify({
                        type: "edit_permission_changed",
                        room_key: roomKey,
                        can_edit: perm
                    }));
                    canEdit = perm ? true : false;
                    document.getElementById('note-title').readOnly = !canEdit && !isOwner;
                    document.getElementById('note-content').readOnly = !canEdit && !isOwner;
                    if (isOwner && document.getElementById('note-category'))
                        document.getElementById('note-category').disabled = !canEdit && !isOwner;
                    showEditAlert();
                });
            }
        }
        function copyShareLink() {
            const input = document.getElementById('share-link-input');
            input.select();
            document.execCommand('copy');
            const btn = document.querySelector('.copy-link-btn');
            btn.classList.add('btn-success');
            setTimeout(() => btn.classList.remove('btn-success'), 700);
        }
    </script>
</body>

</html>