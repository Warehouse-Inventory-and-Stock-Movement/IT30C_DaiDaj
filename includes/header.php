<?php
$user = current_user();
$role = $user['role'] ?? 'staff';

$nav = $role === 'manager'
    ? [
        ['dashboard', 'Dashboard',      'manager/dashboard.php',  'dashboard'],
        ['stock',     'Stock on Hand',  'stock.php',              'layers'],
        ['ledger',    'Movement Ledger','manager/movements.php',  'list'],
        ['products',  'Products',       'manager/products.php',   'box'],
        ['suppliers', 'Suppliers',      'manager/suppliers.php',  'truck'],
        ['users',     'Users & Roles',  'manager/users.php',      'users'],
      ]
    : [
        ['dashboard', 'Dashboard',       'staff/dashboard.php',     'dashboard'],
        ['record',    'Record Movement', 'staff/new_movement.php',  'plus'],
        ['mine',      'My Movements',    'staff/my_movements.php',  'list'],
        ['stock',     'Stock on Hand',   'stock.php',               'layers'],
      ];

$initials = '';
foreach (preg_split('/\s+/', trim($user['name'] ?? 'U')) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    if (mb_strlen($initials) >= 2) break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <span class="brand-mark"><?= icon('box', 20) ?></span>
      <div>
        <div class="brand-name"><?= e(APP_NAME) ?></div>
        <div class="brand-sub">Wholesale Warehouse</div>
      </div>
    </div>

    <div class="nav-label"><?= $role === 'manager' ? 'Manager workspace' : 'Staff workspace' ?></div>
    <nav class="nav">
      <?php foreach ($nav as [$key, $label, $path, $ico]): ?>
        <a href="<?= e(url($path)) ?>" class="nav-link <?= ($active ?? '') === $key ? 'is-active' : '' ?>">
          <?= icon($ico) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="user-chip">
        <span class="avatar"><?= e($initials) ?></span>
        <div class="user-meta">
          <div class="user-name"><?= e($user['name'] ?? '') ?></div>
          <div class="user-role"><?= e($user['code'] ?? '') ?> &middot; <?= e(ucfirst($role)) ?></div>
        </div>
      </div>
      <form method="post" action="<?= e(url('logout.php')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-block"><?= icon('logout', 16) ?> Sign out</button>
      </form>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="menu-toggle" type="button" id="menuToggle" aria-label="Toggle menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <div>
        <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
        <?php if (!empty($pageSub)): ?><p class="page-sub"><?= e($pageSub) ?></p><?php endif; ?>
      </div>
      <div class="topbar-right">
        <span class="role-pill role-<?= e($role) ?>"><?= icon('shield', 14) ?> <?= e(ucfirst($role)) ?></span>
      </div>
    </header>

    <main class="content">
      <?php foreach (pull_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
