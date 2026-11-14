<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me  = require_role('manager');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $targetId = (int)($_POST['user_id'] ?? 0);
    $action   = (string)($_POST['action'] ?? '');

    if ($targetId === $me['id']) {
        flash('error', 'You cannot change your own role or status.');
        redirect('manager/users.php');
    }

    $st = $pdo->prepare('SELECT user_id, full_name, role, is_active FROM users WHERE user_id = ?');
    $st->execute([$targetId]);
    $target = $st->fetch();

    if (!$target) {
        flash('error', 'User not found.');
    } elseif ($action === 'set_role') {
        $newRole = (string)($_POST['role'] ?? '');
        if (in_array($newRole, ['manager', 'staff'], true)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?')->execute([$newRole, $targetId]);
            flash('success', $target['full_name'] . ' is now ' . $newRole . '.');
        }
    } elseif ($action === 'toggle_active') {
        $new = (int)$target['is_active'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?')->execute([$new, $targetId]);
        flash('success', $target['full_name'] . ($new ? ' reactivated.' : ' deactivated.'));
    }
    redirect('manager/users.php');
}

$users = $pdo->query(
    'SELECT u.user_id, u.employee_code, u.full_name, u.email, u.role, u.is_active, u.created_at,
            COUNT(m.movement_id) AS movements
       FROM users u LEFT JOIN stock_movements m ON m.handled_by = u.user_id
      GROUP BY u.user_id, u.employee_code, u.full_name, u.email, u.role, u.is_active, u.created_at
      ORDER BY u.employee_code'
)->fetchAll();

$pageTitle = 'Users & Roles';
$pageSub   = 'Control who can access the system. Accounts are deactivated, never deleted, so movement history keeps its owner.';
$active    = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-head"><div class="card-title">Employees</div><div class="card-sub"><?= count($users) ?> accounts</div></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Employee ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="num">Movements</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int)$u['user_id'] === $me['id']; ?>
        <tr>
          <td class="mono"><?= e($u['employee_code']) ?></td>
          <td><strong><?= e($u['full_name']) ?></strong><?= $self ? ' <span class="muted">(you)</span>' : '' ?></td>
          <td class="muted"><?= e($u['email']) ?></td>
          <td><span class="badge badge-<?= e($u['role']) ?>"><?= e(ucfirst($u['role'])) ?></span></td>
          <td><?= (int)$u['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
          <td class="num"><?= (int)$u['movements'] ?></td>
          <td>
            <?php if ($self): ?>
              <span class="muted">-</span>
            <?php else: ?>
              <div class="btn-row">
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="role" value="<?= $u['role'] === 'manager' ? 'staff' : 'manager' ?>">
                  <button class="btn btn-outline btn-sm" type="submit"><?= $u['role'] === 'manager' ? 'Make staff' : 'Make manager' ?></button>
                </form>
                <form method="post" data-confirm="<?= (int)$u['is_active'] ? 'Deactivate this account?' : 'Reactivate this account?' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                  <input type="hidden" name="action" value="toggle_active">
                  <button class="btn btn-danger btn-sm" type="submit"><?= (int)$u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
