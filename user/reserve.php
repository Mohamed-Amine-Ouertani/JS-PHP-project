<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db    = getDB();
$id    = intval($_GET['id'] ?? 0);
$stmt  = $db->prepare("SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
$stmt->execute([$id]);
$book  = $stmt->fetch();

if (!$book) {
    flash('danger', 'Book not found.');
    header('Location: dashboard.php');
    exit;
}

// Check if user already has active reservation for this book
$check = $db->prepare("SELECT id FROM reservations WHERE user_id=? AND book_id=? AND status IN ('pending','active')");
$check->execute([$_SESSION['user_id'], $id]);
$existing = $check->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if ($book['available_copies'] < 1) {
        flash('danger', 'Sorry, this book is no longer available.');
        header('Location: dashboard.php');
        exit;
    }

    $dueDate = date('Y-m-d', strtotime('+' . MAX_BORROW_DAYS . ' days'));
    $notes   = trim($_POST['notes'] ?? '');

    // Create reservation
    $ins = $db->prepare("INSERT INTO reservations (user_id, book_id, due_date, notes, status) VALUES (?,?,?,?,'active')");
    $ins->execute([$_SESSION['user_id'], $id, $dueDate, $notes]);

    // Decrement available copies
    $db->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")->execute([$id]);

    flash('success', 'Book reserved successfully! Due back by ' . date('M j, Y', strtotime($dueDate)) . '.');
    header('Location: my-reservations.php');
    exit;
}

$pageTitle = 'Reserve: ' . $book['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Reserve a Book</h1>
        <p class="page-subtitle">Confirm your reservation details below</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline">← Back to Browse</a>
</div>

<div class="grid-2" style="align-items:start;">
    <!-- Book details -->
    <div class="card">
        <div style="display:flex;gap:20px;align-items:flex-start;">
            <div style="width:100px;flex-shrink:0;">
                <div class="book-cover" style="border-radius:8px;min-height:130px;">
                    <?php if ($book['cover_image'] && $book['cover_image'] !== 'default_cover.jpg'): ?>
                        <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="cover">
                    <?php else: ?>
                        <div class="book-cover-placeholder" style="font-size:2rem;">📚</div>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if ($book['category_name']): ?>
                    <span class="book-category"><?= htmlspecialchars($book['category_name']) ?></span>
                <?php endif; ?>
                <h2 style="margin:8px 0 4px;"><?= htmlspecialchars($book['title']) ?></h2>
                <p style="margin:0 0 12px;">by <strong class="text-gold"><?= htmlspecialchars($book['author']) ?></strong></p>
                <?php if ($book['publisher']): ?>
                    <p class="fs-sm">Publisher: <?= htmlspecialchars($book['publisher']) ?><?= $book['publication_year'] ? ' (' . $book['publication_year'] . ')' : '' ?></p>
                <?php endif; ?>
                <?php if ($book['isbn']): ?>
                    <p class="fs-sm">ISBN: <?= htmlspecialchars($book['isbn']) ?></p>
                <?php endif; ?>
                <p class="fs-sm mt-1">
                    Available: <span class="text-success fw-bold"><?= $book['available_copies'] ?></span> /
                    <?= $book['total_copies'] ?> copies
                </p>
            </div>
        </div>
        <?php if ($book['description']): ?>
            <hr style="border-color:var(--border);margin:16px 0;">
            <p class="fs-sm"><?= nl2br(htmlspecialchars($book['description'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Reservation form -->
    <div class="card">
        <h3 style="margin-bottom:20px;" class="text-gold">Reservation Details</h3>

        <?php if ($existing): ?>
            <div class="alert alert-warning">⚠ You already have an active reservation for this book.</div>
            <a href="my-reservations.php" class="btn btn-outline w-100">View My Reservations</a>
        <?php elseif ($book['available_copies'] < 1): ?>
            <div class="alert alert-danger">✕ This book is currently unavailable.</div>
        <?php else: ?>
            <!-- Borrow info -->
            <div style="background:var(--gold-dim);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span class="text-muted fs-sm">Member</span>
                    <span class="fw-bold"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                    <span class="text-muted fs-sm">Reservation Date</span>
                    <span class="fw-bold"><?= date('M j, Y') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span class="text-muted fs-sm">Due Date</span>
                    <span class="fw-bold text-gold"><?= date('M j, Y', strtotime('+' . MAX_BORROW_DAYS . ' days')) ?></span>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" placeholder="Any special requests or notes…" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    📖 Confirm Reservation
                </button>
            </form>
            <p class="text-center text-muted fs-sm mt-2">
                You will have <?= MAX_BORROW_DAYS ?> days to return the book.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
