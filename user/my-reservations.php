<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db = getDB();

// Cancel reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = intval($_POST['cancel_id']);
    // Verify ownership
    $check = $db->prepare("SELECT * FROM reservations WHERE id=? AND user_id=? AND status='pending'");
    $check->execute([$cancelId, $_SESSION['user_id']]);
    if ($res = $check->fetch()) {
        $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([$cancelId]);
        $db->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$res['book_id']]);
        flash('success', 'Reservation cancelled.');
    }
    header('Location: my-reservations.php');
    exit;
}

// Update overdue status
$db->prepare("UPDATE reservations SET status='overdue' WHERE due_date < CURDATE() AND status='active'")->execute();

$stmt = $db->prepare("SELECT r.*, b.title, b.author, b.cover_image FROM reservations r
                       JOIN books b ON r.book_id = b.id
                       WHERE r.user_id = ? ORDER BY r.reserved_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$reservations = $stmt->fetchAll();

$pageTitle = 'My Reservations';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Books</h1>
        <p class="page-subtitle"><?= count($reservations) ?> total reservations</p>
    </div>
    <a href="dashboard.php" class="btn btn-primary">+ Reserve New Book</a>
</div>

<?php if ($reservations): ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Book</th>
                <th>Reserved On</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:48px;border-radius:4px;overflow:hidden;background:var(--navy-mid);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                            <?php if ($r['cover_image'] && $r['cover_image'] !== 'default_cover.jpg'): ?>
                                <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($r['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                            <?php else: ?>📚<?php endif; ?>
                        </div>
                        <div>
                            <div class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="fs-sm text-muted"><?= htmlspecialchars($r['author']) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= date('M j, Y', strtotime($r['reserved_at'])) ?></td>
                <td>
                    <?php $isOverdue = strtotime($r['due_date']) < time() && in_array($r['status'], ['active','overdue']); ?>
                    <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                        <?= date('M j, Y', strtotime($r['due_date'])) ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $r['status'] ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="cancel_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Cancel this reservation?">Cancel</button>
                        </form>
                    <?php elseif ($r['status'] === 'returned'): ?>
                        <span class="text-muted fs-sm">Returned <?= $r['returned_at'] ? date('M j', strtotime($r['returned_at'])) : '' ?></span>
                    <?php elseif ($r['status'] === 'overdue'): ?>
                        <span class="text-danger fs-sm fw-bold">⚠ Please return</span>
                    <?php else: ?>
                        <span class="text-muted fs-sm">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <div class="empty-icon">📚</div>
    <h3>No reservations yet</h3>
    <p>Start browsing our library and reserve your first book.</p>
    <a href="dashboard.php" class="btn btn-primary mt-2">Browse Books</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
