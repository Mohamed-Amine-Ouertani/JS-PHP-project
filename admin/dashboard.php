<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin(); requireAdmin();

$db = getDB();

// ── Core Stats ──────────────────────────────────────────────────────────
$totalBooks     = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalRes       = $db->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$activeRes      = $db->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn();
$overdueRes     = $db->query("SELECT COUNT(*) FROM reservations WHERE status='overdue'")->fetchColumn();
$pendingRes     = $db->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn();
$availableBooks = $db->query("SELECT SUM(available_copies) FROM books")->fetchColumn();

// Update overdue
$db->query("UPDATE reservations SET status='overdue' WHERE due_date < CURDATE() AND status='active'");

// ── Reservations by month (last 6 months) ───────────────────────────────
$months = $db->query("
    SELECT DATE_FORMAT(reserved_at,'%b %Y') AS month, COUNT(*) AS cnt
    FROM reservations
    WHERE reserved_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(reserved_at), MONTH(reserved_at)
    ORDER BY reserved_at
")->fetchAll();
$monthLabels = json_encode(array_column($months, 'month'));
$monthValues = json_encode(array_column($months, 'cnt'));

// ── Status distribution ─────────────────────────────────────────────────
$statusStats = $db->query("SELECT status, COUNT(*) AS cnt FROM reservations GROUP BY status")->fetchAll();
$statusLabels = json_encode(array_map(fn($r) => ucfirst($r['status']), $statusStats));
$statusValues = json_encode(array_column($statusStats, 'cnt'));

// ── Most borrowed books ──────────────────────────────────────────────────
$topBooks = $db->query("
    SELECT b.title, b.author, COUNT(r.id) AS borrows
    FROM reservations r JOIN books b ON r.book_id = b.id
    GROUP BY r.book_id ORDER BY borrows DESC LIMIT 5
")->fetchAll();

// ── Recent reservations ──────────────────────────────────────────────────
$recentRes = $db->query("
    SELECT r.*, u.full_name, b.title
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    JOIN books b ON r.book_id = b.id
    ORDER BY r.reserved_at DESC LIMIT 8
")->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Admin Dashboard</h1>
        <p class="page-subtitle">Library overview — <?= date('l, F j, Y') ?></p>
    </div>
    <a href="books.php?action=add" class="btn btn-primary">+ Add New Book</a>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-number"><?= $totalBooks ?></div>
        <div class="stat-label">Total Books</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📖</div>
        <div class="stat-number" style="color:var(--success);"><?= $availableBooks ?></div>
        <div class="stat-label">Copies Available</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👤</div>
        <div class="stat-number"><?= $totalUsers ?></div>
        <div class="stat-label">Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔖</div>
        <div class="stat-number"><?= $totalRes ?></div>
        <div class="stat-label">Total Reservations</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-number" style="color:var(--success);"><?= $activeRes ?></div>
        <div class="stat-label">Currently Borrowed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏰</div>
        <div class="stat-number" style="color:var(--danger);"><?= $overdueRes ?></div>
        <div class="stat-label">Overdue</div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid-2" style="margin-bottom:32px;align-items:start;">
    <div class="card">
        <h3 class="section-title">Reservations — Last 6 Months</h3>
        <div class="chart-container">
            <canvas id="reservationsChart"
                    data-labels='<?= $monthLabels ?>'
                    data-values='<?= $monthValues ?>'></canvas>
        </div>
    </div>
    <div class="card">
        <h3 class="section-title">Status Breakdown</h3>
        <div class="chart-container">
            <canvas id="statusChart"
                    data-labels='<?= $statusLabels ?>'
                    data-values='<?= $statusValues ?>'></canvas>
        </div>
    </div>
</div>

<!-- Two-column bottom -->
<div class="grid-2" style="align-items:start;">
    <!-- Top borrowed books -->
    <div class="card">
        <h3 class="section-title">Most Borrowed Books</h3>
        <?php if ($topBooks): ?>
        <div>
            <?php foreach ($topBooks as $i => $b): ?>
            <div style="display:flex;align-items:center;gap:14px;padding:10px 0;border-bottom:1px solid var(--border);">
                <span style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--gold);width:28px;text-align:center;font-weight:900;"><?= $i+1 ?></span>
                <div style="flex:1;">
                    <div class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($b['title']) ?></div>
                    <div class="text-muted fs-sm"><?= htmlspecialchars($b['author']) ?></div>
                </div>
                <span class="badge badge-active"><?= $b['borrows'] ?> borrows</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state"><p>No borrowing data yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Recent activity -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 class="section-title" style="margin-bottom:0;">Recent Activity</h3>
            <a href="reservations.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php foreach ($recentRes as $r): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
            <div class="avatar" style="width:36px;height:36px;font-size:0.9rem;">
                <?= strtoupper(mb_substr($r['full_name'], 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="color:var(--cream);font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <strong><?= htmlspecialchars($r['full_name']) ?></strong> · <?= htmlspecialchars($r['title']) ?>
                </div>
                <div class="text-muted fs-sm"><?= timeAgo($r['reserved_at']) ?></div>
            </div>
            <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Quick nav -->
<div style="display:flex;gap:16px;margin-top:28px;flex-wrap:wrap;">
    <a href="books.php" class="card card-hover" style="flex:1;min-width:160px;text-align:center;padding:20px;">
        <div style="font-size:2rem;margin-bottom:8px;">📚</div>
        <div class="fw-bold" style="color:var(--cream);">Manage Books</div>
        <div class="text-muted fs-sm"><?= $totalBooks ?> total</div>
    </a>
    <a href="customers.php" class="card card-hover" style="flex:1;min-width:160px;text-align:center;padding:20px;text-decoration:none;">
        <div style="font-size:2rem;margin-bottom:8px;">👥</div>
        <div class="fw-bold" style="color:var(--cream);">Manage Members</div>
        <div class="text-muted fs-sm"><?= $totalUsers ?> members</div>
    </a>
    <a href="reservations.php" class="card card-hover" style="flex:1;min-width:160px;text-align:center;padding:20px;text-decoration:none;">
        <div style="font-size:2rem;margin-bottom:8px;">🔖</div>
        <div class="fw-bold" style="color:var(--cream);">Reservations</div>
        <div class="text-muted fs-sm"><?= $pendingRes ?> pending</div>
    </a>
    <a href="statistics.php" class="card card-hover" style="flex:1;min-width:160px;text-align:center;padding:20px;text-decoration:none;">
        <div style="font-size:2rem;margin-bottom:8px;">📊</div>
        <div class="fw-bold" style="color:var(--cream);">Statistics</div>
        <div class="text-muted fs-sm">Detailed reports</div>
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
