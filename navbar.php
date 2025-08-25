<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand mb-0 h1">
            <i class="fa fa-sticky-note"></i> Notatki
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($_SESSION['user_id'])):
                require_once 'db.php';
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id=?");
                $stmt->execute([$_SESSION['user_id']]);
                $username = $stmt->fetchColumn();
                $firstLetter = strtoupper($username[0] ?? '?');
                ?>
                <a href="add_note.php" class="btn btn-success px-4 py-2 rounded-pill d-flex align-items-center"><i
                        class="fa fa-plus me-2"></i> Dodaj notatkę</a>
                <a href="add_shared_note.php" class="btn btn-warning px-4 py-2 rounded-pill d-flex align-items-center"><i
                        class="fa fa-bolt me-2"></i> Share</a>
                <a href="typeracer.php" class="btn btn-info px-4 py-2 rounded-pill d-flex align-items-center"><i
                        class="bi bi-controller me-2"></i> Typeracer</a>
                <a href="logout.php" class="btn btn-danger px-4 py-2 rounded-pill d-flex align-items-center"><i
                        class="fa fa-sign-out-alt me-2"></i> Wyloguj</a>
                <span class="avatar ms-3"
                    style="background:#222; color:#fff; border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center; font-size:1.2em;">
                    <?= htmlspecialchars($firstLetter) ?>
                </span>
            <?php else: ?>
                <a href="auth.php" class="btn btn-primary px-4 py-2 rounded-pill d-flex align-items-center"><i
                        class="fa fa-user me-2"></i> Logowanie / Rejestracja</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .avatar {
        height: 36px !important;
        width: 36px !important;
        font-size: 1.2em !important;
    }

    @media (max-width: 600px) {
        .navbar .btn {
            font-size: 1em;
            padding: 0.5em 0.8em;
            height: 40px;
        }

        .avatar {
            height: 28px !important;
            width: 28px !important;
            font-size: 1em !important;
        }
    }
</style>