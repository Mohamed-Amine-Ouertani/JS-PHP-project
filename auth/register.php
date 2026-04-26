<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/user/dashboard.php');
    exit;
}

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['full_name'] = trim($_POST['full_name'] ?? '');
    $data['email']     = trim($_POST['email'] ?? '');
    $data['phone']     = trim($_POST['phone'] ?? '');
    $data['address']   = trim($_POST['address'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['confirm_password'] ?? '';

    if (strlen($data['full_name']) < 3)  $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 6)           $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)          $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email address is already registered.';
        } else {
            $hash     = password_hash($password, PASSWORD_BCRYPT);
            $memberId = generateMemberId();
            $stmt = $db->prepare("INSERT INTO users (full_name, email, password, phone, address, member_id) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$data['full_name'], $data['email'], $hash, $data['phone'], $data['address'], $memberId]);

            flash('success', 'Account created! Welcome, ' . $data['full_name'] . '. Your member ID: ' . $memberId);
            header('Location: ' . SITE_URL . '/auth/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:520px;">
        <div class="auth-logo">
            <span class="logo-icon">📚</span>
            <?= SITE_NAME ?>
        </div>

        <h2 style="text-align:center;margin-bottom:8px;">Create Account</h2>
        <p class="text-center fs-sm mb-3">Join our library community</p>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div>✕ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe"
                           value="<?= htmlspecialchars($data['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone (optional)</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1 234 567 8900"
                           value="<?= htmlspecialchars($data['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com"
                       value="<?= htmlspecialchars($data['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Address (optional)</label>
                <input type="text" name="address" class="form-control" placeholder="Your address"
                       value="<?= htmlspecialchars($data['address'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">Create Account</button>
        </form>

        <div class="auth-divider">— or —</div>
        <p class="text-center fs-sm">
            Already a member? <a href="<?= SITE_URL ?>/auth/login.php">Sign in</a>
        </p>
    </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
