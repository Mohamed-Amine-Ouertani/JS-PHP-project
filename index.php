<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit;
}

$pageTitle = 'Welcome';
include __DIR__ . '/includes/header.php';

// Fetch a few stats for landing page
$db = getDB();
$totalBooks  = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalUsers  = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalBorrow = $db->query("SELECT COUNT(*) FROM reservations WHERE status IN ('active','returned')")->fetchColumn();
?>

<div class="hero">
    <div class="hero-badge">📚 &nbsp; Welcome to Bibliotheca</div>
    <h1>Your Digital<br><span>Library Gateway</span></h1>
    <p>Discover, reserve, and manage books seamlessly. Join our growing community of readers and knowledge seekers.</p>
    <div class="hero-actions">
        <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-primary btn-lg">Get Started</a>
        <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-outline btn-lg">Sign In</a>
    </div>
</div>

<!-- Stats strip -->
<div class="stats-grid" style="max-width:700px;margin:0 auto 60px;">
    <div class="stat-card">
        <div class="stat-icon">📖</div>
        <div class="stat-number"><?= number_format($totalBooks) ?></div>
        <div class="stat-label">Books Available</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👤</div>
        <div class="stat-number"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔖</div>
        <div class="stat-number"><?= number_format($totalBorrow) ?></div>
        <div class="stat-label">Books Borrowed</div>
    </div>
</div>

<!-- Featured books preview -->
<?php
$featured = $db->query("SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id ORDER BY b.added_at DESC LIMIT 6")->fetchAll();
?>
<div style="max-width:900px;margin:0 auto;">
    <h2 class="section-title" style="font-size:1.6rem;">Recently Added</h2>
    <div class="books-grid">
        <?php foreach ($featured as $book): ?>
        <div class="book-card">
            <div class="book-cover">
                <?php if ($book['cover_image'] && $book['cover_image'] !== 'default_cover.jpg' && file_exists(UPLOAD_DIR . $book['cover_image'])): ?>
                    <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="cover">
                <?php else: ?>
                    <div class="book-cover-placeholder">📚</div>
                <?php endif; ?>
            </div>
            <div class="book-info">
                <?php if ($book['category_name']): ?><span class="book-category"><?= htmlspecialchars($book['category_name']) ?></span><?php endif; ?>
                <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                <div class="book-author"><?= htmlspecialchars($book['author']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-3">
        <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-outline">View all books →</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
