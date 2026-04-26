<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$errors = [];
$success = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['full_name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (strlen($name) < 3) $errors[] = 'Name must be at least 3 characters.';

    if (empty($errors)) {
        $db->prepare("UPDATE users SET full_name=?, phone=?, address=? WHERE id=?")
           ->execute([$name, $phone, $address, $_SESSION['user_id']]);
        $_SESSION['full_name'] = $name;
        flash('success', 'Profile updated successfully!');
        header('Location: account.php');
        exit;
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        flash('success', 'Password changed successfully!');
        header('Location: account.php');
        exit;
    }
}

// Stats
$totalRes   = $db->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=?");
$totalRes->execute([$_SESSION['user_id']]);
$totalRes   = $totalRes->fetchColumn();

$activeRes  = $db->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=? AND status IN ('active','pending')");
$activeRes->execute([$_SESSION['user_id']]);
$activeRes  = $activeRes->fetchColumn();

$overdueRes = $db->prepare("SELECT COUNT(*) FROM reservations WHERE user_id=? AND status='overdue'");
$overdueRes->execute([$_SESSION['user_id']]);
$overdueRes = $overdueRes->fetchColumn();

$pageTitle = 'My Account';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Account</h1>
        <p class="page-subtitle">Manage your profile and preferences</p>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e): ?><div>✕ <?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid-2" style="align-items:start;gap:24px;">
    <!-- Left: Profile card + stats -->
    <div>
        <div class="card text-center" style="margin-bottom:20px;">
            <div class="avatar avatar-lg" style="margin:0 auto 16px;">
                <?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
            </div>
            <h2 style="margin-bottom:4px;"><?= htmlspecialchars($user['full_name']) ?></h2>
            <p class="fs-sm"><?= htmlspecialchars($user['email']) ?></p>
            <div style="margin-top:12px;">
                <span class="badge badge-<?= $user['role'] === 'admin' ? 'admin' : 'active' ?>">
                    <?= ucfirst($user['role']) ?>
                </span>
            </div>
            <hr style="border-color:var(--border);margin:16px 0;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:left;">
                <div>
                    <div class="text-muted fs-sm">Member ID</div>
                    <div class="fw-bold text-gold"><?= htmlspecialchars($user['member_id']) ?></div>
                </div>
                <div>
                    <div class="text-muted fs-sm">Member Since</div>
                    <div class="fw-bold"><?= date('M Y', strtotime($user['created_at'])) ?></div>
                </div>
                <?php if ($user['phone']): ?>
                <div>
                    <div class="text-muted fs-sm">Phone</div>
                    <div class="fw-bold"><?= htmlspecialchars($user['phone']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick stats -->
        <div class="stats-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:0;">
            <div class="stat-card card-sm">
                <div class="stat-number" style="font-size:1.8rem;"><?= $totalRes ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card card-sm">
                <div class="stat-number" style="font-size:1.8rem;color:var(--success);"><?= $activeRes ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card card-sm">
                <div class="stat-number" style="font-size:1.8rem;color:var(--danger);"><?= $overdueRes ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>

    <!-- Right: Edit forms -->
    <div>
        <!-- Edit profile -->
        <div class="card" style="margin-bottom:20px;">
            <h3 class="text-gold" style="margin-bottom:20px;">Edit Profile</h3>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <span class="fs-sm text-muted">Email cannot be changed.</span>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <!-- Change password -->
        <div class="card">
            <h3 class="text-gold" style="margin-bottom:20px;">Change Password</h3>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-outline">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
