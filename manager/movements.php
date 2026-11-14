<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('manager');

$pdo = db();
$warehouses = $pdo->query('SELECT warehouse_code, warehouse_name FROM warehouses ORDER BY warehouse_code')->fetchAll();
$productsList = $pdo->query('SELECT sku, product_name FROM products ORDER BY sku')->fetchAll();

$type = (string)($_GET['type'] ?? '');
$wh   = (string)($_GET['warehouse'] ?? '');
$sku  = (string)($_GET['sku'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to   = (string)($_GET['to'] ?? '');

$where = [];
$params = [];
if (in_array($type, ['IN', 'OUT', 'ADJUST'], true)) { $where[] = 'm.movement_type = ?'; $params[] = $type; }
if ($wh !== '')  { $where[] = 'm.warehouse_code = ?'; $params[] = $wh; }
if ($sku !== '') { $where[] = 'm.sku = ?'; $params[] = $sku; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'm.movement_date >= ?'; $params[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'm.movement_date <= ?'; $params[] = $to; }

$sql = 'SELECT m.movement_id, m.movement_date, m.sku, p.product_name, m.warehouse_code,
               m.movement_type, m.quantity, m.remarks, u.full_name, u.employee_code
          FROM stock_movements m
          JOIN products p ON p.sku = m.sku
          JOIN users u    ON u.user_id = m.handled_by'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY m.movement_date DESC, m.movement_id DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Movement Ledger';
$pageSub   = 'Posted movements are permanent. Corrections are made with an ADJUST entry.';
$active    = 'ledger';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label for="type">Type</label>
      <select class="select" id="type" name="type">
        <option value="">All</option>
        <?php foreach (['IN', 'OUT', 'ADJUST'] as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="warehouse">Warehouse</label>
      <select class="select" id="warehouse" name="warehouse">
        <option value="">All</option>
        <?php foreach ($warehouses as $w): ?>
          <option value="<?= e($w['warehouse_code']) ?>" <?= $wh === $w['warehouse_code'] ? 'selected' : '' ?>><?= e($w['warehouse_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sku">Product</label>
      <select class="select" id="sku" name="sku">
        <option value="">All</option>
        <?php foreach ($productsList as $p): ?>
          <option value="<?= e($p['sku']) ?>" <?= $sku === $p['sku'] ? 'selected' : '' ?>><?= e($p['sku'] . ' - ' . $p['product_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="btn-row">
      <button class="btn btn-primary" type="submit"><?= icon('search', 16) ?> Filter</button>
      <a class="btn btn-outline" href="<?= e(url('manager/movements.php')) ?>">Reset</a>
    </div>
  </form>
</div>

<div class="card mt">
  <div class="card-head"><div class="card-sub"><?= count($rows) ?> movement<?= count($rows) === 1 ? '' : 's' ?> shown</div></div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>Date</th><th>SKU</th><th>Product</th><th>Warehouse</th><th>Type</th><th class="num">Qty</th><th>Handled by</th><th>Remarks</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?><tr><td class="empty-row" colspan="9">No movements match your filters.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e(movement_code((int)$r['movement_id'])) ?></td>
          <td><?= e(fmt_date($r['movement_date'])) ?></td>
          <td class="mono"><?= e($r['sku']) ?></td>
          <td><?= e($r['product_name']) ?></td>
          <td><?= e($r['warehouse_code']) ?></td>
          <td><?= type_badge($r['movement_type']) ?></td>
          <td class="num"><?= signed_qty($r['movement_type'], (int)$r['quantity']) ?></td>
          <td><?= e($r['full_name']) ?> <span class="muted mono"><?= e($r['employee_code']) ?></span></td>
          <td class="muted"><?= e($r['remarks'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
