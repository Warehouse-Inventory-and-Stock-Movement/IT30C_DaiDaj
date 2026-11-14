<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();  
$pdo = db();
$warehouses = $pdo->query('SELECT warehouse_code, warehouse_name FROM warehouses ORDER BY warehouse_code')->fetchAll();
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

$q   = trim((string)($_GET['q'] ?? ''));
$cat = (int)($_GET['category'] ?? 0);

$sql = 'SELECT * FROM v_stock_on_hand WHERE is_active = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (sku LIKE ? OR product_name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($cat > 0) {
    $sql .= ' AND category_id = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY sku, warehouse_code';
$st = $pdo->prepare($sql);
$st->execute($params);


$products = [];
foreach ($st->fetchAll() as $r) {
    $k = $r['sku'];
    if (!isset($products[$k])) {
        $products[$k] = [
            'sku' => $k, 'name' => $r['product_name'], 'category' => $r['category_name'],
            'supplier' => $r['supplier_name'], 'reorder' => (int)$r['reorder_level'],
            'wh' => [], 'total' => 0,
        ];
    }
    $products[$k]['wh'][$r['warehouse_code']] = (int)$r['on_hand'];
    $products[$k]['total'] += (int)$r['on_hand'];
}

$pageTitle = 'Stock on Hand';
$pageSub   = 'Quantity on hand is calculated live from the movement ledger (IN - OUT +/- ADJUST).';
$active    = 'stock';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <form method="get" class="filters">
    <div class="field" style="flex:2">
      <label for="q">Search</label>
      <input class="input" id="q" name="q" value="<?= e($q) ?>" placeholder="SKU or product name">
    </div>
    <div class="field">
      <label for="category">Category</label>
      <select class="select" id="category" name="category">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['category_id'] ?>" <?= $cat === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="btn-row">
      <button class="btn btn-primary" type="submit"><?= icon('search', 16) ?> Filter</button>
      <a class="btn btn-outline" href="<?= e(url('stock.php')) ?>">Reset</a>
    </div>
  </form>
</div>

<div class="card mt">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>SKU</th><th>Product</th><th>Category</th><th>Supplier</th>
          <?php foreach ($warehouses as $w): ?><th class="num"><?= e($w['warehouse_code']) ?></th><?php endforeach; ?>
          <th class="num">Total</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$products): ?>
        <tr><td class="empty-row" colspan="<?= 6 + count($warehouses) ?>">No products match your filters.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p):
          if ($p['total'] <= 0)                 { $cls = 'badge-zero'; $lbl = 'Out of stock'; }
          elseif ($p['total'] <= $p['reorder']) { $cls = 'badge-low';  $lbl = 'Low stock'; }
          else                                  { $cls = 'badge-ok';   $lbl = 'In stock'; }
      ?>
        <tr>
          <td class="mono"><?= e($p['sku']) ?></td>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><?= e($p['category']) ?></td>
          <td class="muted"><?= e($p['supplier']) ?></td>
          <?php foreach ($warehouses as $w): ?>
            <td class="num"><?= fmt_qty($p['wh'][$w['warehouse_code']] ?? 0) ?></td>
          <?php endforeach; ?>
          <td class="num"><strong><?= fmt_qty($p['total']) ?></strong></td>
          <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="muted" style="margin-top:12px">Low stock means total quantity is at or below the product's reorder level.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
