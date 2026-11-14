<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('staff');  

$pdo = db();

$st = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(movement_type = 'IN'), 0)     AS ins,
            COALESCE(SUM(movement_type = 'OUT'), 0)    AS outs,
            COALESCE(SUM(movement_type = 'ADJUST'), 0) AS adjs
       FROM stock_movements WHERE handled_by = ?"
);
$st->execute([$user['id']]);
$mine = $st->fetch();

$st = $pdo->prepare(
    'SELECT m.movement_id, m.movement_date, m.sku, p.product_name, m.warehouse_code, m.movement_type, m.quantity
       FROM stock_movements m JOIN products p ON p.sku = m.sku
      WHERE m.handled_by = ? ORDER BY m.movement_id DESC LIMIT 6'
);
$st->execute([$user['id']]);
$recent = $st->fetchAll();

$stock = $pdo->query(
    'SELECT sku, product_name, SUM(on_hand) AS total, MAX(reorder_level) AS reorder
       FROM v_stock_on_hand WHERE is_active = 1
      GROUP BY sku, product_name ORDER BY sku'
)->fetchAll();

$pageTitle = 'Staff Dashboard';
$pageSub   = 'Record stock movements and check what is on the shelves.';
$active    = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
  <div>
    <h2>Hello, <?= e(explode(' ', $user['name'])[0]) ?>.</h2>
    <p>Employee ID <strong><?= e($user['code']) ?></strong> &middot; every movement you post is recorded under your name.</p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('staff/new_movement.php')) ?>"><?= icon('plus', 16) ?> Record movement</a>
</div>

<div class="grid grid-3 mt">
  <a class="action-card" href="<?= e(url('staff/new_movement.php?type=IN')) ?>">
    <div class="stat-ico in"><?= icon('arrow-in', 22) ?></div>
    <div><strong>Receive stock</strong><span class="s">Post an IN movement</span></div>
  </a>
  <a class="action-card" href="<?= e(url('staff/new_movement.php?type=OUT')) ?>">
    <div class="stat-ico out"><?= icon('arrow-out', 22) ?></div>
    <div><strong>Dispatch stock</strong><span class="s">Post an OUT movement</span></div>
  </a>
  <a class="action-card" href="<?= e(url('staff/new_movement.php?type=ADJUST')) ?>">
    <div class="stat-ico warn"><?= icon('sliders', 22) ?></div>
    <div><strong>Adjust stock</strong><span class="s">Correct counts or damage</span></div>
  </a>
</div>

<div class="grid grid-4 mt">
  <div class="card stat"><div class="stat-ico"><?= icon('list', 22) ?></div><div><div class="stat-label">My movements</div><div class="stat-value"><?= (int)$mine['total'] ?></div></div></div>
  <div class="card stat"><div class="stat-ico in"><?= icon('arrow-in', 22) ?></div><div><div class="stat-label">Receipts (IN)</div><div class="stat-value"><?= (int)$mine['ins'] ?></div></div></div>
  <div class="card stat"><div class="stat-ico out"><?= icon('arrow-out', 22) ?></div><div><div class="stat-label">Dispatches (OUT)</div><div class="stat-value"><?= (int)$mine['outs'] ?></div></div></div>
  <div class="card stat"><div class="stat-ico warn"><?= icon('sliders', 22) ?></div><div><div class="stat-label">Adjustments</div><div class="stat-value"><?= (int)$mine['adjs'] ?></div></div></div>
</div>

<div class="grid grid-main mt">
  <div class="card">
    <div class="card-head">
      <div class="card-title">My recent movements</div>
      <a class="btn btn-outline btn-sm" href="<?= e(url('staff/my_movements.php')) ?>">See all</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>Date</th><th>Product</th><th>WH</th><th>Type</th><th class="num">Qty</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?><tr><td class="empty-row" colspan="6">You have not posted any movements yet.</td></tr><?php endif; ?>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="mono"><?= e(movement_code((int)$r['movement_id'])) ?></td>
            <td><?= e(fmt_date($r['movement_date'])) ?></td>
            <td><?= e($r['product_name']) ?> <span class="muted mono">(<?= e($r['sku']) ?>)</span></td>
            <td><?= e($r['warehouse_code']) ?></td>
            <td><?= type_badge($r['movement_type']) ?></td>
            <td class="num"><?= signed_qty($r['movement_type'], (int)$r['quantity']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" style="align-self:start">
    <div class="card-head"><div class="card-title">Stock snapshot</div><a class="btn btn-outline btn-sm" href="<?= e(url('stock.php')) ?>">Details</a></div>
    <?php foreach ($stock as $s):
        $low = (int)$s['total'] <= (int)$s['reorder']; ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0e6d9">
        <div><strong><?= e($s['product_name']) ?></strong><div class="muted mono" style="font-size:12px"><?= e($s['sku']) ?></div></div>
        <div style="text-align:right">
          <strong><?= fmt_qty($s['total']) ?></strong>
          <div><span class="badge <?= $low ? 'badge-low' : 'badge-ok' ?>"><?= $low ? 'Low' : 'OK' ?></span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
