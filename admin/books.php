<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin(); requireAdmin();

$db = getDB();
$action  = $_GET['action'] ?? 'list';
$editId  = intval($_GET['id'] ?? 0);
$errors  = [];

// ── ALLOWED IMAGE TYPES ──────────────────────────────────────────────────
const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

function uploadCover(): ?string {
    if (empty($_FILES['cover_image']['name'])) return null;
    $file = $_FILES['cover_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $mime = mime_content_type($file['tmp_name']);
    if (!array_key_exists($mime, ALLOWED)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $name = uniqid('cover_') . '.' . ALLOWED[$mime];
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name);
    return $name;
}

// ── DELETE ───────────────────────────────────────────────────────────────
if ($action === 'delete' && $editId) {
    $book = $db->prepare("SELECT cover_image FROM books WHERE id=?");
    $book->execute([$editId]);
    $b = $book->fetch();
    if ($b && $b['cover_image'] && $b['cover_image'] !== 'default_cover.jpg') {
        @unlink(UPLOAD_DIR . $b['cover_image']);
    }
    $db->prepare("DELETE FROM books WHERE id=?")->execute([$editId]);
    flash('success', 'Book deleted.');
    header('Location: books.php'); exit;
}

// ── ADD / EDIT SUBMIT ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $author   = trim($_POST['author'] ?? '');
    $isbn     = trim($_POST['isbn'] ?? '');
    $catId    = intval($_POST['category_id'] ?? 0) ?: null;
    $desc     = trim($_POST['description'] ?? '');
    $pub      = trim($_POST['publisher'] ?? '');
    $year     = intval($_POST['publication_year'] ?? 0) ?: null;
    $total    = max(1, intval($_POST['total_copies'] ?? 1));
    $avail    = max(0, intval($_POST['available_copies'] ?? $total));
    $postId   = intval($_POST['book_id'] ?? 0);

    if (!$title)  $errors[] = 'Title is required.';
    if (!$author) $errors[] = 'Author is required.';

    if (empty($errors)) {
        $cover = uploadCover();
        if ($postId) {
            // Edit
            if ($cover) {
                // Remove old cover
                $old = $db->prepare("SELECT cover_image FROM books WHERE id=?"); $old->execute([$postId]);
                $oldRow = $old->fetch();
                if ($oldRow && $oldRow['cover_image'] !== 'default_cover.jpg') @unlink(UPLOAD_DIR . $oldRow['cover_image']);

                $db->prepare("UPDATE books SET title=?,author=?,isbn=?,category_id=?,description=?,publisher=?,publication_year=?,total_copies=?,available_copies=?,cover_image=? WHERE id=?")
                   ->execute([$title,$author,$isbn,$catId,$desc,$pub,$year,$total,$avail,$cover,$postId]);
            } else {
                $db->prepare("UPDATE books SET title=?,author=?,isbn=?,category_id=?,description=?,publisher=?,publication_year=?,total_copies=?,available_copies=? WHERE id=?")
                   ->execute([$title,$author,$isbn,$catId,$desc,$pub,$year,$total,$avail,$postId]);
            }
            flash('success', 'Book updated.');
        } else {
            // Add
            $db->prepare("INSERT INTO books (title,author,isbn,category_id,description,publisher,publication_year,total_copies,available_copies,cover_image) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$title,$author,$isbn,$catId,$desc,$pub,$year,$total,$avail,$cover ?? 'default_cover.jpg']);
            flash('success', 'Book added to the library.');
        }
        header('Location: books.php'); exit;
    }
}

// ── FETCH FOR EDIT ────────────────────────────────────────────────────────
$editBook = null;
if ($action === 'edit' && $editId) {
    $s = $db->prepare("SELECT * FROM books WHERE id=?"); $s->execute([$editId]);
    $editBook = $s->fetch();
    if (!$editBook) { flash('danger','Book not found.'); header('Location: books.php'); exit; }
}

// ── LIST ──────────────────────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;
$where   = $search ? "WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?" : "WHERE 1=1";
$params  = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total   = $db->prepare("SELECT COUNT(*) FROM books b $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

$stmt = $db->prepare("SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id=c.id $where ORDER BY b.added_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'Manage Books';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= ($action === 'add' || ($action === 'edit' && $editBook)) ? (($action==='add') ? 'Add New Book' : 'Edit Book') : 'Book Management' ?></h1>
        <p class="page-subtitle">
            <?php if ($action === 'list'): ?>
                <?= $totalCount ?> books in the library
            <?php else: ?>
                Fill in the details below and upload a cover image
            <?php endif; ?>
        </p>
    </div>
    <?php if ($action !== 'list'): ?>
        <a href="books.php" class="btn btn-outline">← Back to List</a>
    <?php else: ?>
        <a href="?action=add" class="btn btn-primary">+ Add Book</a>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ── ADD / EDIT FORM ── -->
<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach($errors as $e): ?><div>✕ <?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="book_id" value="<?= $editBook['id'] ?? 0 ?>">
        <div class="card">
            <h3 class="text-gold" style="margin-bottom:20px;">Book Information</h3>
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required
                       value="<?= htmlspecialchars($editBook['title'] ?? $_POST['title'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Author *</label>
                    <input type="text" name="author" class="form-control" required
                           value="<?= htmlspecialchars($editBook['author'] ?? $_POST['author'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">ISBN</label>
                    <input type="text" name="isbn" class="form-control"
                           value="<?= htmlspecialchars($editBook['isbn'] ?? $_POST['isbn'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= (($editBook['category_id'] ?? $_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Publisher</label>
                    <input type="text" name="publisher" class="form-control"
                           value="<?= htmlspecialchars($editBook['publisher'] ?? $_POST['publisher'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Publication Year</label>
                    <input type="number" name="publication_year" class="form-control" min="1000" max="<?= date('Y') ?>"
                           value="<?= htmlspecialchars($editBook['publication_year'] ?? $_POST['publication_year'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Total Copies</label>
                    <input type="number" name="total_copies" class="form-control" min="1" required
                           value="<?= htmlspecialchars($editBook['total_copies'] ?? $_POST['total_copies'] ?? 1) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Available Copies</label>
                <input type="number" name="available_copies" class="form-control" min="0"
                       value="<?= htmlspecialchars($editBook['available_copies'] ?? $_POST['available_copies'] ?? 1) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editBook['description'] ?? $_POST['description'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Update Book' : 'Add to Library' ?></button>
                <a href="books.php" class="btn btn-outline">Cancel</a>
            </div>
        </div>
    </form>

    <!-- Cover upload card -->
    <div class="card">
        <h3 class="text-gold" style="margin-bottom:20px;">Cover Image</h3>
        <form method="POST" enctype="multipart/form-data" id="coverForm">
            <input type="hidden" name="book_id" value="<?= $editBook['id'] ?? 0 ?>">
            <!-- Hidden duplicate of required fields for cover-only submit -->
            <?php if ($editBook): ?>
                <input type="hidden" name="title" value="<?= htmlspecialchars($editBook['title']) ?>">
                <input type="hidden" name="author" value="<?= htmlspecialchars($editBook['author']) ?>">
                <input type="hidden" name="isbn" value="<?= htmlspecialchars($editBook['isbn'] ?? '') ?>">
                <input type="hidden" name="category_id" value="<?= $editBook['category_id'] ?? '' ?>">
                <input type="hidden" name="description" value="<?= htmlspecialchars($editBook['description'] ?? '') ?>">
                <input type="hidden" name="publisher" value="<?= htmlspecialchars($editBook['publisher'] ?? '') ?>">
                <input type="hidden" name="publication_year" value="<?= $editBook['publication_year'] ?? '' ?>">
                <input type="hidden" name="total_copies" value="<?= $editBook['total_copies'] ?>">
                <input type="hidden" name="available_copies" value="<?= $editBook['available_copies'] ?>">
            <?php endif; ?>

            <div class="file-upload-area">
                <input type="file" name="cover_image" accept="image/*">
                <div class="file-upload-icon">🖼</div>
                <p class="upload-label">Drop image here or click to browse</p>
                <p class="text-muted fs-sm mt-1">JPG, PNG, WebP — max 5 MB</p>
            </div>

            <?php if (!empty($editBook['cover_image']) && $editBook['cover_image'] !== 'default_cover.jpg'): ?>
            <div style="margin-top:16px;text-align:center;">
                <p class="text-muted fs-sm mb-1">Current cover:</p>
                <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($editBook['cover_image']) ?>"
                     style="max-height:200px;border-radius:8px;border:1px solid var(--border);" alt="cover">
            </div>
            <?php endif; ?>

            <img id="coverPreview" src="" style="display:none;max-width:100%;margin-top:16px;border-radius:8px;" alt="preview">
        </form>

        <div class="mt-2 card card-sm" style="background:var(--gold-dim);">
            <p class="fs-sm text-gold fw-bold">📌 Tip</p>
            <p class="fs-sm">Upload the cover using the main form on the left. The image field is included in the main save action.</p>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── LIST ── -->
<form method="GET" class="search-bar">
    <input type="text" name="search" class="form-control" placeholder="Search books…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?><a href="books.php" class="btn btn-outline">Clear</a><?php endif; ?>
</form>

<?php if ($books): ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Cover</th><th>Title</th><th>Author</th><th>Category</th>
                <th>Copies</th><th>Available</th><th>Added</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book): ?>
        <tr>
            <td>
                <div style="width:32px;height:42px;border-radius:4px;overflow:hidden;background:var(--navy-mid);display:flex;align-items:center;justify-content:center;font-size:1rem;">
                    <?php if ($book['cover_image'] && $book['cover_image'] !== 'default_cover.jpg'): ?>
                        <img src="<?= SITE_URL ?>/uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                    <?php else: ?>📚<?php endif; ?>
                </div>
            </td>
            <td><span class="fw-bold" style="color:var(--cream);"><?= htmlspecialchars($book['title']) ?></span></td>
            <td><?= htmlspecialchars($book['author']) ?></td>
            <td><?= $book['category_name'] ? '<span class="badge badge-pending">' . htmlspecialchars($book['category_name']) . '</span>' : '—' ?></td>
            <td><?= $book['total_copies'] ?></td>
            <td>
                <span class="badge badge-<?= $book['available_copies'] > 0 ? 'available' : 'unavailable' ?>">
                    <?= $book['available_copies'] ?>
                </span>
            </td>
            <td class="text-muted"><?= date('M j, Y', strtotime($book['added_at'])) ?></td>
            <td>
                <div style="display:flex;gap:6px;">
                    <a href="?action=edit&id=<?= $book['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <a href="?action=delete&id=<?= $book['id'] ?>" class="btn btn-danger btn-sm"
                       data-confirm="Delete '<?= htmlspecialchars($book['title']) ?>'? This cannot be undone.">Delete</a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state"><div class="empty-icon">📭</div><h3>No books found</h3></div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
