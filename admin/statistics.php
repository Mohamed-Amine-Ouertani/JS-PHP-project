<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin(); requireAdmin();

$db = getDB();

// Update overdue
$db->query("UPDATE reservations SET status='overdue' WHERE due_date < CURDATE() AND status='active'");

// ── Core counters ────────────────────────────────────────────────────────
$totalBooks     = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalRes       = $db->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$activeRes      = $db->query("SELECT COUNT(*) FROM reservations WHERE status='active'")->fetchColumn();
$returnedRes    = $db->query("SELECT COUNT(*) FROM reservations WHERE status='returned'")->fetchColumn();
$overdueRes     = $db->query("SELECT COUNT(*) FROM reservations WHERE status='overdue'")->fetchColumn();
$cancelledRes   = $db->query("SELECT COUNT(*) FROM reservations WHERE status='cancelled'")->fetchColumn();
$pendingRes     = $db->query("SELECT COUNT(*) FROM reservations WHERE status='pending'")->fetchColumn();
$unavailBooks   = $db->query("SELECT COUNT(*) FROM books WHERE available_copies=0")->fetchColumn();
$totalCopies    = $db->query("SELECT SUM(total_copies) FROM books")->fetchColumn();
$availCopies    = $db->query("SELECT SUM(available_copies) FROM books")->fetchColumn();

