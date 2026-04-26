<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/library/config/db.php';

$siteName = SITE_NAME;
$pageTitle = $pageTitle ?? $siteName;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= $siteName ?></title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body>
<div class="page-wrapper">

<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= SITE_URL ?>" class="navbar-brand">
            <span class="brand-icon">📚</span> <?= $siteName ?>
        </a>
        <div class="nav-links">
            <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/user/dashboard.php" class="nav-link">Browse</a>
                <a href="<?= SITE_URL ?>/user/my-reservations.php" class="nav-link">My Books</a>
                <a href="<?= SITE_URL ?>/user/account.php" class="nav-link">Account</a>
                <?php if (isAdmin()): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-link nav-admin">⚙ Admin</a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="nav-logout">Sign out</a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/auth/login.php" class="nav-link">Sign in</a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main>
<div class="container">

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> fade-in mt-2">
    <?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'danger' ? '✕' : 'ℹ') ?>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>
