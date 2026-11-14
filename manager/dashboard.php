<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('manager');

$pdo = db();

$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
$totalUnits    = (int)$pdo->query('SELECT COALESCE(SUM(on_hand),0) FROM v_stock_on_hand WHERE is_active = 1')->fetchColumn();
$unitsIn       = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE movement_type = 'IN'")->fetchColumn();
$unitsOut      = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock_movements WHERE movement_type = 'OUT'")->fetchColumn();
$totalMoves    = (int)$pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();

$lowStock = $pdo->query(
    'SELECT sku, product_name, category_name, supplier_name,
            SUM(on_hand) AS total, MAX(reorder_level) AS reorder
       FROM v_stock_on_hand
      WHERE is_active = 1
      GROUP BY sku, product_name, category_name, supplier_name
     HAVING SUM(on_hand) <= MAX(reorder_level)
      ORDER BY SUM(on_hand) ASC'
)->fetchAll();

$byWarehouse = $pdo->query(
    'SELECT warehouse_code, warehouse_name, SUM(on_hand) AS total
       FROM v_stock_on_hand WHERE is_active = 1
      GROUP BY warehouse_code, warehouse_name ORDER BY warehouse_code'
)->fetchAll();
$maxWh = max(1, ...array_map(fn($r) => (int)$r['total'], $byWarehouse ?: [['total' => 1]]));

$recent = $pdo->query(
    'SELECT m.movement_id, m.movement_date, m.sku, p.product_name, m.warehouse_code,
            m.movement_type, m.quantity, u.full_name, u.employee_code
       FROM stock_movements m
       JOIN products p ON p.sku = m.sku
       JOIN users u    ON u.user_id = m.handled_by
      ORDER BY m.movement_id DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Manager Dashboard';
$pageSub   = 'Inventory health and warehouse activity at a glance.';
$active    = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
  <div>
    <h2>Good day, <?= e(explode(' ', $user['name'])[0]) ?>.</h2>
    <p>
      <?php if ($lowStock): ?>
        <?= count($lowStock) ?> product<?= count($lowStock) > 1 ? 's are' : ' is' ?> at or below the reorder level.
      <?php else: ?>
        All products are above their reorder levels.
      <?php endif; ?>
    </p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('manager/movements.php')) ?>">View movement ledger</a>
</div>

<div class="grid grid-4 mt">
  <div class="card stat"><div class="stat-ico"><?= icon('layers', 22) ?></div>
    <div><div class="stat-label">Units on hand</div><div class="stat-value"><?= fmt_qty($totalUnits) ?></div></div></div>
  <div class="card stat"><div class="stat-ico"><?= icon('box', 22) ?></div>
    <div><div class="stat-label">Active products</div><div class="stat-value"><?= fmt_qty($totalProducts) ?></div></div></div>
  <div class="card stat"><div class="stat-ico in"><?= icon('arrow-in', 22) ?></div>
    <div><div class="stat-label">Units received (IN)</div><div class="stat-value"><?= fmt_qty($unitsIn) ?></div></div></div>
  <div class="card stat"><div class="stat-ico out"><?= icon('arrow-out', 22) ?></div>
    <div><div class="stat-label">Units dispatched (OUT)</div><div class="stat-value"><?= fmt_qty($unitsOut) ?></div></div></div>
</div>

<div class="grid grid-main mt">
  <div class="card">
    <div class="card-head">
      <div><div class="card-title">Recent movements</div><div class="card-sub"><?= fmt_qty($totalMoves) ?> posted in total</div></div>
      <a class="btn btn-outline btn-sm" href="<?= e(url('manager/movements.php')) ?>">See all</a>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>Date</th><th>Product</th><th>WH</th><th>Type</th><th class="num">Qty</th><th>Handled by</th></tr></thead>
        <tbody>
        <?php if (!$recent): ?><tr><td class="empty-row" colspan="7">No movements yet.</td></tr><?php endif; ?>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="mono"><?= e(movement_code((int)$r['movement_id'])) ?></td>
            <td><?= e(fmt_date($r['movement_date'])) ?></td>
            <td><?= e($r['product_name']) ?> <span class="muted mono">(<?= e($r['sku']) ?>)</span></td>
            <td><?= e($r['warehouse_code']) ?></td>
            <td><?= type_badge($r['movement_type']) ?></td>
            <td class="num"><?= signed_qty($r['movement_type'], (int)$r['quantity']) ?></td>
            <td><?= e($r['full_name']) ?> <span class="muted mono"><?= e($r['employee_code']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card">
      <div class="card-head"><div class="card-title">Stock by warehouse</div></div>
      <?php foreach ($byWarehouse as $w): ?>
        <div class="bar-row">
          <div class="bar-label"><span><?= e($w['warehouse_name']) ?></span><strong><?= fmt_qty($w['total']) ?></strong></div>
          <div class="bar"><span style="width: <?= max(2, round(((int)$w['total'] / $maxWh) * 100)) ?>%"></span></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">Low-stock alerts</div></div>
      <?php if (!$lowStock): ?>
        <p class="muted" style="margin:0">Nothing needs reordering right now.</p>
      <?php endif; ?>
      <?php foreach ($lowStock as $p): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid #f0e6d9">
          <div>
            <strong><?= e($p['product_name']) ?></strong>
            <div class="muted" style="font-size:12.5px"><?= e($p['sku']) ?> &middot; <?= e($p['supplier_name']) ?></div>
          </div>
          <div style="text-align:right">
            <span class="badge <?= (int)$p['total'] <= 0 ? 'badge-zero' : 'badge-low' ?>"><?= fmt_qty($p['total']) ?> left</span>
            <div class="muted" style="font-size:12px">reorder at <?= fmt_qty($p['reorder']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid grid-3 mt">
  <a class="action-card" href="<?= e(url('manager/products.php')) ?>"><div class="stat-ico"><?= icon('box', 20) ?></div><div><strong>Manage products</strong><span class="s">SKUs, categories, reorder levels</span></div></a>
  <a class="action-card" href="<?= e(url('manager/suppliers.php')) ?>"><div class="stat-ico"><?= icon('truck', 20) ?></div><div><strong>Manage suppliers</strong><span class="s">Supplier codes and contacts</span></div></a>
  <a class="action-card" href="<?= e(url('manager/users.php')) ?>"><div class="stat-ico"><?= icon('users', 20) ?></div><div><strong>Users &amp; roles</strong><span class="s">Promote, demote, deactivate</span></div></a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