// ── Monthly reservations — last 12 months ────────────────────────────────
$monthly = $db->query("
    SELECT DATE_FORMAT(reserved_at,'%b %Y') AS month,
           COUNT(*) AS total,
           SUM(status='returned') AS returned,
           SUM(status='overdue') AS overdue
    FROM reservations
    WHERE reserved_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(reserved_at), MONTH(reserved_at)
    ORDER BY reserved_at
")->fetchAll();
$mLabels   = json_encode(array_column($monthly, 'month'));
$mTotal    = json_encode(array_column($monthly, 'total'));
$mReturned = json_encode(array_column($monthly, 'returned'));
$mOverdue  = json_encode(array_column($monthly, 'overdue'));

// ── Reservations by category ─────────────────────────────────────────────
$byCat = $db->query("
    SELECT COALESCE(c.name,'Uncategorized') AS category, COUNT(r.id) AS cnt
    FROM reservations r
    JOIN books b ON r.book_id=b.id
    LEFT JOIN categories c ON b.category_id=c.id
    GROUP BY b.category_id
    ORDER BY cnt DESC LIMIT 8
")->fetchAll();
$catLabels = json_encode(array_column($byCat, 'category'));
$catValues = json_encode(array_column($byCat, 'cnt'));

// ── Top 10 borrowed books ────────────────────────────────────────────────
$topBooks = $db->query("
    SELECT b.title, b.author, COUNT(r.id) AS borrows,
           SUM(r.status='overdue') AS overdues
    FROM reservations r JOIN books b ON r.book_id=b.id
    GROUP BY r.book_id ORDER BY borrows DESC LIMIT 10
")->fetchAll();

// ── Top active members ───────────────────────────────────────────────────
$topMembers = $db->query("
    SELECT u.full_name, u.member_id, u.email,
           COUNT(r.id) AS total,
           SUM(r.status='overdue') AS overdues
    FROM reservations r JOIN users u ON r.user_id=u.id
    GROUP BY r.user_id ORDER BY total DESC LIMIT 8
")->fetchAll();

// ── Books never borrowed ─────────────────────────────────────────────────
$neverBorrowed = $db->query("
    SELECT b.title, b.author, b.added_at
    FROM books b
    LEFT JOIN reservations r ON r.book_id=b.id
    WHERE r.id IS NULL ORDER BY b.added_at DESC LIMIT 5
")->fetchAll();

// ── Avg return time ──────────────────────────────────────────────────────
$avgReturn = $db->query("
    SELECT ROUND(AVG(DATEDIFF(returned_at, reserved_at)),1) AS avg_days
    FROM reservations WHERE status='returned' AND returned_at IS NOT NULL
")->fetchColumn();

$pageTitle = 'Statistics';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Library Statistics</h1>
        <p class="page-subtitle">Comprehensive analytics — generated <?= date('M j, Y \a\t H:i') ?></p>
    </div>
    <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
</div>

<!-- ── KPI Strip ─────────────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-number"><?= $totalBooks ?></div>
        <div class="stat-label">Total Books</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-number"><?= $totalCopies ?></div>
        <div class="stat-label">Total Copies</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-number" style="color:var(--success);"><?= $availCopies ?></div>
        <div class="stat-label">Available Now</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-number"><?= $totalUsers ?></div>
        <div class="stat-label">Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔖</div>
        <div class="stat-number"><?= $totalRes ?></div>
        <div class="stat-label">All Reservations</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📖</div>
        <div class="stat-number" style="color:var(--success);"><?= $activeRes ?></div>
        <div class="stat-label">Currently Borrowed</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">↩</div>
        <div class="stat-number"><?= $returnedRes ?></div>
        <div class="stat-label">Returned</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏰</div>
        <div class="stat-number" style="color:var(--danger);"><?= $overdueRes ?></div>
        <div class="stat-label">Overdue</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⌛</div>
        <div class="stat-number" style="color:var(--warning);"><?= $pendingRes ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-number"><?= $cancelledRes ?></div>
        <div class="stat-label">Cancelled</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚫</div>
        <div class="stat-number" style="color:var(--danger);"><?= $unavailBooks ?></div>
        <div class="stat-label">Books Out of Stock</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-number"><?= $avgReturn ?? '—' ?></div>
        <div class="stat-label">Avg Return (days)</div>
    </div>
</div>

<!-- ── Monthly Trend Chart ────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
    <h3 class="section-title">Monthly Reservation Trends — Last 12 Months</h3>
    <div class="chart-container" style="height:300px;">
        <canvas id="trendChart"
                data-labels='<?= $mLabels ?>'
                data-total='<?= $mTotal ?>'
                data-returned='<?= $mReturned ?>'
                data-overdue='<?= $mOverdue ?>'></canvas>
    </div>
</div>

<!-- ── Category + Status Row ─────────────────────────────────────────── -->
<div class="grid-2" style="margin-bottom:24px;align-items:start;">
    <div class="card">
        <h3 class="section-title">Borrowing by Category</h3>
        <div class="chart-container">
            <canvas id="categoryChart"
                    data-labels='<?= $catLabels ?>'
                    data-values='<?= $catValues ?>'></canvas>
        </div>
    </div>
    <div class="card">
        <h3 class="section-title">Reservation Status Mix</h3>
        <div class="chart-container">
            <canvas id="statusPieChart"
                data-labels='["Active","Returned","Overdue","Pending","Cancelled"]'
                data-values='[<?= $activeRes ?>,<?= $returnedRes ?>,<?= $overdueRes ?>,<?= $pendingRes ?>,<?= $cancelledRes ?>]'></canvas>
        </div>
    </div>
</div>

<!-- ── Top Books + Top Members ───────────────────────────────────────── -->
<div class="grid-2" style="margin-bottom:24px;align-items:start;">
    <!-- Top books -->
    <div class="card">
        <h3 class="section-title">Top 10 Most Borrowed Books</h3>
        <?php if ($topBooks): ?>
        <div class="table-wrap" style="border:none;">
            <table>
                <thead>
                    <tr><th>#</th><th>Book</th><th>Borrows</th><th>Overdue</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topBooks as $i => $b): ?>
                <tr>
                    <td style="color:var(--gold);font-family:'Playfair Display',serif;font-weight:900;"><?= $i+1 ?></td>
                    <td>
                        <div style="color:var(--cream);font-weight:600;"><?= htmlspecialchars($b['title']) ?></div>
                        <div class="fs-sm text-muted"><?= htmlspecialchars($b['author']) ?></div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:rgba(255,255,255,0.06);border-radius:4px;height:6px;overflow:hidden;">
                                <div style="width:<?= round(($b['borrows']/$topBooks[0]['borrows'])*100) ?>%;background:var(--gold);height:100%;border-radius:4px;"></div>
                            </div>
                            <span class="fw-bold text-gold"><?= $b['borrows'] ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($b['overdues'] > 0): ?>
                            <span class="badge badge-overdue"><?= $b['overdues'] ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><p>No borrowing data yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Top members -->
    <div class="card">
        <h3 class="section-title">Most Active Members</h3>
        <?php if ($topMembers): ?>
        <div class="table-wrap" style="border:none;">
            <table>
                <thead>
                    <tr><th>#</th><th>Member</th><th>Total</th><th>Overdue</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topMembers as $i => $m): ?>
                <tr>
                    <td style="color:var(--gold);font-family:'Playfair Display',serif;font-weight:900;"><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar" style="width:32px;height:32px;font-size:0.8rem;">
                                <?= strtoupper(mb_substr($m['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="color:var(--cream);font-weight:600;"><?= htmlspecialchars($m['full_name']) ?></div>
                                <div class="fs-sm text-gold"><?= htmlspecialchars($m['member_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="fw-bold text-gold"><?= $m['total'] ?></span></td>
                    <td>
                        <?php if ($m['overdues'] > 0): ?>
                            <span class="badge badge-overdue"><?= $m['overdues'] ?></span>
                        <?php else: ?><span class="text-success">✓</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state"><p>No member data yet.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Never Borrowed Books ───────────────────────────────────────────── -->
<?php if ($neverBorrowed): ?>
<div class="card">
    <h3 class="section-title">⚠ Books Never Borrowed</h3>
    <p class="fs-sm text-muted" style="margin-bottom:16px;">These books have no reservation history. Consider featuring them to members.</p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php foreach ($neverBorrowed as $b): ?>
        <div style="background:rgba(240,165,0,0.07);border:1px solid rgba(240,165,0,0.2);border-radius:8px;padding:12px 16px;">
            <div class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($b['title']) ?></div>
            <div class="fs-sm text-muted"><?= htmlspecialchars($b['author']) ?></div>
            <div class="fs-sm text-muted">Added <?= date('M j, Y', strtotime($b['added_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.color = '#8a9ab5';
    Chart.defaults.font.family = "'DM Sans', sans-serif";

    // ── Trend chart (multi-line) ──────────────────────────────────────────
    const tc = document.getElementById('trendChart');
    if (tc) {
        new Chart(tc, {
            type: 'line',
            data: {
                labels: JSON.parse(tc.dataset.labels || '[]'),
                datasets: [
                    {
                        label: 'Total',
                        data: JSON.parse(tc.dataset.total || '[]'),
                        borderColor: '#c9a84c', backgroundColor: 'rgba(201,168,76,0.1)',
                        tension: 0.4, fill: true, borderWidth: 2, pointRadius: 4
                    },
                    {
                        label: 'Returned',
                        data: JSON.parse(tc.dataset.returned || '[]'),
                        borderColor: '#4caf7d', backgroundColor: 'rgba(76,175,125,0.08)',
                        tension: 0.4, fill: true, borderWidth: 2, pointRadius: 3
                    },
                    {
                        label: 'Overdue',
                        data: JSON.parse(tc.dataset.overdue || '[]'),
                        borderColor: '#e05252', backgroundColor: 'rgba(224,82,82,0.08)',
                        tension: 0.4, fill: true, borderWidth: 2, pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // ── Category bar chart ────────────────────────────────────────────────
    const cc = document.getElementById('categoryChart');
    if (cc) {
        const colors = ['#c9a84c','#4caf7d','#8a9ab5','#e05252','#f0a500','#5b9bd5','#9b59b6','#e67e22'];
        new Chart(cc, {
            type: 'bar',
            data: {
                labels: JSON.parse(cc.dataset.labels || '[]'),
                datasets: [{
                    label: 'Borrows',
                    data: JSON.parse(cc.dataset.values || '[]'),
                    backgroundColor: colors,
                    borderRadius: 6, borderWidth: 0
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // ── Status pie ────────────────────────────────────────────────────────
    const sp = document.getElementById('statusPieChart');
    if (sp) {
        new Chart(sp, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(sp.dataset.labels || '[]'),
                datasets: [{
                    data: JSON.parse(sp.dataset.values || '[]'),
                    backgroundColor: ['#4caf7d','#8a9ab5','#e05252','#f0a500','#c9a84c'],
                    borderWidth: 0, hoverOffset: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { padding: 16, usePointStyle: true } } },
                cutout: '68%'
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
