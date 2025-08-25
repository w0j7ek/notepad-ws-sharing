<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
$user_id = $_SESSION['user_id'];

switch ($_GET['action'] ?? '') {
    case 'get_notes':
        $cat = $_GET['category'] ?? '';
        $sql = "SELECT notes.*, categories.name AS category FROM notes LEFT JOIN categories ON notes.category_id=categories.id WHERE notes.user_id=?";
        if ($cat) {
            $sql .= " AND categories.name=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $cat]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_note':
        $id = $_GET['note_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT notes.*, categories.name AS category FROM notes LEFT JOIN categories ON notes.category_id=categories.id WHERE notes.id=? AND notes.user_id=?");
        $stmt->execute([$id, $user_id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    case 'add_note':
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $cat_id = $_POST['category_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, category_id, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $cat_id, $title, $content]);
        echo json_encode(['ok' => true]);
        break;

    case 'update_note':
        $id = $_POST['id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $cat_id = $_POST['category_id'] ?? null;

        $stmt_old = $pdo->prepare("SELECT * FROM notes WHERE id=? AND user_id=?");
        $stmt_old->execute([$id, $user_id]);
        $old_note = $stmt_old->fetch();
        if ($old_note) {
            $stmt_ver = $pdo->prepare("SELECT MAX(version_number) FROM note_versions WHERE note_id=?");
            $stmt_ver->execute([$id]);
            $max_ver = $stmt_ver->fetchColumn() ?: 0;
            $stmt_ver = $pdo->prepare("INSERT INTO note_versions (note_id, version_number, title, content, category_id) VALUES (?, ?, ?, ?, ?)");
            $stmt_ver->execute([$id, $max_ver + 1, $old_note['title'], $old_note['content'], $old_note['category_id']]);
        }

        $stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, category_id=? WHERE id=? AND user_id=?");
        $stmt->execute([$title, $content, $cat_id, $id, $user_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'update_note_live':
        $id = $_POST['note_id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $category_id = $_POST['category_id'] ?? null;
        $room_key = $_POST['room_key'] ?? '';
        $stmt = $pdo->prepare("SELECT owner_id, can_edit FROM shared_notes WHERE note_id=? AND room_key=?");
        $stmt->execute([$id, $room_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $row['owner_id'];
        if ($is_owner || ($row && $row['can_edit'])) {
            $stmt_old = $pdo->prepare("SELECT * FROM notes WHERE id=?");
            $stmt_old->execute([$id]);
            $old_note = $stmt_old->fetch();
            if ($old_note) {
                $stmt_ver = $pdo->prepare("SELECT MAX(version_number) FROM note_versions WHERE note_id=?");
                $stmt_ver->execute([$id]);
                $max_ver = $stmt_ver->fetchColumn() ?: 0;
                $stmt_ver = $pdo->prepare("INSERT INTO note_versions (note_id, version_number, title, content, category_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_ver->execute([$id, $max_ver + 1, $old_note['title'], $old_note['content'], $old_note['category_id']]);
            }
            $stmt = $pdo->prepare("UPDATE notes SET title=?, content=?, category_id=? WHERE id=?");
            $stmt->execute([$title, $content, $category_id, $id]);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Brak uprawnień']);
        }
        break;

    case 'toggle_pin':
        $id = $_POST['id'] ?? 0;
        $pinned = $_POST['pinned'] ?? 0;
        $stmt = $pdo->prepare("UPDATE notes SET is_pinned=? WHERE id=? AND user_id=?");
        $stmt->execute([$pinned, $id, $user_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete_note':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id=? AND user_id=?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'get_categories':
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=?");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'add_category':
        $name = $_POST['name'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
        $stmt->execute([$user_id, $name]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Bad request']);
}