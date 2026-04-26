<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin(); requireAdmin();

$db = getDB();

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $tid = intval($_POST['toggle_id']);
    $db->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([$tid]);
    flash('success', 'Member status updated.');
    header('Location: customers.php'); exit;
}

// Delete member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = intval($_POST['delete_id']);
    $db->prepare("DELETE FROM users WHERE id=? AND role='user'")->execute([$did]);
    flash('success', 'Member deleted.');
    header('Location: customers.php'); exit;
}

$search  = trim($_GET['search'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = $search ? "WHERE (u.full_name LIKE ? OR u.email LIKE ? OR u.member_id LIKE ?) AND u.role='user'" : "WHERE u.role='user'";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM users u $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT u.*,
        COUNT(r.id) AS total_reservations,
        SUM(r.status = 'active') AS active_reservations,
        SUM(r.status = 'overdue') AS overdue_reservations
    FROM users u
    LEFT JOIN reservations r ON r.user_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$members = $stmt->fetchAll();

$pageTitle = 'Manage Members';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Member Management</h1>
        <p class="page-subtitle"><?= $totalCount ?> registered members</p>
    </div>
</div>

<form method="GET" class="search-bar">
    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or member ID…"
           value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-outline">Clear</a><?php endif; ?>
</form>

<?php if ($members): ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Member</th><th>Member ID</th><th>Phone</th>
                <th>Reservations</th><th>Overdue</th><th>Status</th>
                <th>Joined</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="avatar" style="width:36px;height:36px;font-size:0.85rem;">
                        <?= strtoupper(mb_substr($m['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($m['full_name']) ?></div>
                        <div class="fs-sm text-muted"><?= htmlspecialchars($m['email']) ?></div>
                    </div>
                </div>
            </td>
            <td><span class="text-gold fw-bold"><?= htmlspecialchars($m['member_id']) ?></span></td>
            <td><?= htmlspecialchars($m['phone'] ?? '—') ?></td>
            <td><?= $m['total_reservations'] ?></td>
            <td>
                <?php if ($m['overdue_reservations'] > 0): ?>
                    <span class="badge badge-overdue"><?= $m['overdue_reservations'] ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <span class="badge badge-<?= $m['status'] === 'active' ? 'active' : 'overdue' ?>">
                    <?= ucfirst($m['status']) ?>
                </span>
            </td>
            <td class="text-muted"><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="toggle_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $m['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                            <?= $m['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                                data-confirm="Permanently delete <?= htmlspecialchars($m['full_name']) ?>?">Delete</button>
                    </form>
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
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state"><div class="empty-icon">👥</div><h3>No members found</h3></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
