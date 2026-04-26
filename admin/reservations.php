<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin(); requireAdmin();

$db = getDB();

// Update overdue
$db->query("UPDATE reservations SET status='overdue' WHERE due_date < CURDATE() AND status='active'");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resId  = intval($_POST['res_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $res    = $db->prepare("SELECT * FROM reservations WHERE id=?");
    $res->execute([$resId]);
    $res = $res->fetch();

    if ($res) {
        if ($action === 'approve' && $res['status'] === 'pending') {
            $db->prepare("UPDATE reservations SET status='active' WHERE id=?")->execute([$resId]);
            flash('success', 'Reservation approved.');
        } elseif ($action === 'return' && in_array($res['status'], ['active','overdue'])) {
            $db->prepare("UPDATE reservations SET status='returned', returned_at=NOW() WHERE id=?")->execute([$resId]);
            $db->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$res['book_id']]);
            flash('success', 'Book marked as returned. Copy restored.');
        } elseif ($action === 'cancel') {
            if (in_array($res['status'], ['pending','active'])) {
                $db->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id=?")->execute([$res['book_id']]);
            }
            $db->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([$resId]);
            flash('success', 'Reservation cancelled.');
        }
    }
    header('Location: reservations.php'); exit;
}

// Filters
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($status) { $where[] = "r.status=?"; $params[] = $status; }
if ($search) {
    $where[] = "(u.full_name LIKE ? OR b.title LIKE ? OR u.member_id LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereStr = 'WHERE ' . implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN users u ON r.user_id=u.id JOIN books b ON r.book_id=b.id $whereStr");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("SELECT r.*, u.full_name, u.member_id, b.title AS book_title, b.author
                       FROM reservations r
                       JOIN users u ON r.user_id=u.id
                       JOIN books b ON r.book_id=b.id
                       $whereStr
                       ORDER BY r.reserved_at DESC
                       LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$statusOptions = ['pending','active','returned','overdue','cancelled'];

$pageTitle = 'Manage Reservations';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Reservations</h1>
        <p class="page-subtitle"><?= $totalCount ?> records<?= $status ? " — " . ucfirst($status) : "" ?></p>
    </div>
</div>

<!-- Filters -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;align-items:center;">
    <a href="reservations.php" class="btn btn-<?= !$status ? 'primary' : 'outline' ?> btn-sm">All</a>
    <?php foreach ($statusOptions as $s): ?>
        <a href="?status=<?= $s ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="btn btn-<?= $status === $s ? 'primary' : 'outline' ?> btn-sm">
            <?= ucfirst($s) ?>
        </a>
    <?php endforeach; ?>

    <form method="GET" style="display:flex;gap:8px;margin-left:auto;">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= $status ?>"><?php endif; ?>
        <input type="text" name="search" class="form-control" style="width:220px;"
               placeholder="Search name or book…" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
</div>

<?php if ($reservations): ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th><th>Member</th><th>Book</th>
                <th>Reserved</th><th>Due Date</th><th>Returned</th>
                <th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
        <tr>
            <td class="text-muted fs-sm">#<?= $r['id'] ?></td>
            <td>
                <div class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($r['full_name']) ?></div>
                <div class="fs-sm text-gold"><?= htmlspecialchars($r['member_id']) ?></div>
            </td>
            <td>
                <div style="color:var(--cream);"><?= htmlspecialchars($r['book_title']) ?></div>
                <div class="fs-sm text-muted"><?= htmlspecialchars($r['author']) ?></div>
            </td>
            <td><?= date('M j, Y', strtotime($r['reserved_at'])) ?></td>
            <td>
                <?php $isOverdue = strtotime($r['due_date']) < time() && in_array($r['status'], ['active','overdue']); ?>
                <span class="<?= $isOverdue ? 'text-danger fw-bold' : '' ?>">
                    <?= date('M j, Y', strtotime($r['due_date'])) ?>
                </span>
            </td>
            <td><?= $r['returned_at'] ? date('M j, Y', strtotime($r['returned_at'])) : '—' ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-success btn-sm">Approve</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['active','overdue'])): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="return">
                            <button class="btn btn-primary btn-sm">Return</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['pending','active','overdue'])): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <button class="btn btn-danger btn-sm"
                                    data-confirm="Cancel this reservation?">Cancel</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($r['status'], ['returned','cancelled'])): ?>
                        <span class="text-muted fs-sm">—</span>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state"><div class="empty-icon">🔖</div><h3>No reservations found</h3></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
