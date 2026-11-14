<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('staff');

$pdo = db();
$type = (string)($_GET['type'] ?? '');

$sql = 'SELECT m.movement_id, m.movement_date, m.sku, p.product_name, m.warehouse_code,
               m.movement_type, m.quantity, m.remarks, m.posted_at
          FROM stock_movements m JOIN products p ON p.sku = m.sku
         WHERE m.handled_by = ?';
$params = [$user['id']];
if (in_array($type, ['IN', 'OUT', 'ADJUST'], true)) {
    $sql .= ' AND m.movement_type = ?';
    $params[] = $type;
}
$sql .= ' ORDER BY m.movement_id DESC LIMIT 300';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'My Movements';
$pageSub   = 'Everything you have posted under employee ID ' . $user['code'] . '. Entries are read-only.';
$active    = 'mine';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <div class="card-head">
    <form method="get" class="filters" style="flex:1">
      <div class="field" style="max-width:220px">
        <label for="type">Type</label>
        <select class="select" id="type" name="type" onchange="this.form.submit()">
          <option value="">All types</option>
          <?php foreach (['IN', 'OUT', 'ADJUST'] as $t): ?>
            <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <a class="btn btn-primary" href="<?= e(url('staff/new_movement.php')) ?>"><?= icon('plus', 16) ?> Record movement</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>ID</th><th>Date</th><th>SKU</th><th>Product</th><th>Warehouse</th><th>Type</th><th class="num">Qty</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td class="empty-row" colspan="8">No movements found.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono"><?= e(movement_code((int)$r['movement_id'])) ?></td>
          <td><?= e(fmt_date($r['movement_date'])) ?></td>
          <td class="mono"><?= e($r['sku']) ?></td>
          <td><?= e($r['product_name']) ?></td>
          <td><?= e($r['warehouse_code']) ?></td>
          <td><?= type_badge($r['movement_type']) ?></td>
          <td class="num"><?= signed_qty($r['movement_type'], (int)$r['quantity']) ?></td>
          <td class="muted"><?= e($r['remarks'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
