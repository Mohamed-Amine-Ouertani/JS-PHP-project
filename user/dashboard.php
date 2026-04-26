<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pageTitle = 'Browse Books';
$db = getDB();

// Filters
$search   = trim($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// Build query
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($category) {
    $where[]  = "b.category_id = ?";
    $params[] = $category;
}
$whereStr = implode(' AND ', $where);

$total      = $db->prepare("SELECT COUNT(*) FROM books b WHERE $whereStr");
$total->execute($params);
$totalBooks = $total->fetchColumn();
$totalPages = ceil($totalBooks / $perPage);

$stmt = $db->prepare("SELECT b.*, c.name AS category_name FROM books b
                       LEFT JOIN categories c ON b.category_id = c.id
                       WHERE $whereStr ORDER BY b.added_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Browse Library</h1>
        <p class="page-subtitle"><?= number_format($totalBooks) ?> books in our collection</p>
    </div>
</div>

<!-- Search & Filter -->
<form method="GET" class="search-bar" style="flex-wrap:wrap;">
    <input type="text" name="search" class="form-control" placeholder="Search by title, author, or ISBN…"
           value="<?= htmlspecialchars($search) ?>">
    <select name="category" class="form-control" style="max-width:200px;">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search || $category): ?>
        <a href="?" class="btn btn-outline">Clear</a>
    <?php endif; ?>
</form>

<!-- Books Grid -->
<?php if ($books): ?>
<div class="books-grid">
    <?php foreach ($books as $book): ?>
    <div class="book-card">
        <div class="book-cover">
            <?php if ($book['cover_image'] && $book['cover_image'] !== 'default_cover.jpg'): ?>
                <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>" alt="cover">
            <?php else: ?>
                <div class="book-cover-placeholder">📚</div>
            <?php endif; ?>
        </div>
        <div class="book-info">
            <?php if ($book['category_name']): ?>
                <span class="book-category"><?= htmlspecialchars($book['category_name']) ?></span>
            <?php endif; ?>
            <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
            <div class="book-author">by <?= htmlspecialchars($book['author']) ?></div>
            <div class="book-copies">
                <?php if ($book['available_copies'] > 0): ?>
                    <span class="badge badge-available">✓ <?= $book['available_copies'] ?> available</span>
                <?php else: ?>
                    <span class="badge badge-unavailable">✕ Unavailable</span>
                <?php endif; ?>
            </div>
            <?php if ($book['available_copies'] > 0): ?>
                <a href="reserve.php?id=<?= $book['id'] ?>" class="btn btn-primary btn-sm w-100">Reserve</a>
            <?php else: ?>
                <button class="btn btn-outline btn-sm w-100" disabled>Not Available</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state">
    <div class="empty-icon">📭</div>
    <h3>No books found</h3>
    <p>Try adjusting your search or browse all categories.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
